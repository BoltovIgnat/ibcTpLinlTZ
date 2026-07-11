<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;

/**
 * Чтение JSON-логов прогонов импорта (tplink_import_*.json в log_dir).
 */
final class ImportRunLogReader
{
    /** @return list<array{file: string, path: string, started_at: ?string, finished_at: ?string, counters: array<string, int>, mtime: int}> */
    public function listRuns(int $limit = 50): array
    {
        $dir = $this->logDirectory();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . 'tplink_import_*.json') ?: [];
        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $runs = [];
        foreach (array_slice($files, 0, max(1, min(200, $limit))) as $path) {
            $basename = basename($path);
            $meta = $this->readMeta($path);
            $runs[] = [
                'file' => $basename,
                'path' => $path,
                'started_at' => $meta['started_at'] ?? null,
                'finished_at' => $meta['finished_at'] ?? null,
                'counters' => $meta['counters'] ?? [],
                'mtime' => (int)filemtime($path),
            ];
        }

        return $runs;
    }

    /**
     * @return array<string, mixed>
     */
    public function readRun(string $file, ?int $tailItems = null): array
    {
        $path = $this->resolveRunPath($file);
        if ($path === null || !is_file($path)) {
            return [
                'file' => $file,
                'found' => false,
                'payload' => null,
            ];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read import run log: ' . $file);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid JSON in import run log: ' . $file);
        }

        if ($tailItems !== null && isset($payload['items']) && is_array($payload['items'])) {
            $items = $payload['items'];
            if (count($items) > $tailItems) {
                $payload['items'] = array_slice($items, -$tailItems);
                $payload['_items_truncated'] = true;
                $payload['_items_total'] = count($items);
            }
        }

        return [
            'file' => basename($path),
            'found' => true,
            'path' => $path,
            'payload' => $payload,
        ];
    }

    /** @return array{started_at?: string, finished_at?: string, counters?: array<string, int>} */
    private function readMeta(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [];
        }

        return [
            'started_at' => isset($payload['started_at']) ? (string)$payload['started_at'] : null,
            'finished_at' => isset($payload['finished_at']) ? (string)$payload['finished_at'] : null,
            'counters' => is_array($payload['counters'] ?? null) ? $payload['counters'] : [],
        ];
    }

    private function resolveRunPath(string $file): ?string
    {
        $file = basename($file);
        if (!preg_match('/^tplink_import_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.json$/', $file)) {
            return null;
        }

        $dir = realpath($this->logDirectory());
        if ($dir === false) {
            return null;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $dir)) {
            return null;
        }

        return $real;
    }

    private function logDirectory(): string
    {
        $relative = (string)Option::get('ibc.tplink', 'log_dir', '/local/logs');
        $relative = '/' . trim($relative, '/');
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($docRoot === '') {
            return '';
        }

        return $docRoot . $relative;
    }
}
