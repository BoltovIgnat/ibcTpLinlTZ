<?php

namespace Ibc\Tplink\Dto;

final class SyncResultDto
{
    public function __construct(
        public readonly string $status,
        public readonly ?int $elementId = null,
        public readonly array $changedFields = [],
    ) {
    }
}
