<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

final class IblockInstaller
{
    public const IBLOCK_CODE = 'tplink_catalog_stage';
    public const OPTION_IBLOCK_ID = 'iblock_tplink_catalog_stage';

    public function ensureCatalog(): int
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Module iblock is required');
        }

        $existing = \CIBlock::GetList([], ['TYPE' => 'catalog', 'CODE' => self::IBLOCK_CODE])->Fetch();
        if ($existing) {
            $iblockId = (int)$existing['ID'];
            Option::set('ibc.tplink', self::OPTION_IBLOCK_ID, (string)$iblockId);
            $this->ensureProperties($iblockId);

            return $iblockId;
        }

        $this->ensureCatalogType();
        $ib = new \CIBlock();
        $iblockId = (int)$ib->Add([
            'ACTIVE' => 'Y',
            'NAME' => 'TP-Link Catalog (Test Import)',
            'CODE' => self::IBLOCK_CODE,
            'IBLOCK_TYPE_ID' => 'catalog',
            'SITE_ID' => $this->siteIds(),
            'SORT' => 500,
            'GROUP_ID' => ['2' => 'R'],
            'LIST_PAGE_URL' => '',
            'DETAIL_PAGE_URL' => '',
        ]);
        if ($iblockId <= 0) {
            throw new \RuntimeException('Failed to create iblock: ' . $ib->LAST_ERROR);
        }

        Option::set('ibc.tplink', self::OPTION_IBLOCK_ID, (string)$iblockId);
        $this->ensureProperties($iblockId);

        return $iblockId;
    }

    public static function getIblockId(): int
    {
        $id = (int)Option::get('ibc.tplink', self::OPTION_IBLOCK_ID, '0');
        if ($id > 0) {
            return $id;
        }

        if (!Loader::includeModule('iblock')) {
            return 0;
        }
        $row = \CIBlock::GetList([], ['TYPE' => 'catalog', 'CODE' => self::IBLOCK_CODE])->Fetch();

        return $row ? (int)$row['ID'] : 0;
    }

    private function ensureCatalogType(): void
    {
        if (\CIBlockType::GetByID('catalog')->Fetch()) {
            return;
        }
        $type = new \CIBlockType();
        $type->Add([
            'ID' => 'catalog',
            'SECTIONS' => 'Y',
            'IN_RSS' => 'N',
            'SORT' => 100,
            'LANG' => [
                'ru' => ['NAME' => 'Каталог', 'SECTION_NAME' => 'Разделы', 'ELEMENT_NAME' => 'Товары'],
            ],
        ]);
    }

    /** @return list<string> */
    private function siteIds(): array
    {
        $sites = [];
        $rs = \CSite::GetList('', '', ['ACTIVE' => 'Y']);
        while ($row = $rs->Fetch()) {
            $sites[] = (string)$row['LID'];
        }

        return $sites !== [] ? $sites : ['s1'];
    }

    private function ensureProperties(int $iblockId): void
    {
        $defs = [
            ['CODE' => 'ARTICLE', 'NAME' => 'Артикул', 'TYPE' => 'S', 'REQUIRED' => 'Y'],
            ['CODE' => 'FULL_ARTICLE', 'NAME' => 'Полный артикул', 'TYPE' => 'S', 'REQUIRED' => 'Y'],
            ['CODE' => 'CATEGORY', 'NAME' => 'Категория', 'TYPE' => 'S', 'REQUIRED' => 'Y'],
            ['CODE' => 'WIFI_STANDARD', 'NAME' => 'Wi-Fi стандарт', 'TYPE' => 'S'],
            ['CODE' => 'WIFI_SPEED', 'NAME' => 'Скорость Wi-Fi', 'TYPE' => 'S'],
            ['CODE' => 'WAN_SPEED', 'NAME' => 'Скорость WAN', 'TYPE' => 'S'],
            ['CODE' => 'LAN_PORTS', 'NAME' => 'LAN-порты', 'TYPE' => 'N'],
            ['CODE' => 'SOURCE_URL', 'NAME' => 'URL карточки', 'TYPE' => 'S', 'REQUIRED' => 'Y'],
            ['CODE' => 'SYNCED_AT', 'NAME' => 'Дата синхронизации', 'TYPE' => 'S', 'REQUIRED' => 'Y'],
            ['CODE' => 'MISSING_AT_SOURCE', 'NAME' => 'Нет на источнике', 'TYPE' => 'L', 'LIST' => ['N' => 'N', 'Y' => 'Y'], 'DEF' => 'N'],
            ['CODE' => 'NEEDS_REVIEW', 'NAME' => 'Требует проверки', 'TYPE' => 'L', 'LIST' => ['N' => 'N', 'Y' => 'Y'], 'DEF' => 'N'],
        ];

        foreach ($defs as $def) {
            $this->ensureProperty($iblockId, $def);
        }
    }

    /** @param array<string, mixed> $def */
    private function ensureProperty(int $iblockId, array $def): void
    {
        $existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $def['CODE']])->Fetch();
        if ($existing) {
            return;
        }

        $fields = [
            'IBLOCK_ID' => $iblockId,
            'NAME' => $def['NAME'],
            'ACTIVE' => 'Y',
            'CODE' => $def['CODE'],
            'PROPERTY_TYPE' => $def['TYPE'],
            'IS_REQUIRED' => ($def['REQUIRED'] ?? 'N'),
            'MULTIPLE' => 'N',
        ];
        if ($def['TYPE'] === 'L' && !empty($def['LIST'])) {
            $fields['PROPERTY_TYPE'] = 'L';
            $fields['LIST_TYPE'] = 'L';
            $fields['VALUES'] = [];
            $sort = 100;
            foreach ($def['LIST'] as $xml => $label) {
                $fields['VALUES'][] = [
                    'VALUE' => $label,
                    'XML_ID' => $xml,
                    'SORT' => $sort,
                    'DEF' => ($def['DEF'] ?? '') === $xml ? 'Y' : 'N',
                ];
                $sort += 100;
            }
        }

        $prop = new \CIBlockProperty();
        $prop->Add($fields);
    }
}
