<?php

declare(strict_types=1);

namespace Karhu\Auth;

/**
 * Role-based access control — queries roles via UserRepositoryInterface.
 *
 * No SQL in this class. All user/role reads go through the injected
 * repository. Port of chukwu Core_ACL and Peopsquik Core_ACL, but
 * decoupled from raw database access.
 */
final class Rbac
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /** Check if a user has a specific role. */
    public function hasRole(string $username, string $role): bool
    {
        return in_array($role, $this->users->rolesFor($username), true);
    }

    /**
     * Check if a user has any of the given roles.
     * @param list<string> $roles
     */
    public function hasAnyRole(string $username, array $roles): bool
    {
        $userRoles = $this->users->rolesFor($username);
        return count(array_intersect($roles, $userRoles)) > 0;
    }

    /**
     * Authenticate a user by username and password.
     *
     * @return array{username: string, roles: list<string>}|null Null on failure
     */
    public function authenticate(string $username, string $password, PasswordHasher $hasher): ?array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            return null;
        }

        if (!$hasher->verify($password, $user['password_hash'])) {
            return null;
        }

        return [
            'username' => $user['username'],
            'roles' => $user['roles'],
        ];
    }
}
