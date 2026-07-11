<?php

namespace Ibc\Tplink\Helper;

/**
 * Файловый лог модуля: local/modules/ibc.tplink/log/{channel}/YYYY-MM-DD.log
 */
final class ModuleFileLogger
{
    private const MODULE_ROOT = __DIR__ . '/../..';

    private string $channel;

    public function __construct(string $channel)
    {
        $channel = trim($channel);
        $this->channel = preg_replace('/[^a-z0-9_-]+/i', '', $channel) ?: 'general';
    }

    public static function channel(string $channel): self
    {
        return new self($channel);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $dir = self::MODULE_ROOT . '/log/' . $this->channel;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $line = sprintf('[%s] [%s] %s', date('Y-m-d H:i:s'), $level, $message);
        if ($context !== []) {
            $encoded = json_encode(self::maskSecrets($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $line .= ' | ' . $encoded;
            }
        }

        @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private static function maskSecrets(array $context): array
    {
        $masked = [];
        foreach ($context as $key => $value) {
            $keyLower = strtolower((string)$key);
            if (in_array($keyLower, ['token', 'password', 'secret', 'authorization'], true)) {
                $masked[$key] = '***';
                continue;
            }
            if (is_array($value)) {
                $masked[$key] = self::maskSecrets($value);
                continue;
            }
            $masked[$key] = $value;
        }

        return $masked;
    }
}
