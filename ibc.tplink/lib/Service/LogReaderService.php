<?php

namespace Ibc\Tplink\Service;

/**
 * Чтение файловых логов модуля (log/{channel}/YYYY-MM-DD.log).
 */
final class LogReaderService
{
    private const MODULE_LOG_ROOT = __DIR__ . '/../../log';

    private const MAX_TAIL = 2000;

    /** @return list<string> */
    public function listChannels(): array
    {
        $root = self::MODULE_LOG_ROOT;
        if (!is_dir($root)) {
            return ['import', 'api'];
        }

        $channels = [];
        foreach (scandir($root) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitignore') {
                continue;
            }
            $path = $root . '/' . $item;
            if (is_dir($path) && preg_match('/^[a-z0-9_-]+$/i', $item)) {
                $channels[] = $item;
            }
        }

        sort($channels);

        return $channels !== [] ? $channels : ['import', 'api'];
    }

    /** @return list<string> */
    public function listDates(string $channel): array
    {
        $dir = $this->channelDir($channel);
        if ($dir === null || !is_dir($dir)) {
            return [];
        }

        $dates = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\.log$/', $file, $m)) {
                $dates[] = $m[1];
            }
        }

        rsort($dates);

        return $dates;
    }

    /**
     * @return array{
     *   channel: string,
     *   date: string,
     *   file: string,
     *   total_lines: int,
     *   returned: int,
     *   entries: list<array{time: string, level: string, message: string, context: array<string, mixed>|null, raw: string}>
     * }
     */
    public function read(
        string $channel,
        ?string $date = null,
        int $tail = 100,
        ?string $level = null,
        ?string $search = null
    ): array {
        $channel = $this->sanitizeChannel($channel);
        $date = $this->resolveDate($channel, $date);
        $tail = max(1, min(self::MAX_TAIL, $tail));
        $level = $level !== null && $level !== '' ? strtoupper(trim($level)) : null;
        $search = $search !== null && $search !== '' ? trim($search) : null;

        $file = self::MODULE_LOG_ROOT . '/' . $channel . '/' . $date . '.log';
        if (!is_file($file)) {
            return [
                'channel' => $channel,
                'date' => $date,
                'file' => $channel . '/' . $date . '.log',
                'total_lines' => 0,
                'returned' => 0,
                'entries' => [],
            ];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            $lines = [];
        }

        $entries = [];
        foreach ($lines as $line) {
            $parsed = $this->parseLine($line);
            if ($level !== null && ($parsed['level'] ?? '') !== $level) {
                continue;
            }
            if ($search !== null && stripos($line, $search) === false) {
                continue;
            }
            $entries[] = $parsed;
        }

        if (count($entries) > $tail) {
            $entries = array_slice($entries, -$tail);
        }

        return [
            'channel' => $channel,
            'date' => $date,
            'file' => $channel . '/' . $date . '.log',
            'total_lines' => count($lines),
            'returned' => count($entries),
            'entries' => $entries,
        ];
    }

    /** @return array{time: string, level: string, message: string, context: array<string, mixed>|null, raw: string} */
    private function parseLine(string $line): array
    {
        if (preg_match('/^\[([^\]]+)\]\s+\[([^\]]+)\]\s+(.+)$/', $line, $m)) {
            $message = $m[3];
            $context = null;
            if (str_contains($message, ' | ')) {
                [$message, $json] = explode(' | ', $message, 2);
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $context = $decoded;
                }
            }

            return [
                'time' => $m[1],
                'level' => $m[2],
                'message' => $message,
                'context' => $context,
                'raw' => $line,
            ];
        }

        return [
            'time' => '',
            'level' => '',
            'message' => $line,
            'context' => null,
            'raw' => $line,
        ];
    }

    private function sanitizeChannel(string $channel): string
    {
        return preg_replace('/[^a-z0-9_-]+/i', '', trim($channel)) ?: 'import';
    }

    private function resolveDate(string $channel, ?string $date): string
    {
        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        $dates = $this->listDates($channel);

        return $dates[0] ?? date('Y-m-d');
    }

    private function channelDir(string $channel): ?string
    {
        $channel = $this->sanitizeChannel($channel);
        $dir = self::MODULE_LOG_ROOT . '/' . $channel;
        $realRoot = realpath(self::MODULE_LOG_ROOT);
        $realDir = realpath($dir);
        if ($realRoot === false) {
            return is_dir($dir) ? $dir : null;
        }
        if ($realDir === false || !str_starts_with($realDir, $realRoot)) {
            return null;
        }

        return $realDir;
    }
}
