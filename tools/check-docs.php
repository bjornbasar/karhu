<?php

declare(strict_types=1);

/**
 * check-docs — assert the API reference still matches the code.
 *
 * karhu's docs rotted once already: the README's hello-world example was fatally
 * broken (wrong #[Route] argument order, a static call to an instance method) and
 * nothing noticed, because nothing checked. This is the check.
 *
 * Three assertions:
 *   1. every public method in src/ is documented on its namespace's API page
 *   2. every method documented in an API table still exists in src/
 *   3. every src/ path cited anywhere in docs/ actually exists
 *
 * Deliberately dependency-free — it registers its own PSR-4 autoloader rather than
 * requiring vendor/autoload.php, so it runs in a bare `php:8.3-cli-alpine` with no
 * `composer install` first. That is what lets the docs deploy gate on it cheaply.
 *
 * Usage:  php tools/check-docs.php          (from the repo root)
 * Exit:   0 = clean, 1 = drift found
 */

$root = dirname(__DIR__);

// Minimal PSR-4 autoloader for Karhu\ → src/. Avoids a composer install.
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'Karhu\\')) {
        return;
    }
    $path = $root . '/src/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

/**
 * Which API page owns which namespace. A new namespace under src/ must be added
 * here AND given a page, or the check fails loudly rather than skipping it.
 */
const NAMESPACE_PAGES = [
    'Karhu'             => 'app.md',
    'Karhu\Http'        => 'http.md',
    'Karhu\Container'   => 'container.md',
    'Karhu\Attributes'  => 'attributes.md',
    'Karhu\Middleware'  => 'middleware.md',
    'Karhu\Auth'        => 'auth.md',
    'Karhu\Config'      => 'config.md',
    'Karhu\Cli'         => 'cli.md',
    'Karhu\Cli\Commands' => 'cli.md',
    'Karhu\Error'       => 'error.md',
    'Karhu\Log'         => 'log.md',
];

/**
 * Methods that exist on every class but are never worth a reference row.
 * __construct IS documented (as a signature block), so it is not skipped here.
 */
const SKIP_METHODS = ['__toString', '__destruct', '__clone'];

$errors = [];
$stats = ['classes' => 0, 'methods' => 0, 'pages' => 0, 'paths' => 0];

/**
 * Read a file, or record an error and return null. file_get_contents() returns
 * string|false, and silently treating false as '' would make every assertion
 * below vacuously pass on an unreadable file.
 *
 * @param list<string> $errors
 */
function readOrFail(string $path, string $label, array &$errors): ?string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        $errors[] = "UNREADABLE    {$label} could not be read";
        return null;
    }

    return $contents;
}

// ---------------------------------------------------------------- discover src/
/** @var array<class-string, string> $classes FQCN => relative path */
$classes = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($root . '/src') + 1);
    $fqcn = 'Karhu\\' . str_replace(['/', '.php'], ['\\', ''], $rel);

    if (class_exists($fqcn) || interface_exists($fqcn)) {
        $classes[$fqcn] = 'src/' . $rel;
    }
}

ksort($classes);

// ---------------------------------------------------------------- load API pages
/** @var array<string, string> $pageText */
$pageText = [];
foreach (array_unique(array_values(NAMESPACE_PAGES)) as $page) {
    $path = $root . '/docs/api/' . $page;
    if (!is_file($path)) {
        $errors[] = "MISSING PAGE  docs/api/{$page} does not exist";
        continue;
    }

    $contents = readOrFail($path, "docs/api/{$page}", $errors);
    if ($contents === null) {
        continue;
    }

    $pageText[$page] = $contents;
    $stats['pages']++;
}

// ------------------------------------------- 1. every public method is documented
foreach ($classes as $fqcn => $relPath) {
    $rc = new ReflectionClass($fqcn);
    $namespace = $rc->getNamespaceName();
    $short = $rc->getShortName();
    $stats['classes']++;

    if (!isset(NAMESPACE_PAGES[$namespace])) {
        $errors[] = "UNMAPPED NS   {$namespace} ({$relPath}) has no page in NAMESPACE_PAGES";
        continue;
    }

    $page = NAMESPACE_PAGES[$namespace];
    $text = $pageText[$page] ?? '';

    if ($text === '') {
        continue; // page-missing already reported
    }

    // The class must have its own section, so a whole class cannot silently vanish
    // from the reference while its method names happen to appear elsewhere.
    if (!str_contains($text, "## {$short}") && !str_contains($text, "## `{$short}`")
        && !str_contains($text, "`{$fqcn}`") && !str_contains($text, "# `{$fqcn}`")) {
        $errors[] = "UNDOCUMENTED  class {$fqcn} has no section in docs/api/{$page}";
        continue;
    }

    foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        // Inherited methods are documented on the parent's page, not here.
        if ($method->getDeclaringClass()->getName() !== $fqcn) {
            continue;
        }
        if (in_array($method->getName(), SKIP_METHODS, true)) {
            continue;
        }

        $stats['methods']++;
        $needle = $method->getName() . '(';

        if (!str_contains($text, $needle)) {
            $errors[] = sprintf(
                'UNDOCUMENTED  %s::%s() is not in docs/api/%s',
                $fqcn,
                $method->getName(),
                $page,
            );
        }
    }
}

// ------------------------------------------ 2. documented methods still exist
// Scoped to API table rows (`| \`signature\` | ... |`) so prose references to PHP
// built-ins — password_hash(), preg_match(), getenv() — are not mistaken for
// karhu methods.
foreach ($pageText as $page => $text) {
    // Every class documented on this page, so a row can be checked against any of them.
    $pageClasses = array_keys(array_filter(
        $classes,
        static fn(string $fqcn): bool =>
            (NAMESPACE_PAGES[substr($fqcn, 0, strrpos($fqcn, '\\') ?: 0) ?: 'Karhu'] ?? null) === $page,
        ARRAY_FILTER_USE_KEY,
    ));

    // Rows look like:  | `static fromGlobals()` | `self` | Build from ... |
    preg_match_all('/^\|\s*`(?:static\s+)?([a-zA-Z_][a-zA-Z0-9_]*)\(/m', $text, $matches);

    foreach (array_unique($matches[1]) as $documented) {
        $found = false;
        foreach ($pageClasses as $fqcn) {
            if (method_exists($fqcn, $documented)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $errors[] = sprintf(
                'PHANTOM       docs/api/%s documents %s() — no class on that page has it',
                $page,
                $documented,
            );
        }
    }
}

// ------------------------------------------------ 3. cited src/ paths exist
$docFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/docs'));

foreach ($docFiles as $file) {
    if ($file->getExtension() !== 'md') {
        continue;
    }
    $docRel = substr($file->getPathname(), strlen($root) + 1);
    $text = readOrFail($file->getPathname(), $docRel, $errors);

    if ($text === null) {
        continue;
    }

    // Strip fenced code blocks first. Example stack traces and sample JSON carry
    // invented paths (src/Controllers/BrokenController.php) that are illustrations,
    // not citations — checking them would fail the build for being good examples.
    $prose = preg_replace('/^```.*?^```/ms', '', $text) ?? $text;

    preg_match_all('#\bsrc/[A-Za-z0-9_/]+\.php\b#', $prose, $matches);

    foreach (array_unique($matches[0]) as $cited) {
        $stats['paths']++;
        if (!is_file($root . '/' . $cited)) {
            $errors[] = "BAD PATH      {$docRel} cites {$cited}, which does not exist";
        }
    }
}

// ---------------------------------------------------------------------- report
if ($errors !== []) {
    fwrite(STDERR, "\n✗ docs-check FAILED — " . count($errors) . " problem(s)\n\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  {$error}\n");
    }
    fwrite(STDERR, "\nThe API reference has drifted from src/. Update docs/api/*.md.\n\n");
    exit(1);
}

printf(
    "✓ docs-check passed — %d classes, %d public methods documented across %d pages, %d src/ paths verified\n",
    $stats['classes'],
    $stats['methods'],
    $stats['pages'],
    $stats['paths'],
);
exit(0);
