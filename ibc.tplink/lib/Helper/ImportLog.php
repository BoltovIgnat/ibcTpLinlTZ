<?php

namespace Ibc\Tplink\Helper;

/** Логи импорта (канал import). */
final class ImportLog
{
    public static function info(string $message, array $context = []): void
    {
        ModuleFileLogger::channel('import')->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        ModuleFileLogger::channel('import')->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        ModuleFileLogger::channel('import')->error($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        ModuleFileLogger::channel('import')->debug($message, $context);
    }
}
