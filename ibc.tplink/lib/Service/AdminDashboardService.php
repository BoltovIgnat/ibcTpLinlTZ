<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

final class AdminDashboardService
{
    /** @return array<string, mixed> */
    public function collect(): array
    {
        $iblockId = IblockInstaller::getIblockId();
        $stats = [
            'iblock_id' => $iblockId,
            'elements_total' => 0,
            'needs_review' => 0,
            'missing_at_source' => 0,
            'active' => 0,
        ];

        if ($iblockId > 0 && Loader::includeModule('iblock')) {
            $stats['elements_total'] = (int)\CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId], []);
            $stats['needs_review'] = (int)\CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $iblockId, 'PROPERTY_NEEDS_REVIEW' => 'Y'],
                []
            );
            $stats['missing_at_source'] = (int)\CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $iblockId, 'PROPERTY_MISSING_AT_SOURCE' => 'Y'],
                []
            );
            $stats['active'] = (int)\CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
                []
            );
        }

        $runs = (new ImportRunLogReader())->listRuns(5);
        $lastRun = $runs[0] ?? null;

        return [
            'stats' => $stats,
            'iblock_code' => IblockInstaller::IBLOCK_CODE,
            'last_run' => $lastRun,
            'recent_runs' => $runs,
            'cli_command' => 'php -f local/modules/ibc.tplink/tools/import.php',
            'source_url' => Option::get('ibc.tplink', 'source_list_url', ''),
            'use_sitemap' => Option::get('ibc.tplink', 'use_sitemap', 'Y'),
            'log_dir' => Option::get('ibc.tplink', 'log_dir', '/local/logs'),
        ];
    }
}
