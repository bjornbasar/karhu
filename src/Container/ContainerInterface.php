<?php

declare(strict_types=1);

namespace Karhu\Container;

/**
 * PSR-11-shape container interface.
 *
 * Matches the psr/container ContainerInterface signature; if users
 * bring `psr/container`, karhu's Container implements theirs too.
 */
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @throws ContainerException  Error while retrieving the entry.
     * @throws NotFoundException   No entry was found for this identifier.
     */
    public function get(string $id): mixed;

    /** Returns true if the container can return an entry for the given identifier. */
    public function has(string $id): bool;
}
