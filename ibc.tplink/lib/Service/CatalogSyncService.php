<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Loader;
use Ibc\Tplink\Dto\ProductVariantDto;
use Ibc\Tplink\Dto\SyncResultDto;

final class CatalogSyncService
{
    /** @var array<string, int> */
    private array $indexByFull = [];

    /** @var array<string, int> */
    private array $indexByFallback = [];

    /** @var list<string> */
    private const DIFF_SKIP = ['SYNCED_AT'];

    /** @var list<string> */
    private const LIST_FLAGS = ['NEEDS_REVIEW', 'MISSING_AT_SOURCE'];

    /** @var array<int, string> */
    private array $labelByElementId = [];

    /** @var array<string, array<string, int>> */
    private array $listEnumIds = [];

    public function __construct(
        private readonly int $iblockId,
    ) {
        $this->buildIndex();
    }

    public function sync(ProductVariantDto $variant, string $syncedAt): SyncResultDto
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('iblock module required');
        }

        $key = $variant->needsReview && $variant->fallbackKey
            ? $variant->fallbackKey
            : $variant->fullArticle;

        $elementId = $this->resolveElementId($variant);

        $props = array_merge($variant->fields, [
            'FULL_ARTICLE' => $variant->needsReview && $variant->fallbackKey
                ? $variant->fallbackKey
                : $variant->fullArticle,
            'SOURCE_URL' => $variant->sourceUrl,
            'SYNCED_AT' => $syncedAt,
            'MISSING_AT_SOURCE' => 'N',
            'NEEDS_REVIEW' => $variant->needsReview ? 'Y' : 'N',
        ]);

        $fields = [
            'IBLOCK_ID' => $this->iblockId,
            'NAME' => $variant->name,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => $this->normalizeProps($props),
        ];

        if ($elementId <= 0) {
            $el = new \CIBlockElement();
            $newId = (int)$el->Add($fields);
            if ($newId <= 0) {
                throw new \RuntimeException('Element add failed: ' . $el->LAST_ERROR);
            }
            $this->remember($newId, $variant, $props);

            return new SyncResultDto('new', $newId);
        }

        $changed = $this->diffChanged($elementId, $variant, $props);
        if ($changed === []) {
            $this->setMissingFlag($elementId, 'N');

            return new SyncResultDto('unchanged', $elementId);
        }

        $el = new \CIBlockElement();
        if (!$el->Update($elementId, ['NAME' => $variant->name])) {
            throw new \RuntimeException('Element update failed: ' . $el->LAST_ERROR);
        }
        \CIBlockElement::SetPropertyValuesEx($elementId, $this->iblockId, $this->normalizeProps($props));
        $this->remember($elementId, $variant, $props);

        return new SyncResultDto('updated', $elementId, $changed);
    }

    /** @param list<int> $seenElementIds */
    public function markMissing(array $seenElementIds): array
    {
        $seen = array_fill_keys(array_map('intval', $seenElementIds), true);
        $results = [];

        foreach ($this->labelByElementId as $elementId => $label) {
            if (isset($seen[$elementId])) {
                continue;
            }
            $this->setMissingFlag($elementId, 'Y');
            $results[] = [
                'article' => $label,
                'status' => 'missing',
                'element_id' => $elementId,
            ];
        }

        return $results;
    }

    private function buildIndex(): void
    {
        if ($this->iblockId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId],
            false,
            false,
            ['ID', 'NAME', 'PROPERTY_FULL_ARTICLE', 'PROPERTY_SOURCE_URL', 'PROPERTY_NEEDS_REVIEW']
        );
        while ($row = $rs->Fetch()) {
            $id = (int)$row['ID'];
            $full = trim((string)($row['PROPERTY_FULL_ARTICLE_VALUE'] ?? ''));
            if ($full !== '') {
                $this->indexByFull[$full] = $id;
                $this->labelByElementId[$id] = $full;
            }
            if ($this->isListFlagYes($row['PROPERTY_NEEDS_REVIEW_VALUE'] ?? $row['PROPERTY_NEEDS_REVIEW_ENUM_ID'] ?? '')) {
                $fallback = trim((string)$row['NAME']) . '|' . trim((string)($row['PROPERTY_SOURCE_URL_VALUE'] ?? ''));
                $this->indexByFallback[$fallback] = $id;
                if ($full === '' || str_contains($full, '|')) {
                    $this->indexByFull[$fallback] = $id;
                    $this->labelByElementId[$id] = $fallback;
                }
            }
        }
    }

    /** @param array<string, scalar|null> $props */
    private function remember(int $elementId, ProductVariantDto $variant, array $props): void
    {
        if ($variant->needsReview && $variant->fallbackKey) {
            $this->indexByFallback[$variant->fallbackKey] = $elementId;
            $this->indexByFull[$variant->fallbackKey] = $elementId;
            $this->labelByElementId[$elementId] = $variant->fallbackKey;
        } else {
            $this->indexByFull[$variant->fullArticle] = $elementId;
            $this->labelByElementId[$elementId] = $variant->fullArticle;
        }
    }

    /** @param array<string, scalar|null> $props @return list<string> */
    private function diffChanged(int $elementId, ProductVariantDto $variant, array $props): array
    {
        $current = [];
        $rs = \CIBlockElement::GetByID($elementId);
        if ($row = $rs->GetNextElement()) {
            $fields = $row->GetFields();
            $current['NAME'] = (string)$fields['NAME'];
            foreach ($row->GetProperties() as $code => $prop) {
                if (in_array($code, self::LIST_FLAGS, true)) {
                    $current[$code] = $this->listFlagFromProperty($prop);
                } else {
                    $val = is_array($prop['VALUE']) ? implode(', ', $prop['VALUE']) : (string)($prop['VALUE'] ?? '');
                    $current[$code] = $val;
                }
            }
        }

        $changed = [];
        if (($current['NAME'] ?? '') !== $variant->name) {
            $changed[] = 'NAME';
        }
        foreach ($props as $code => $value) {
            if (in_array($code, self::DIFF_SKIP, true)) {
                continue;
            }
            $newVal = $this->normalizeCompareValue($code, $value);
            $oldVal = $this->normalizeCompareValue($code, $current[$code] ?? '');
            if ($oldVal !== $newVal) {
                $changed[] = $code;
            }
        }

        return $changed;
    }

    private function resolveElementId(ProductVariantDto $variant): int
    {
        if ($variant->needsReview && $variant->fallbackKey) {
            return $this->indexByFallback[$variant->fallbackKey]
                ?? $this->indexByFull[$variant->fallbackKey]
                ?? $this->indexByFull[$variant->fullArticle]
                ?? 0;
        }

        return $this->indexByFull[$variant->fullArticle] ?? 0;
    }

    private function isListFlagYes(mixed $value): bool
    {
        return $this->normalizeListFlag($value) === 'Y';
    }

    private function normalizeListFlag(mixed $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return 'N';
        }
        if (in_array(strtoupper($raw), ['Y', 'YES', 'N', 'NO'], true)) {
            return strtoupper($raw)[0] === 'Y' ? 'Y' : 'N';
        }
        if (ctype_digit($raw)) {
            $enum = \CIBlockPropertyEnum::GetByID((int)$raw);
            if (is_array($enum)) {
                $xml = strtoupper(trim((string)($enum['XML_ID'] ?? $enum['VALUE'] ?? '')));

                return $xml === 'Y' ? 'Y' : 'N';
            }
        }

        return 'N';
    }

    /** @param array<string, mixed> $prop */
    private function listFlagFromProperty(array $prop): string
    {
        foreach (['VALUE_XML_ID', 'VALUE_ENUM', 'VALUE_ENUM_ID', 'VALUE'] as $key) {
            if (!isset($prop[$key]) || $prop[$key] === '' || $prop[$key] === null) {
                continue;
            }
            return $this->normalizeListFlag($prop[$key]);
        }

        return 'N';
    }

    private function normalizeCompareValue(string $code, mixed $value): string
    {
        if (in_array($code, self::LIST_FLAGS, true)) {
            return $this->normalizeListFlag($value);
        }
        if ($value === null) {
            return '';
        }

        return trim((string)$value);
    }

    private function setMissingFlag(int $elementId, string $flag): void
    {
        $enumId = $this->resolveListEnumId('MISSING_AT_SOURCE', $flag);
        if ($enumId <= 0) {
            return;
        }
        \CIBlockElement::SetPropertyValuesEx($elementId, $this->iblockId, [
            'MISSING_AT_SOURCE' => $enumId,
        ]);
    }

    /** @param array<string, scalar|null> $props @return array<string, mixed> */
    private function normalizeProps(array $props): array
    {
        $out = [];
        foreach ($props as $code => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array($code, self::LIST_FLAGS, true)) {
                $enumId = $this->resolveListEnumId($code, (string)$value);
                if ($enumId > 0) {
                    $out[$code] = $enumId;
                }
                continue;
            }
            $out[$code] = $value;
        }

        return $out;
    }

    private function resolveListEnumId(string $propCode, string $flag): int
    {
        $xml = $this->normalizeListFlag($flag) === 'Y' ? 'Y' : 'N';
        if (isset($this->listEnumIds[$propCode][$xml])) {
            return $this->listEnumIds[$propCode][$xml];
        }

        $prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $this->iblockId, 'CODE' => $propCode])->Fetch();
        if (!$prop) {
            return 0;
        }
        $enum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => (int)$prop['ID'], 'XML_ID' => $xml])->Fetch();
        $id = $enum ? (int)$enum['ID'] : 0;
        $this->listEnumIds[$propCode][$xml] = $id;

        return $id;
    }
}
