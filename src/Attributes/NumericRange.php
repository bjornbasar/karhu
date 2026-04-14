<?php

declare(strict_types=1);

namespace Karhu\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class NumericRange
{
    public function __construct(
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        public readonly ?string $message = null,
    ) {}
}
