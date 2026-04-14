<?php

declare(strict_types=1);

namespace Karhu\Log;

/**
 * PSR-3-shape logger interface.
 *
 * Matches the method signatures of psr/log LoggerInterface so that
 * implementations are compatible if the user brings psr/log. karhu
 * itself does not require that package.
 */
interface LoggerInterface
{
    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void;

    /**
     * @param string               $level   One of: emergency, alert, critical, error, warning, notice, info, debug
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void;
}
