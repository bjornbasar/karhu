<?php

declare(strict_types=1);

namespace Karhu\Config;

/**
 * Typed configuration with env-var override.
 *
 * Merges PHP array files in a config directory with getenv() overrides.
 * Supports dot-notation access: config->get('database.host').
 */
final class Config
{
    /** @var array<string, mixed> Flattened config values */
    private array $items = [];

    /**
     * @param array<string, mixed> $items Pre-loaded config (or empty to loadDir)
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Load all .php files from a directory. Each file should return an
     * array; the filename (without extension) becomes the top-level key.
     *
     * config/database.php returning ['host' => 'localhost']
     * → accessible as config->get('database.host')
     */
    public function loadDir(string $path): void
    {
        $path = rtrim($path, '/');
        $files = glob($path . '/*.php');

        foreach ($files ?: [] as $file) {
            $key = basename($file, '.php');
            $values = require $file;

            if (is_array($values)) {
                $this->items[$key] = $values;
            }
        }
    }

    /**
     * Get a config value using dot notation.
     *
     * First checks for an env-var override: 'database.host' looks for
     * DATABASE_HOST in the environment. If found, the env value wins.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check env override: database.host → DATABASE_HOST
        $envKey = strtoupper(str_replace('.', '_', $key));
        $envVal = getenv($envKey);
        if ($envVal !== false) {
            return $envVal;
        }

        return $this->dotGet($key, $default);
    }

    /**
     * Set a config value using dot notation.
     */
    public function set(string $key, mixed $value): void
    {
        $this->dotSet($key, $value);
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return $this->dotGet($key, $this) !== $this;
    }

    /**
     * Get all config items as a flat array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /** Traverse nested arrays by dot-separated key. */
    private function dotGet(string $key, mixed $default): mixed
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /** Set a value in a nested array by dot-separated key. */
    private function dotSet(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$this->items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}
