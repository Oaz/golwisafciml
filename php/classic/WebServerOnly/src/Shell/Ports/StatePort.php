<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell\Ports;

use GameOfLife\Classic\Core\Core;

/**
 * Session-based State port for the WebServerOnly shell.
 * Manages the mutable Core reference and UI state between HTTP requests.
 */
final class StatePort implements Port
{
    private const SESSION_KEY_CORE = 'gol_core';
    private const SESSION_KEY_CLOCKED = 'gol_clocked';
    private const SESSION_KEY_PATTERN = 'gol_selected_pattern';
    private const SESSION_KEY_INITIALIZED = 'gol_initialized';

    public function loadCore(): Core
    {
        if (!isset($_SESSION[self::SESSION_KEY_CORE])) {
            return Core::initial();
        }

        return $_SESSION[self::SESSION_KEY_CORE];
    }

    public function saveCore(Core $core): void
    {
        $_SESSION[self::SESSION_KEY_CORE] = $core;
    }

    public function isClocked(): bool
    {
        return $_SESSION[self::SESSION_KEY_CLOCKED] ?? false;
    }

    public function setClocked(bool $clocked): void
    {
        $_SESSION[self::SESSION_KEY_CLOCKED] = $clocked;
    }

    public function getSelectedPattern(): string
    {
        return $_SESSION[self::SESSION_KEY_PATTERN] ?? 'blinker';
    }

    public function setSelectedPattern(string $pattern): void
    {
        $_SESSION[self::SESSION_KEY_PATTERN] = $pattern;
    }

    /**
     * Check if this is the first request (session not initialized).
     *
     * @return bool True if this is the first request
     */
    public function isFirstRequest(): bool
    {
        return !isset($_SESSION[self::SESSION_KEY_INITIALIZED]);
    }

    /**
     * Mark the session as initialized.
     */
    public function markInitialized(): void
    {
        $_SESSION[self::SESSION_KEY_INITIALIZED] = true;
    }
}
