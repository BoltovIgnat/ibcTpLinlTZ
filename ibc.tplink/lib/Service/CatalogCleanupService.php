<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Loader;
use Ibc\Tplink\Helper\ImportLog;

final class CatalogCleanupService
{
    /** @return array{iblock_id: int, iblock_code: string, deleted: int, dry_run: bool} */
    public function clearAll(bool $dryRun = false): array
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Module iblock is required');
        }

        $iblockId = IblockInstaller::getIblockId();
        if ($iblockId <= 0) {
            $iblockId = (new IblockInstaller())->ensureCatalog();
        }

        $ids = $this->collectElementIds($iblockId);
        $deleted = 0;

        if (!$dryRun) {
            foreach ($ids as $elementId) {
                if (\CIBlockElement::Delete($elementId)) {
                    ++$deleted;
                }
            }
            (new ImportRunGuard())->releaseLock();
        }

        $result = [
            'iblock_id' => $iblockId,
            'iblock_code' => IblockInstaller::IBLOCK_CODE,
            'deleted' => $dryRun ? 0 : $deleted,
            'would_delete' => count($ids),
            'dry_run' => $dryRun,
        ];

        ImportLog::info('Catalog cleanup', $result + ['user_id' => self::currentUserId()]);

        return $result;
    }

    /** @return list<int> */
    private function collectElementIds(int $iblockId): array
    {
        $ids = [];
        $rs = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId],
            false,
            false,
            ['ID']
        );
        while ($row = $rs->Fetch()) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    private static function currentUserId(): int
    {
        global $USER;

        return is_object($USER) ? (int)$USER->GetID() : 0;
    }
}
