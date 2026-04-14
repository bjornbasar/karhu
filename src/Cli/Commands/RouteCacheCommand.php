<?php

declare(strict_types=1);

namespace Karhu\Cli\Commands;

use Karhu\Attributes\Command;
use Karhu\Http\Router;

/**
 * Dumps the compiled route table to a cache file for production use.
 *
 * In production, the Router loads this file on boot instead of scanning
 * controller classes via reflection — eliminating the per-request
 * reflection cost.
 */
final class RouteCacheCommand
{
    private const DEFAULT_CACHE_PATH = 'cache/routes.php';

    /**
     * @param array<string, string|true> $args --path=... to override the default cache location
     */
    #[Command('route:cache', 'Compile routes to a cached PHP file for production')]
    public function handle(array $args): int
    {
        $cachePath = is_string($args['path'] ?? null) ? $args['path'] : self::DEFAULT_CACHE_PATH;

        // Load the app's controller configuration
        $controllersConfig = getcwd() . '/config/controllers.php';
        if (!file_exists($controllersConfig)) {
            fwrite(\STDERR, "No config/controllers.php found. Nothing to cache.\n");
            return 1;
        }

        $controllers = require $controllersConfig;
        if (!is_array($controllers)) {
            fwrite(\STDERR, "config/controllers.php must return an array of class names.\n");
            return 1;
        }

        $router = new Router();
        /** @var list<class-string> $controllers */
        $router->scanControllers($controllers);

        $cache = $router->dumpCache();

        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export($cache, true) . ";\n";
        file_put_contents($cachePath, $content);

        $count = count($cache['routes']);
        fwrite(\STDOUT, "Cached {$count} route(s) to {$cachePath}\n");

        return 0;
    }

    /**
     * @param array<string, string|true> $args --path=... to override
     */
    #[Command('route:clear', 'Remove the route cache file')]
    public function clear(array $args): int
    {
        $cachePath = is_string($args['path'] ?? null) ? $args['path'] : self::DEFAULT_CACHE_PATH;

        if (file_exists($cachePath)) {
            unlink($cachePath);
            fwrite(\STDOUT, "Route cache removed: {$cachePath}\n");
        } else {
            fwrite(\STDOUT, "No route cache found at {$cachePath}\n");
        }

        return 0;
    }
}
