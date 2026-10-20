<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Pattern-related functions: translation and status messages.
 */
final class Pattern
{
    /**
     * Translate a set of positions by a reference position.
     * Adds the reference position's coordinates to each position.
     *
     * This is used when adding patterns at specific locations in the grid.
     *
     * @param Cells $points The pattern cells to translate
     * @param Position $reference The reference position to add to each point
     * @return Cells The translated cells
     */
    public static function translate(Cells $points, Position $reference): Cells
    {
        return $points->map(function(Position $q) use ($reference): Position {
            return Position::create(
                $q->x + $reference->x,
                $q->y + $reference->y
            );
        });
    }

    /**
     * Get the status message for the current cell count.
     * Normative text from spec.
     *
     * Grammar:
     * - 0 cells: "No cell is alive"
     * - 1 cell: "1 cell is alive"
     * - N cells (N > 1): "N cells are alive"
     *
     * @param Cells $cells The cells to get the status for
     * @return string The status message
     */
    public static function statusMessage(Cells $cells): string
    {
        $n = $cells->count();
        
        if ($n === 0) {
            return 'No cell is alive';
        } elseif ($n === 1) {
            return '1 cell is alive';
        } else {
            return sprintf('%d cells are alive', $n);
        }
    }
}
