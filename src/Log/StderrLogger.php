<?php

declare(strict_types=1);

namespace Karhu\Log;

/**
 * Default logger — writes to stderr with timestamp and level.
 */
final class StderrLogger implements LoggerInterface
{
    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $interpolated = $this->interpolate($message, $context);
        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $interpolated);
        file_put_contents('php://stderr', $line, FILE_APPEND);
    }

    /**
     * Replace {key} placeholders in the message with context values.
     *
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $val) {
            $replacements['{' . $key . '}'] = is_scalar($val) ? (string) $val : json_encode($val);
        }
        return strtr($message, $replacements);
    }
}
