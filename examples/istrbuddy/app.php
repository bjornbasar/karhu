<?php

/**
 * IsTrBuddy — self-contained karhu example app.
 *
 * Run: php -S localhost:8080 -t examples/istrbuddy examples/istrbuddy/app.php
 * Then: curl http://localhost:8080/issues
 *
 * This file demonstrates the full M2 middleware + auth + resource
 * controller stack in a single script — no separate files needed.
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Karhu\App;
use Karhu\Attributes\Required;
use Karhu\Attributes\Route;
use Karhu\Attributes\StringLength;
use Karhu\Auth\PasswordHasher;
use Karhu\Auth\Rbac;
use Karhu\Auth\UserRepositoryInterface;
use Karhu\Http\AbstractResourceController;
use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Http\Validation;
use Karhu\Middleware\Cors;
use Karhu\Middleware\Csrf;
use Karhu\Middleware\RequireRole;
use Karhu\Middleware\Session;

// --- In-memory stores (replace with karhu-db in a real app) ---

/**
 * In-memory user repository for the demo.
 * In production, implement UserRepositoryInterface backed by karhu-db.
 */
final class DemoUserRepository implements UserRepositoryInterface
{
    /** @var array<string, array{username: string, password_hash: string, roles: list<string>}> */
    private array $users = [];

    public function seed(PasswordHasher $hasher): void
    {
        $this->users['admin'] = [
            'username' => 'admin',
            'password_hash' => $hasher->hash('admin123'),
            'roles' => ['admin', 'editor'],
        ];
        $this->users['editor'] = [
            'username' => 'editor',
            'password_hash' => $hasher->hash('editor123'),
            'roles' => ['editor'],
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

/**
 * In-memory issue store.
 */
final class IssueStore
{
    /** @var array<int, array{id: int, title: string, body: string, author: string}> */
    private array $issues = [];
    private int $nextId = 1;

    public function seed(): void
    {
        $this->add('Fix login redirect', 'The login page redirects to the wrong URL after auth.', 'admin');
        $this->add('Add dark mode', 'Users have requested a dark mode toggle in settings.', 'editor');
    }

    /** @return array{id: int, title: string, body: string, author: string} */
    public function add(string $title, string $body, string $author): array
    {
        $issue = ['id' => $this->nextId++, 'title' => $title, 'body' => $body, 'author' => $author];
        $this->issues[$issue['id']] = $issue;
        return $issue;
    }

    /** @return list<array{id: int, title: string, body: string, author: string}> */
    public function all(): array
    {
        return array_values($this->issues);
    }

    /** @return array{id: int, title: string, body: string, author: string}|null */
    public function find(int $id): ?array
    {
        return $this->issues[$id] ?? null;
    }

    public function delete(int $id): bool
    {
        if (!isset($this->issues[$id])) {
            return false;
        }
        unset($this->issues[$id]);
        return true;
    }
}

// --- Validation DTO ---

final class CreateIssueDto
{
    #[Required]
    #[StringLength(min: 3, max: 100)]
    public string $title = '';

    #[Required]
    #[StringLength(min: 10)]
    public string $body = '';
}

// --- Controllers ---

final class AuthController
{
    public function __construct(
        private readonly Rbac $rbac,
        private readonly PasswordHasher $hasher,
    ) {}

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $body = $request->body();
        $username = is_array($body) ? ($body['username'] ?? '') : $request->post('username');
        $password = is_array($body) ? ($body['password'] ?? '') : $request->post('password');

        $user = $this->rbac->authenticate((string) $username, (string) $password, $this->hasher);

        if ($user === null) {
            return (new Response(401))->json(['error' => 'Invalid credentials'], 401);
        }

        Session::set('username', $user['username']);
        Session::set('roles', $user['roles']);
        Session::regenerate();

        return (new Response())->json(['message' => 'Logged in', 'user' => $user]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(): Response
    {
        Session::destroy();
        return (new Response())->json(['message' => 'Logged out']);
    }
}

final class IssueController extends AbstractResourceController
{
    public function __construct(
        private readonly IssueStore $store,
    ) {}

    #[Route('/issues', methods: ['GET'], name: 'issues.index')]
    public function dispatchIndex(Request $request): Response
    {
        return $this->dispatch($request);
    }

    #[Route('/issues/{id}', methods: ['GET'], name: 'issues.show')]
    public function dispatchShow(Request $request): Response
    {
        return $this->dispatch($request);
    }

    #[Route('/issues', methods: ['POST'], name: 'issues.create')]
    public function dispatchCreate(Request $request): Response
    {
        return $this->dispatch($request);
    }

    #[Route('/issues/{id}', methods: ['DELETE'], name: 'issues.delete')]
    public function dispatchDelete(Request $request): Response
    {
        return $this->dispatch($request);
    }

    protected function index(Request $request): Response
    {
        return $this->respond($request, ['issues' => $this->store->all()]);
    }

    protected function show(Request $request, string $id): Response
    {
        $issue = $this->store->find((int) $id);
        if ($issue === null) {
            return (new Response(404))->json(['error' => 'Issue not found'], 404);
        }
        return $this->respond($request, $issue);
    }

    protected function create(Request $request): Response
    {
        $data = is_array($request->body()) ? $request->body() : [];

        $errors = Validation::validate($data, CreateIssueDto::class);
        if ($errors !== []) {
            return (new Response(422))->json(['errors' => $errors], 422);
        }

        $username = Session::get('username', 'anonymous');
        $issue = $this->store->add(
            (string) ($data['title'] ?? ''),
            (string) ($data['body'] ?? ''),
            is_string($username) ? $username : 'anonymous',
        );

        return (new Response())->json(['issue' => $issue], 201);
    }

    protected function delete(Request $request, string $id): Response
    {
        if (!$this->store->delete((int) $id)) {
            return (new Response(404))->json(['error' => 'Issue not found'], 404);
        }
        return new Response(204);
    }
}

// --- Boot the app ---

$hasher = new PasswordHasher();
$userRepo = new DemoUserRepository();
$userRepo->seed($hasher);
$issueStore = new IssueStore();
$issueStore->seed();
$rbac = new Rbac($userRepo);

$app = new App();

// Register services in container
$app->container()->set(PasswordHasher::class, $hasher);
$app->container()->set(DemoUserRepository::class, $userRepo);
$app->container()->set(UserRepositoryInterface::class, $userRepo);
$app->container()->set(IssueStore::class, $issueStore);
$app->container()->set(Rbac::class, $rbac);

// Middleware stack
$app->pipe(new Cors(['origins' => ['*']]));
$app->pipe(new Session());
$app->pipe(new Csrf());

// RBAC: protect issue creation (editor+) and deletion (admin only)
$app->pipe(function (Request $req, callable $next) use ($rbac): Response {
    $path = $req->path();
    $method = $req->method();

    // POST /issues → require editor or admin
    if ($method === 'POST' && $path === '/issues') {
        $mw = RequireRole::for($rbac, ['editor', 'admin']);
        return $mw($req, $next);
    }

    // DELETE /issues/* → require admin
    if ($method === 'DELETE' && str_starts_with($path, '/issues/')) {
        $mw = RequireRole::for($rbac, ['admin']);
        return $mw($req, $next);
    }

    return $next($req);
});

// Scan controllers and run
$app->router()->scanControllers([AuthController::class, IssueController::class]);
$app->run();
