<?php

declare(strict_types=1);

namespace Karhu\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Regex
{
    public function __construct(
        public readonly string $pattern,
        public readonly ?string $message = null,
    ) {}
}
