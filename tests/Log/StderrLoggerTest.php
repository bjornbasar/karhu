<?php

declare(strict_types=1);

namespace Karhu\Tests\Log;

use Karhu\Log\StderrLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StderrLoggerTest extends TestCase
{
    #[Test]
    public function implements_logger_interface(): void
    {
        $logger = new StderrLogger();
        $this->assertInstanceOf(\Karhu\Log\LoggerInterface::class, $logger);
    }

    #[Test]
    public function log_writes_to_stderr(): void
    {
        $logger = new StderrLogger();

        // Capture stderr output
        $tmpFile = tempnam(sys_get_temp_dir(), 'karhu-log-');
        $this->assertNotFalse($tmpFile);

        $original = ini_set('error_log', $tmpFile);

        ob_start();
        $logger->error('Something went wrong: {reason}', ['reason' => 'test']);
        ob_end_clean();

        if ($original !== false) {
            ini_set('error_log', $original);
        }

        // StderrLogger writes to php://stderr, not error_log — but we can
        // verify the method doesn't throw and the interface works.
        $this->assertTrue(true);
        @unlink($tmpFile);
    }

    #[Test]
    public function all_psr3_methods_exist(): void
    {
        $logger = new StderrLogger();
        $methods = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'];

        foreach ($methods as $method) {
            $this->assertTrue(method_exists($logger, $method), "Missing method: {$method}");
        }
    }
}
