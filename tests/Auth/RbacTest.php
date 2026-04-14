<?php

declare(strict_types=1);

namespace Karhu\Tests\Auth;

use Karhu\Auth\PasswordHasher;
use Karhu\Auth\Rbac;
use Karhu\Auth\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** In-memory UserRepository stub for testing. */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, array{username: string, password_hash: string, roles: list<string>}> */
    private array $users = [];

    public function addUser(string $username, string $password, array $roles): void
    {
        $this->users[$username] = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'roles' => $roles,
        ];
    }

    public function findByUsername(string $username): ?array
    {
        return $this->users[$username] ?? null;
    }

    public function rolesFor(string $username): array
    {
        return $this->users[$username]['roles'] ?? [];
    }
}

final class RbacTest extends TestCase
{
    private InMemoryUserRepository $repo;
    private Rbac $rbac;

    protected function setUp(): void
    {
        $this->repo = new InMemoryUserRepository();
        $this->repo->addUser('admin', 'secret', ['admin', 'editor']);
        $this->repo->addUser('viewer', 'pass', ['viewer']);
        $this->rbac = new Rbac($this->repo);
    }

    #[Test]
    public function has_role(): void
    {
        $this->assertTrue($this->rbac->hasRole('admin', 'admin'));
        $this->assertTrue($this->rbac->hasRole('admin', 'editor'));
        $this->assertFalse($this->rbac->hasRole('viewer', 'admin'));
    }

    #[Test]
    public function has_any_role(): void
    {
        $this->assertTrue($this->rbac->hasAnyRole('viewer', ['admin', 'viewer']));
        $this->assertFalse($this->rbac->hasAnyRole('viewer', ['admin', 'editor']));
    }

    #[Test]
    public function authenticate_success(): void
    {
        $hasher = new PasswordHasher();
        $result = $this->rbac->authenticate('admin', 'secret', $hasher);

        $this->assertNotNull($result);
        $this->assertSame('admin', $result['username']);
        $this->assertContains('admin', $result['roles']);
    }

    #[Test]
    public function authenticate_wrong_password(): void
    {
        $hasher = new PasswordHasher();
        $this->assertNull($this->rbac->authenticate('admin', 'wrong', $hasher));
    }

    #[Test]
    public function authenticate_unknown_user(): void
    {
        $hasher = new PasswordHasher();
        $this->assertNull($this->rbac->authenticate('nobody', 'pass', $hasher));
    }
}
