<?php

namespace Ibc\Tplink\Service;

use Ibc\Tplink\Dto\ProductVariantDto;
use Ibc\Tplink\Dto\SourceCardDto;

final class FullArticleResolver
{
    public function __construct(
        private readonly ProductPageParser $productParser,
        private readonly SupportPageParser $supportParser,
        private readonly SourceFetcher $fetcher,
    ) {
    }

    /** @return list<ProductVariantDto> */
    public function resolve(SourceCardDto $card): array
    {
        $product = $this->productParser->parse($card->sourceUrl, $card->name);
        $article = (string)($product['ARTICLE'] ?? $card->name);
        $name = (string)($product['NAME'] ?? $card->name);

        $pairs = [];
        try {
            foreach ($this->supportParser->versionPageUrls($card->slug) as $versionUrl) {
                $html = $this->fetcher->get($versionUrl);
                foreach ($this->supportParser->parseRegionalHardware($html, $article) as $pair) {
                    $key = ($pair['region'] ?? '') . '|' . $pair['hw'];
                    $pairs[$key] = $pair;
                }
            }
        } catch (\Throwable $e) {
            return [$this->fallbackVariant($card, $product, 'support_page_unavailable: ' . $e->getMessage())];
        }

        if ($pairs === []) {
            return [$this->fallbackVariant($card, $product, 'no_hw_region_pairs_found')];
        }

        $variants = [];
        foreach ($pairs as $pair) {
            $fullArticle = $this->buildFullArticle($article, $pair['region'], $pair['hw']);
            $needsReview = $pair['region'] === null;
            $fields = $this->productFields($product);
            $fields['NEEDS_REVIEW'] = $needsReview ? 'Y' : 'N';

            $variants[$fullArticle] = new ProductVariantDto(
                fullArticle: $fullArticle,
                article: $article,
                name: $name,
                sourceUrl: $card->sourceUrl,
                needsReview: $needsReview,
                fields: $fields,
                fallbackKey: null,
                reviewReason: $needsReview ? 'region_not_found_in_firmware' : null,
            );
        }

        return array_values($variants);
    }

    /** @param array<string, scalar|null> $product */
    private function fallbackVariant(SourceCardDto $card, array $product, string $reason): ProductVariantDto
    {
        $article = (string)($product['ARTICLE'] ?? $card->name);
        $name = (string)($product['NAME'] ?? $card->name);
        $fields = $this->productFields($product);
        $fields['NEEDS_REVIEW'] = 'Y';

        return new ProductVariantDto(
            fullArticle: $name . '|' . $card->sourceUrl,
            article: $article,
            name: $name,
            sourceUrl: $card->sourceUrl,
            needsReview: true,
            fields: $fields,
            fallbackKey: $name . '|' . $card->sourceUrl,
            reviewReason: $reason,
        );
    }

    private function buildFullArticle(string $article, ?string $region, string $hw): string
    {
        if ($region) {
            return sprintf('%s(%s) %s', $article, $region, $hw);
        }

        return sprintf('%s %s', $article, $hw);
    }

    /** @param array<string, scalar|null> $product @return array<string, scalar|null> */
    private function productFields(array $product): array
    {
        return [
            'ARTICLE' => $product['ARTICLE'] ?? null,
            'CATEGORY' => $product['CATEGORY'] ?? 'Роутеры Wi-Fi',
            'WIFI_STANDARD' => $product['WIFI_STANDARD'] ?? null,
            'WIFI_SPEED' => $product['WIFI_SPEED'] ?? null,
            'WAN_SPEED' => $product['WAN_SPEED'] ?? null,
            'LAN_PORTS' => $product['LAN_PORTS'] ?? null,
        ];
    }
}
