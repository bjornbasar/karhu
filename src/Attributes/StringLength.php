<?php

declare(strict_types=1);

namespace Karhu\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class StringLength
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $message = null,
    ) {}
}
