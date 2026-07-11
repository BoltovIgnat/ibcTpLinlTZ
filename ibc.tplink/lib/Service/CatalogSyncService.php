<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Ibc\Tplink\Dto\ProductVariantDto;
use Ibc\Tplink\Dto\SyncResultDto;

final class CatalogSyncService
{
    /** @var array<string, int> */
    private array $indexByFull = [];

    /** @var array<string, int> */
    private array $indexByFallback = [];

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

        $elementId = $variant->needsReview
            ? ($this->indexByFallback[$variant->fallbackKey ?? ''] ?? 0)
            : ($this->indexByFull[$variant->fullArticle] ?? 0);

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

    /** @param list<string> $seenArticles */
    public function markMissing(array $seenArticles): array
    {
        $results = [];
        $seen = array_fill_keys($seenArticles, true);

        foreach ($this->indexByFull as $full => $elementId) {
            if (isset($seen[$full])) {
                continue;
            }
            $this->setMissingFlag($elementId, 'Y');
            $results[] = ['article' => $full, 'status' => 'missing', 'element_id' => $elementId];
        }

        foreach ($this->indexByFallback as $fallback => $elementId) {
            if (isset($seen[$fallback])) {
                continue;
            }
            if (isset($this->indexByFull[$fallback])) {
                continue;
            }
            $this->setMissingFlag($elementId, 'Y');
            $results[] = ['article' => $fallback, 'status' => 'missing', 'element_id' => $elementId];
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
            }
            if (($row['PROPERTY_NEEDS_REVIEW_VALUE'] ?? '') === 'Y') {
                $fallback = trim((string)$row['NAME']) . '|' . trim((string)($row['PROPERTY_SOURCE_URL_VALUE'] ?? ''));
                $this->indexByFallback[$fallback] = $id;
                if ($full === '' || str_contains($full, '|')) {
                    $this->indexByFull[$fallback] = $id;
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
        } else {
            $this->indexByFull[$variant->fullArticle] = $elementId;
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
                $current[$code] = is_array($prop['VALUE']) ? implode(', ', $prop['VALUE']) : (string)$prop['VALUE'];
            }
        }

        $changed = [];
        if (($current['NAME'] ?? '') !== $variant->name) {
            $changed[] = 'NAME';
        }
        foreach ($props as $code => $value) {
            $newVal = $value === null ? '' : (string)$value;
            $oldVal = $current[$code] ?? '';
            if ($oldVal !== $newVal) {
                $changed[] = $code;
            }
        }

        return $changed;
    }

    private function setMissingFlag(int $elementId, string $flag): void
    {
        \CIBlockElement::SetPropertyValuesEx($elementId, $this->iblockId, [
            'MISSING_AT_SOURCE' => $flag,
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
            $out[$code] = $value;
        }

        return $out;
    }
}
