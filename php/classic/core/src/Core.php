<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Core is the immutable state of the Game of Life functional core.
 * Contains only the cells and status visibility flag.
 */
final readonly class Core
{
    public function __construct(
        public Cells $cells,
        public bool $statusVisible
    ) {}

    /**
     * Create the initial core state.
     */
    public static function initial(): self
    {
        return new self(Cells::empty(), false);
    }

    /**
     * Create a new Core with the given cells.
     * Immutability: returns new instance.
     */
    public function withCells(Cells $cells): self
    {
        return new self($cells, $this->statusVisible);
    }

    /**
     * Create a new Core with the given status visibility.
     */
    public function withStatusVisible(bool $statusVisible): self
    {
        return new self($this->cells, $statusVisible);
    }

    /**
     * Get the cells (observer).
     */
    public function cellsOf(): Cells
    {
        return $this->cells;
    }

    /**
     * Get the status visibility (observer).
     */
    public function statusVisibleOf(): bool
    {
        return $this->statusVisible;
    }

    /**
     * Check equality with another Core.
     */
    public function equals(self $other): bool
    {
        return $this->cells->equals($other->cells)
            && $this->statusVisible === $other->statusVisible;
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        return sprintf(
            'Core{cells=%d, statusVisible=%s}',
            $this->cells->count(),
            $this->statusVisible ? 'true' : 'false'
        );
    }
}
