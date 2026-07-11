<?php

/**
 * CLI entry: php -f local/modules/ibc.tplink/tools/import.php [--dry-run] [--csv=path]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../../../');
if (!is_file($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Bitrix prolog not found. Set DOCUMENT_ROOT.\n");
    exit(1);
}

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Ibc\Tplink\Helper\ImportLog;
use Ibc\Tplink\Service\ImportServiceFactory;

if (!Loader::includeModule('ibc.tplink')) {
    fwrite(STDERR, "Module ibc.tplink not installed\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$csvPath = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--csv=')) {
        $csvPath = substr($arg, 6);
    }
}

try {
    $result = ImportServiceFactory::create()->run($dryRun, $csvPath);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    ImportLog::error('Import CLI failed', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
