<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell\Ports;

use GameOfLife\Classic\Core\Cells;
use GameOfLife\Classic\Core\Position;

/**
 * File-based PatternStore port for the WebServerOnly shell.
 * Stores patterns as JSON files and provides retrieval by name.
 */
final class PatternStorePort implements Port
{
    private string $patternsDir;

    /**
     * @param string $patternsDir Directory where pattern JSON files are stored
     */
    public function __construct(string $patternsDir)
    {
        $this->patternsDir = rtrim($patternsDir, '/');
        
        // Ensure directory exists
        if (!is_dir($this->patternsDir)) {
            mkdir($this->patternsDir, 0755, true);
        }
    }

    /**
     * Get a pattern's cells by name.
     *
     * @param string $name Pattern name
     * @return Cells The cells of the pattern, or empty Cells if not found
     */
    public function get(string $name): Cells
    {
        $path = $this->getPatternPath($name);
        
        if (!file_exists($path)) {
            return Cells::empty();
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return Cells::empty();
        }

        $data = json_decode($json, true);
        if ($data === null || !isset($data['cells'])) {
            return Cells::empty();
        }

        $positions = [];
        foreach ($data['cells'] as $coord) {
            if (isset($coord['x'], $coord['y'])) {
                $positions[] = new Position((int)$coord['x'], (int)$coord['y']);
            }
        }

        return new Cells(...$positions);
    }

    /**
     * List all available pattern names.
     *
     * @return string[] Sorted array of pattern names
     */
    public function list(): array
    {
        $files = glob($this->patternsDir . '/*.json');
        if ($files === false) {
            return [];
        }

        $names = [];
        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }

            $data = json_decode($json, true);
            if ($data !== null && isset($data['name'])) {
                $names[] = $data['name'];
            } else {
                // Fallback to filename without extension
                $names[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        sort($names);
        return $names;
    }

    /**
     * Seed all 19 catalog patterns from Appendix A to disk.
     * Called once during first request to initialize the pattern catalog.
     */
    public function seedCatalog(): void
    {
        $catalog = $this->getCatalogPatterns();
        
        foreach ($catalog as $name => $coordinates) {
            $path = $this->getPatternPath($name);
            if (!file_exists($path)) {
                $data = [
                    'name' => $name,
                    'cells' => $coordinates
                ];
                file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * Check if all catalog patterns exist on disk.
     *
     * @return bool True if all 19 catalog patterns are seeded
     */
    public function isCatalogSeeded(): bool
    {
        $catalog = $this->getCatalogPatterns();
        
        foreach (array_keys($catalog) as $name) {
            if (!file_exists($this->getPatternPath($name))) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get the path for a pattern file.
     *
     * @param string $name Pattern name
     * @return string Full path to the pattern JSON file
     */
    private function getPatternPath(string $name): string
    {
        return $this->patternsDir . '/' . $name . '.json';
    }

    /**
     * Get the 19 catalog patterns from Appendix A.
     *
     * @return array<string, array<array{x: int, y: int}>> Pattern name to coordinates
     */
    private function getCatalogPatterns(): array
    {
        return [
            'acorn' => [
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 0],
                ['x' => 1, 'y' => 2],
                ['x' => 3, 'y' => 1],
                ['x' => 4, 'y' => 1],
                ['x' => 5, 'y' => 1],
                ['x' => 6, 'y' => 1],
            ],
            'beacon' => [
                ['x' => 0, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 0],
                ['x' => 1, 'y' => 1],
                ['x' => -1, 'y' => -1],
                ['x' => -1, 'y' => -2],
                ['x' => -2, 'y' => -1],
                ['x' => -2, 'y' => -2],
            ],
            'blinker' => [
                ['x' => -1, 'y' => 0],
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
            ],
            'block' => [
                ['x' => 0, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 0],
                ['x' => 1, 'y' => 1],
            ],
            'glider' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 2, 'y' => -1],
                ['x' => 1, 'y' => -2],
            ],
            'gosperGun' => [
                ['x' => 0, 'y' => 4],
                ['x' => 0, 'y' => 5],
                ['x' => 1, 'y' => 4],
                ['x' => 1, 'y' => 5],
                ['x' => 10, 'y' => 4],
                ['x' => 10, 'y' => 5],
                ['x' => 10, 'y' => 6],
                ['x' => 11, 'y' => 3],
                ['x' => 11, 'y' => 7],
                ['x' => 12, 'y' => 2],
                ['x' => 13, 'y' => 2],
                ['x' => 14, 'y' => 2],
                ['x' => 12, 'y' => 8],
                ['x' => 13, 'y' => 8],
                ['x' => 14, 'y' => 8],
                ['x' => 15, 'y' => 5],
                ['x' => 16, 'y' => 3],
                ['x' => 16, 'y' => 7],
                ['x' => 17, 'y' => 5],
                ['x' => 20, 'y' => 4],
                ['x' => 20, 'y' => 5],
                ['x' => 20, 'y' => 6],
                ['x' => 21, 'y' => 3],
                ['x' => 21, 'y' => 7],
                ['x' => 22, 'y' => 2],
                ['x' => 23, 'y' => 2],
                ['x' => 24, 'y' => 2],
                ['x' => 22, 'y' => 8],
                ['x' => 23, 'y' => 8],
                ['x' => 24, 'y' => 8],
            ],
            'hwss' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 3, 'y' => 0],
                ['x' => 4, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 4, 'y' => 1],
                ['x' => 0, 'y' => 2],
                ['x' => 4, 'y' => 2],
                ['x' => 0, 'y' => -1],
                ['x' => 4, 'y' => -1],
            ],
            'lwss' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 3, 'y' => 0],
                ['x' => 4, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 4, 'y' => 1],
                ['x' => 4, 'y' => -1],
                ['x' => 0, 'y' => -2],
            ],
            'mwss' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 3, 'y' => 0],
                ['x' => 4, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 4, 'y' => 1],
                ['x' => 4, 'y' => -1],
                ['x' => 1, 'y' => -2],
            ],
            'pentadecathlon' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => -1, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 0, 'y' => -1],
                ['x' => 1, 'y' => -1],
                ['x' => -1, 'y' => -1],
                ['x' => 2, 'y' => -1],
                ['x' => -2, 'y' => -1],
            ],
            'pulsar' => [
                ['x' => 2, 'y' => 0],
                ['x' => 3, 'y' => 0],
                ['x' => 4, 'y' => 0],
                ['x' => -2, 'y' => 0],
                ['x' => -3, 'y' => 0],
                ['x' => -4, 'y' => 0],
                ['x' => 2, 'y' => 2],
                ['x' => 3, 'y' => 2],
                ['x' => 4, 'y' => 2],
                ['x' => -2, 'y' => 2],
                ['x' => -3, 'y' => 2],
                ['x' => -4, 'y' => 2],
                ['x' => 2, 'y' => -2],
                ['x' => 3, 'y' => -2],
                ['x' => 4, 'y' => -2],
                ['x' => -2, 'y' => -2],
                ['x' => -3, 'y' => -2],
                ['x' => -4, 'y' => -2],
                ['x' => 0, 'y' => 4],
                ['x' => 0, 'y' => 3],
                ['x' => 0, 'y' => -3],
                ['x' => 0, 'y' => -4],
                ['x' => 2, 'y' => 4],
                ['x' => 3, 'y' => 4],
                ['x' => -2, 'y' => 4],
                ['x' => -3, 'y' => 4],
                ['x' => 2, 'y' => -4],
                ['x' => 3, 'y' => -4],
                ['x' => -2, 'y' => -4],
                ['x' => -3, 'y' => -4],
            ],
            'queenBeeShuttle' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
                ['x' => -1, 'y' => 2],
                ['x' => 2, 'y' => 2],
                ['x' => -1, 'y' => 3],
                ['x' => 2, 'y' => 3],
                ['x' => -1, 'y' => -2],
                ['x' => 2, 'y' => -2],
                ['x' => -1, 'y' => -3],
                ['x' => 2, 'y' => -3],
            ],
            'rabbits' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
                ['x' => 2, 'y' => 1],
            ],
            'rpentomino' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => -1, 'y' => 1],
                ['x' => 0, 'y' => 2],
            ],
            'spaceShip' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 3, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 3, 'y' => 1],
                ['x' => 0, 'y' => 2],
                ['x' => 3, 'y' => 2],
                ['x' => 1, 'y' => 3],
            ],
            'toad' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => -1, 'y' => 1],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
            ],
            'tub' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => -1, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 0, 'y' => -1],
            ],
            'pentominoR' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 0, 'y' => -1],
                ['x' => -1, 'y' => 0],
            ],
            'beehive' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => -1, 'y' => 1],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
                ['x' => -1, 'y' => -1],
                ['x' => 0, 'y' => -1],
            ],
        ];
    }
}
