<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Cells is an immutable set of Position objects.
 * Provides set semantics: uniqueness, order-insensitive equality.
 * 
 * Note: While we would prefer readonly class, PHP doesn't allow setting
 * readonly properties through variadic constructor with unpacking, so we
 * use private properties and enforce immutability through discipline
 * (no setters, properties set only in constructor).
 */
final class Cells
{
    /** @var array<string, Position> Map from hash to Position */
    private array $positions;

    /**
     * Create Cells from an array of Positions.
     * Duplicates are automatically removed.
     *
     * @param Position[] $positions
     */
    public function __construct(Position ...$positions)
    {
        $this->positions = [];
        foreach ($positions as $position) {
            $this->positions[$position->hash()] = $position;
        }
    }

    /**
     * Create empty Cells.
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Create Cells from an iterable of Positions.
     *
     * @param iterable<Position> $iterable
     */
    public static function fromIterable(iterable $iterable): self
    {
        $positions = [];
        foreach ($iterable as $position) {
            if ($position instanceof Position) {
                $positions[$position->hash()] = $position;
            }
        }
        return new self(...array_values($positions));
    }

    /**
     * Check if a position is in this set.
     */
    public function contains(Position $position): bool
    {
        return isset($this->positions[$position->hash()]);
    }

    /**
     * Get the number of cells.
     */
    public function count(): int
    {
        return count($this->positions);
    }

    /**
     * Check if empty.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Get all positions as an array.
     *
     * @return Position[]
     */
    public function all(): array
    {
        return array_values($this->positions);
    }

    /**
     * Check equality with another Cells (order-insensitive).
     */
    public function equals(self $other): bool
    {
        // If counts differ, not equal
        if ($this->count() !== $other->count()) {
            return false;
        }
        // Check all positions in $this are in $other
        foreach ($this->positions as $hash => $position) {
            if (!isset($other->positions[$hash])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Create a new Cells with an additional position.
     * Immutability: returns new instance.
     */
    public function with(Position $position): self
    {
        $all = $this->all();
        $all[] = $position;
        return new self(...$all);
    }

    /**
     * Create a new Cells with positions removed.
     */
    public function remove(Position $position): self
    {
        $filtered = array_filter(
            $this->all(),
            fn(Position $p) => !$p->equals($position)
        );
        return new self(...$filtered);
    }

    /**
     * Create a new Cells as the union with another Cells.
     */
    public function union(self $other): self
    {
        $merged = array_merge($this->all(), $other->all());
        return new self(...$merged);
    }

    /**
     * Create a new Cells as the intersection with another Cells.
     */
    public function intersection(self $other): self
    {
        $filtered = array_filter(
            $this->all(),
            fn(Position $p) => $other->contains($p)
        );
        return new self(...$filtered);
    }

    /**
     * Map over all positions and return a new Cells.
     *
     * @param callable(Position): Position $callback
     */
    public function map(callable $callback): self
    {
        $mapped = array_map($callback, $this->all());
        return new self(...$mapped);
    }

    /**
     * Filter positions and return a new Cells.
     *
     * @param callable(Position): bool $callback
     */
    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->all(), $callback);
        return new self(...$filtered);
    }

    /**
     * Get as array for iteration.
     *
     * @return Position[]
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        $items = array_map(fn(Position $p) => (string)$p, $this->all());
        return 'Cells{' . implode(', ', $items) . '}';
    }
}
