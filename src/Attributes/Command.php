<?php

declare(strict_types=1);

namespace Karhu\Attributes;

use Attribute;

/**
 * Marks a class method as a CLI command handler.
 *
 * Usage:
 *   #[Command('greet', 'Say hello to someone')]
 *   public function handle(array $args): int { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Command
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
    ) {}
}
