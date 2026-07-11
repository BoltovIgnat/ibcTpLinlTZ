<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;

final class ImportLogWriter
{
    /** @param array<string, mixed> $payload */
    public function write(array $payload): string
    {
        $dir = $this->logDirectory();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create log directory: ' . $dir);
        }

        $filename = 'tplink_import_' . date('Y-m-d_H-i-s') . '.json';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode import log JSON');
        }
        file_put_contents($path, $json);

        return $path;
    }

    /** @param list<array<string, mixed>> $items */
    public function writeCsv(string $path, array $items): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot write CSV: ' . $path);
        }
        fputcsv($fh, ['article', 'name', 'category', 'source_url', 'status'], ';');
        foreach ($items as $item) {
            fputcsv($fh, [
                $item['article'] ?? '',
                $item['name'] ?? '',
                $item['category'] ?? '',
                $item['url'] ?? ($item['source_url'] ?? ''),
                $item['status'] ?? '',
            ], ';');
        }
        fclose($fh);
    }

    private function logDirectory(): string
    {
        $relative = (string)Option::get('ibc.tplink', 'log_dir', '/local/logs');
        $relative = '/' . trim($relative, '/');
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($docRoot === '') {
            throw new \RuntimeException('DOCUMENT_ROOT is not set');
        }

        return $docRoot . $relative;
    }
}
