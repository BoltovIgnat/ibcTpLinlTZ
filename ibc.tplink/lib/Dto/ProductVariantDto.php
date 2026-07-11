<?php

namespace Ibc\Tplink\Dto;

final class ProductVariantDto
{
    /** @param array<string, scalar|null> $fields */
    public function __construct(
        public readonly string $fullArticle,
        public readonly string $article,
        public readonly string $name,
        public readonly string $sourceUrl,
        public readonly bool $needsReview,
        public readonly array $fields,
        public readonly ?string $fallbackKey = null,
        public readonly ?string $reviewReason = null,
    ) {
    }
}
