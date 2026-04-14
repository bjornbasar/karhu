<?php

declare(strict_types=1);

namespace Karhu\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class In
{
    /**
     * @param list<string> $values
     */
    public function __construct(
        public readonly array $values,
        public readonly ?string $message = null,
    ) {}
}
