<?php

declare(strict_types=1);

namespace Karhu\Tests\Config;

use Karhu\Config\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    #[Test]
    public function constructor_accepts_items(): void
    {
        $cfg = new Config(['app' => ['name' => 'karhu']]);
        $this->assertSame('karhu', $cfg->get('app.name'));
    }

    #[Test]
    public function dot_notation_traverses_nested(): void
    {
        $cfg = new Config([
            'database' => [
                'connections' => [
                    'default' => ['host' => 'localhost'],
                ],
            ],
        ]);
        $this->assertSame('localhost', $cfg->get('database.connections.default.host'));
    }

    #[Test]
    public function returns_default_for_missing_key(): void
    {
        $cfg = new Config();
        $this->assertSame('fallback', $cfg->get('missing.key', 'fallback'));
        $this->assertNull($cfg->get('nope'));
    }

    #[Test]
    public function env_override_wins(): void
    {
        putenv('APP_NAME=from-env');
        $cfg = new Config(['app' => ['name' => 'from-file']]);

        $this->assertSame('from-env', $cfg->get('app.name'));

        putenv('APP_NAME'); // cleanup
    }

    #[Test]
    public function has_returns_correct_state(): void
    {
        $cfg = new Config(['a' => ['b' => 'c']]);
        $this->assertTrue($cfg->has('a.b'));
        $this->assertTrue($cfg->has('a'));
        $this->assertFalse($cfg->has('a.x'));
        $this->assertFalse($cfg->has('z'));
    }

    #[Test]
    public function set_creates_nested_keys(): void
    {
        $cfg = new Config();
        $cfg->set('x.y.z', 'deep');
        $this->assertSame('deep', $cfg->get('x.y.z'));
    }

    #[Test]
    public function all_returns_full_array(): void
    {
        $data = ['a' => 1, 'b' => ['c' => 2]];
        $cfg = new Config($data);
        $this->assertSame($data, $cfg->all());
    }

    #[Test]
    public function load_dir_reads_php_files(): void
    {
        $dir = sys_get_temp_dir() . '/karhu-config-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/database.php', "<?php\nreturn ['host' => '127.0.0.1', 'port' => 5432];\n");
        file_put_contents($dir . '/app.php', "<?php\nreturn ['name' => 'karhu', 'debug' => false];\n");

        $cfg = new Config();
        $cfg->loadDir($dir);

        $this->assertSame('127.0.0.1', $cfg->get('database.host'));
        $this->assertSame(5432, $cfg->get('database.port'));
        $this->assertSame('karhu', $cfg->get('app.name'));
        $this->assertFalse($cfg->get('app.debug'));

        // Cleanup
        unlink($dir . '/database.php');
        unlink($dir . '/app.php');
        rmdir($dir);
    }
}
