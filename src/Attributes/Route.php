<?php

declare(strict_types=1);

namespace Karhu\Attributes;

use Attribute;

/**
 * Marks a controller method as a route handler.
 *
 * Usage:
 *   #[Route('/users/{id}', methods: ['GET'], name: 'user.show')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param string        $path    URI pattern, e.g. '/users/{id}'
     * @param list<string>  $methods HTTP methods, e.g. ['GET', 'POST']
     * @param string|null   $name    Optional route name for urlFor()
     */
    public function __construct(
        public readonly string $path,
        public readonly array $methods = ['GET'],
        public readonly ?string $name = null,
    ) {}
}
