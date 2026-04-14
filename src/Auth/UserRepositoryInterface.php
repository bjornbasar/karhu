<?php

declare(strict_types=1);

namespace Karhu\Auth;

/**
 * Decoupling point for RBAC — the auth system queries users and roles
 * through this interface rather than depending on a specific DB layer.
 *
 * Users implement this with their own storage (PDO, karhu-db, Doctrine,
 * etc). The karhu-db optional package ships a ready-made implementation.
 *
 * @see \Karhu\Auth\Rbac
 * @see \Karhu\Middleware\RequireRole
 */
interface UserRepositoryInterface
{
    /**
     * Find a user by username.
     *
     * @return array{username: string, password_hash: string, roles: list<string>}|null
     */
    public function findByUsername(string $username): ?array;

    /**
     * Get the roles assigned to a username.
     *
     * @return list<string>
     */
    public function rolesFor(string $username): array;
}
