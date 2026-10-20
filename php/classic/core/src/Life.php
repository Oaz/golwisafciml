<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Life rules implementation for Conway's Game of Life.
 */
final class Life
{
    /**
     * Get the 8 Moore neighbors of a position.
     * Direct computation (faster for single-generation web use).
     *
     * @return Cells The 8 neighboring positions
     */
    public static function neighbors(Position $position): Cells
    {
        $neighbors = [];
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue; // Skip the position itself
                }
                $neighbors[] = Position::create(
                    $position->x + $dx,
                    $position->y + $dy
                );
            }
        }
        return new Cells(...$neighbors);
    }

    /**
     * Life rule: should a cell live given its current state and neighbor count?
     *
     * Rules:
     * - A live cell with 2 or 3 live neighbors survives
     * - A dead cell with exactly 3 live neighbors becomes alive (birth)
     * - All other cells die or remain dead
     */
    public static function shouldLive(bool $isAlive, int $liveNeighborCount): bool
    {
        if ($isAlive) {
            // Survival: 2 or 3 live neighbors
            return $liveNeighborCount === 2 || $liveNeighborCount === 3;
        } else {
            // Birth: exactly 3 live neighbors
            return $liveNeighborCount === 3;
        }
    }

    /**
     * Get all cells and their neighbors (candidate set).
     * This is used for the candidate-set filtering strategy.
     */
    public static function allCellsAndNeighbors(Cells $cells): Cells
    {
        $all = [];
        foreach ($cells->all() as $cell) {
            $all[] = $cell;
            foreach (self::neighbors($cell)->all() as $neighbor) {
                $all[] = $neighbor;
            }
        }
        return new Cells(...$all);
    }

    /**
     * Compute the next generation of cells using candidate-set filtering strategy.
     * This is the v1 strategy as specified.
     *
     * For each position in the candidate set (all cells and their neighbors),
     * determine if it should be alive in the next generation based on the
     * current state and the number of live neighbors.
     *
     * @return Cells The cells in the next generation
     */
    public static function nextGenerationCells(Cells $cells): Cells
    {
        // If no cells, return empty
        if ($cells->isEmpty()) {
            return Cells::empty();
        }

        $candidates = self::allCellsAndNeighbors($cells);
        
        $nextCells = [];
        foreach ($candidates->all() as $position) {
            $isAlive = $cells->contains($position);
            $neighbors = self::neighbors($position);
            
            // Count live neighbors
            $liveNeighborCount = 0;
            foreach ($neighbors->all() as $neighbor) {
                if ($cells->contains($neighbor)) {
                    $liveNeighborCount++;
                }
            }
            
            if (self::shouldLive($isAlive, $liveNeighborCount)) {
                $nextCells[] = $position;
            }
        }
        
        return new Cells(...$nextCells);
    }
}
