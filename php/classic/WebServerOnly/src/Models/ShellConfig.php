<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Models;

/**
 * Shell configuration value object.
 * Holds all configuration needed for the WebServerOnly shell.
 */
final readonly class ShellConfig
{
    public function __construct(
        public float $tickInterval = 0.5,
        public string $patternsDir = '',
        public bool $debug = false
    ) {}
}
