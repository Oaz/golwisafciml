<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Position is an immutable value object representing a grid coordinate.
 * Implements structural equality and can be used as array keys.
 */
final readonly class Position
{
    public function __construct(
        public int $x,
        public int $y
    ) {}

    /**
     * Create a new Position (factory method for readability).
     */
    public static function create(int $x, int $y): self
    {
        return new self($x, $y);
    }

    /**
     * Structural equality: two positions are equal if their coordinates match.
     */
    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    /**
     * Hash representation for use as array/map keys.
     * Returns a string key unique to this position.
     */
    public function hash(): string
    {
        return sprintf('%d,%d', $this->x, $this->y);
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        return sprintf('(%d, %d)', $this->x, $this->y);
    }
}
