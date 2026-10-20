<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell\Ports;

use GameOfLife\Classic\Core\Position;

/**
 * EventSource port for the WebServerOnly shell.
 * Extracts user actions and parameters from HTTP GET parameters.
 */
final class EventSourcePort implements Port
{
    private array $get;

    /**
     * @param array $get The $_GET superglobal array
     */
    public function __construct(array $get)
    {
        $this->get = $get;
    }

    /**
     * Get the action from the request.
     *
     * @return string|null The action name, or null if no action specified
     */
    public function getAction(): ?string
    {
        return $this->get['action'] ?? null;
    }

    /**
     * Get the reference position from the request (for stamp action).
     *
     * @return Position|null The position from x,y GET parameters, or null if not present/invalid
     */
    public function getReference(): ?Position
    {
        $x = $this->get['x'] ?? null;
        $y = $this->get['y'] ?? null;

        if ($x === null || $y === null) {
            return null;
        }

        // Validate as integers
        if (!is_numeric($x) || !is_numeric($y)) {
            return null;
        }

        return new Position((int)$x, (int)$y);
    }

    /**
     * Get the pattern name from the request.
     *
     * @return string|null The pattern name from GET, or null if not present
     */
    public function getPatternName(): ?string
    {
        return $this->get['pattern'] ?? null;
    }

    /**
     * Check if clocked mode flag is set in the request.
     *
     * @return bool True if clocked=1 in GET parameters
     */
    public function isClocked(): bool
    {
        return ($this->get['clocked'] ?? '0') === '1';
    }
}
