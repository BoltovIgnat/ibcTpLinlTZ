<?php

/**
 * CLI: php -f local/modules/ibc.tplink/tools/clear_catalog.php [--dry-run] [--yes]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../../../');
if (!is_file($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Bitrix prolog not found.\n");
    exit(1);
}

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Ibc\Tplink\Service\CatalogCleanupService;

if (!Loader::includeModule('ibc.tplink')) {
    fwrite(STDERR, "Module ibc.tplink not installed\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$yes = in_array('--yes', $argv ?? [], true);

if (!$yes && !$dryRun) {
    fwrite(STDERR, "Add --yes to delete all elements in tplink_catalog_stage, or --dry-run to preview.\n");
    exit(1);
}

try {
    $result = (new CatalogCleanupService())->clearAll($dryRun);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Cleanup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
