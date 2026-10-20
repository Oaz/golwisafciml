<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell\Ports;

use GameOfLife\Classic\Core\Cells;
use GameOfLife\Classic\Core\Position;

/**
 * File-based PatternStore port for the WebServerOnly shell.
 * Stores patterns as JSON files and provides retrieval by name.
 */
class PatternStorePort implements Port
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
            'blinker' => [
                ['x' => -1, 'y' => 0],
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
            ],
            'toad' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => -1, 'y' => 1],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
            ],
            'beacon' => [
                ['x' => -1, 'y' => -1],
                ['x' => 0, 'y' => -1],
                ['x' => -1, 'y' => 0],
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 1],
                ['x' => 2, 'y' => 1],
                ['x' => 1, 'y' => 2],
                ['x' => 2, 'y' => 2],
            ],
            'pentadecathlon' => [
                ['x' => -2, 'y' => -1],
                ['x' => 3, 'y' => -1],
                ['x' => -4, 'y' => 0],
                ['x' => -3, 'y' => 0],
                ['x' => -1, 'y' => 0],
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 4, 'y' => 0],
                ['x' => 5, 'y' => 0],
                ['x' => -2, 'y' => 1],
                ['x' => 3, 'y' => 1],
            ],
            'block' => [
                ['x' => 0, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
            ],
            'beehive' => [
                ['x' => 0, 'y' => -1],
                ['x' => 1, 'y' => -1],
                ['x' => -1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
            ],
            'loaf' => [
                ['x' => 0, 'y' => -1],
                ['x' => 1, 'y' => -1],
                ['x' => -1, 'y' => 0],
                ['x' => 2, 'y' => 0],
                ['x' => 0, 'y' => 1],
                ['x' => 2, 'y' => 1],
                ['x' => 1, 'y' => 2],
            ],
            'boat' => [
                ['x' => -1, 'y' => -1],
                ['x' => 0, 'y' => -1],
                ['x' => -1, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 0, 'y' => 1],
            ],
            'tub' => [
                ['x' => 0, 'y' => -1],
                ['x' => -1, 'y' => 0],
                ['x' => 1, 'y' => 0],
                ['x' => 0, 'y' => 1],
            ],
            'glider' => [
                ['x' => 0, 'y' => -1],
                ['x' => 1, 'y' => 0],
                ['x' => -1, 'y' => 1],
                ['x' => 0, 'y' => 1],
                ['x' => 1, 'y' => 1],
            ],
            'lwss' => [
                ['x' => -1, 'y' => -1],
                ['x' => 2, 'y' => -1],
                ['x' => -2, 'y' => 0],
                ['x' => -2, 'y' => 1],
                ['x' => 2, 'y' => 1],
                ['x' => -2, 'y' => 2],
                ['x' => -1, 'y' => 2],
                ['x' => 0, 'y' => 2],
                ['x' => 1, 'y' => 2],
            ],
            'mwss' => [
                ['x' => 1, 'y' => -2],
                ['x' => -1, 'y' => -1],
                ['x' => 3, 'y' => -1],
                ['x' => -2, 'y' => 0],
                ['x' => -2, 'y' => 1],
                ['x' => 3, 'y' => 1],
                ['x' => -2, 'y' => 2],
                ['x' => -1, 'y' => 2],
                ['x' => 0, 'y' => 2],
                ['x' => 1, 'y' => 2],
                ['x' => 2, 'y' => 2],
            ],
            'hwss' => [
                ['x' => 0, 'y' => -2],
                ['x' => 1, 'y' => -2],
                ['x' => -2, 'y' => -1],
                ['x' => 3, 'y' => -1],
                ['x' => -3, 'y' => 0],
                ['x' => -3, 'y' => 1],
                ['x' => 3, 'y' => 1],
                ['x' => -3, 'y' => 2],
                ['x' => -2, 'y' => 2],
                ['x' => -1, 'y' => 2],
                ['x' => 0, 'y' => 2],
                ['x' => 1, 'y' => 2],
                ['x' => 2, 'y' => 2],
            ],
            'rpentomino' => [
                ['x' => 0, 'y' => -1],
                ['x' => 1, 'y' => -1],
                ['x' => -1, 'y' => 0],
                ['x' => 0, 'y' => 0],
                ['x' => 0, 'y' => 1],
            ],
            'diehard' => [
                ['x' => 3, 'y' => -1],
                ['x' => -3, 'y' => 0],
                ['x' => -2, 'y' => 0],
                ['x' => -2, 'y' => 1],
                ['x' => 2, 'y' => 1],
                ['x' => 3, 'y' => 1],
                ['x' => 4, 'y' => 1],
            ],
            'acorn' => [
                ['x' => -2, 'y' => -1],
                ['x' => 0, 'y' => 0],
                ['x' => -3, 'y' => 1],
                ['x' => -2, 'y' => 1],
                ['x' => 1, 'y' => 1],
                ['x' => 2, 'y' => 1],
                ['x' => 3, 'y' => 1],
            ],
            'pulsar' => [
                ['x' => -4, 'y' => -6],
                ['x' => -3, 'y' => -6],
                ['x' => -2, 'y' => -6],
                ['x' => 2, 'y' => -6],
                ['x' => 3, 'y' => -6],
                ['x' => 4, 'y' => -6],
                ['x' => -6, 'y' => -4],
                ['x' => -1, 'y' => -4],
                ['x' => 1, 'y' => -4],
                ['x' => 6, 'y' => -4],
                ['x' => -6, 'y' => -3],
                ['x' => -1, 'y' => -3],
                ['x' => 1, 'y' => -3],
                ['x' => 6, 'y' => -3],
                ['x' => -6, 'y' => -2],
                ['x' => -1, 'y' => -2],
                ['x' => 1, 'y' => -2],
                ['x' => 6, 'y' => -2],
                ['x' => -4, 'y' => -1],
                ['x' => -3, 'y' => -1],
                ['x' => -2, 'y' => -1],
                ['x' => 2, 'y' => -1],
                ['x' => 3, 'y' => -1],
                ['x' => 4, 'y' => -1],
                ['x' => -4, 'y' => 1],
                ['x' => -3, 'y' => 1],
                ['x' => -2, 'y' => 1],
                ['x' => 2, 'y' => 1],
                ['x' => 3, 'y' => 1],
                ['x' => 4, 'y' => 1],
                ['x' => -6, 'y' => 2],
                ['x' => -1, 'y' => 2],
                ['x' => 1, 'y' => 2],
                ['x' => 6, 'y' => 2],
                ['x' => -6, 'y' => 3],
                ['x' => -1, 'y' => 3],
                ['x' => 1, 'y' => 3],
                ['x' => 6, 'y' => 3],
                ['x' => -6, 'y' => 4],
                ['x' => -1, 'y' => 4],
                ['x' => 1, 'y' => 4],
                ['x' => 6, 'y' => 4],
                ['x' => -4, 'y' => 6],
                ['x' => -3, 'y' => 6],
                ['x' => -2, 'y' => 6],
                ['x' => 2, 'y' => 6],
                ['x' => 3, 'y' => 6],
                ['x' => 4, 'y' => 6],
            ],
            'gosperGun' => [
                ['x' => 7, 'y' => -4],
                ['x' => 5, 'y' => -3],
                ['x' => 7, 'y' => -3],
                ['x' => -5, 'y' => -2],
                ['x' => -4, 'y' => -2],
                ['x' => 3, 'y' => -2],
                ['x' => 4, 'y' => -2],
                ['x' => 17, 'y' => -2],
                ['x' => 18, 'y' => -2],
                ['x' => -6, 'y' => -1],
                ['x' => -2, 'y' => -1],
                ['x' => 3, 'y' => -1],
                ['x' => 4, 'y' => -1],
                ['x' => 17, 'y' => -1],
                ['x' => 18, 'y' => -1],
                ['x' => -17, 'y' => 0],
                ['x' => -16, 'y' => 0],
                ['x' => -7, 'y' => 0],
                ['x' => -1, 'y' => 0],
                ['x' => 3, 'y' => 0],
                ['x' => 4, 'y' => 0],
                ['x' => -17, 'y' => 1],
                ['x' => -16, 'y' => 1],
                ['x' => -7, 'y' => 1],
                ['x' => -3, 'y' => 1],
                ['x' => -1, 'y' => 1],
                ['x' => 0, 'y' => 1],
                ['x' => 5, 'y' => 1],
                ['x' => 7, 'y' => 1],
                ['x' => -7, 'y' => 2],
                ['x' => -1, 'y' => 2],
                ['x' => 7, 'y' => 2],
                ['x' => -6, 'y' => 3],
                ['x' => -2, 'y' => 3],
                ['x' => -5, 'y' => 4],
                ['x' => -4, 'y' => 4],
            ],
            'simkinGun' => [
                ['x' => -16, 'y' => -10],
                ['x' => -15, 'y' => -10],
                ['x' => -9, 'y' => -10],
                ['x' => -8, 'y' => -10],
                ['x' => -16, 'y' => -9],
                ['x' => -15, 'y' => -9],
                ['x' => -9, 'y' => -9],
                ['x' => -8, 'y' => -9],
                ['x' => -12, 'y' => -7],
                ['x' => -11, 'y' => -7],
                ['x' => -12, 'y' => -6],
                ['x' => -11, 'y' => -6],
                ['x' => 6, 'y' => -1],
                ['x' => 7, 'y' => -1],
                ['x' => 9, 'y' => -1],
                ['x' => 10, 'y' => -1],
                ['x' => 5, 'y' => 0],
                ['x' => 11, 'y' => 0],
                ['x' => 5, 'y' => 1],
                ['x' => 12, 'y' => 1],
                ['x' => 15, 'y' => 1],
                ['x' => 16, 'y' => 1],
                ['x' => 5, 'y' => 2],
                ['x' => 6, 'y' => 2],
                ['x' => 7, 'y' => 2],
                ['x' => 11, 'y' => 2],
                ['x' => 15, 'y' => 2],
                ['x' => 16, 'y' => 2],
                ['x' => 10, 'y' => 3],
                ['x' => 4, 'y' => 7],
                ['x' => 5, 'y' => 7],
                ['x' => 4, 'y' => 8],
                ['x' => 5, 'y' => 9],
                ['x' => 6, 'y' => 9],
                ['x' => 7, 'y' => 9],
                ['x' => 7, 'y' => 10],
            ],
        ];
    }
}
