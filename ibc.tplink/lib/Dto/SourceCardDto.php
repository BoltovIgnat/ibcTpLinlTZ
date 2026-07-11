<?php

namespace Ibc\Tplink\Dto;

final class SourceCardDto
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $sourceUrl,
    ) {
    }
}
