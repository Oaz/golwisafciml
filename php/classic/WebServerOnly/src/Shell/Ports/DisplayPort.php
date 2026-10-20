<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell\Ports;

use GameOfLife\Classic\Core\Cells;
use GameOfLife\Classic\Core\Position;

/**
 * HTML/CSS Display port for the WebServerOnly shell.
 * Renders cells as a clickable grid of <a> tags in a CSS Grid.
 */
final class DisplayPort implements Port
{
    private Cells $cells;
    private string $status = '';
    private bool $statusVisible = false;
    private bool $clocked = false;
    private string $selectedPattern = 'blinker';
    private int $cellSize = 20;
    private int $padding = 10;

    public function render(Cells $cells): void
    {
        $this->cells = $cells;
    }

    public function show(string $message): void
    {
        $this->status = $message;
        $this->statusVisible = true;
    }

    public function hide(): void
    {
        $this->statusVisible = false;
    }

    public function setClocked(bool $clocked): void
    {
        $this->clocked = $clocked;
    }

    public function setSelectedPattern(string $pattern): void
    {
        $this->selectedPattern = $pattern;
    }

    public function getOutput(): string
    {
        return $this->renderHtml();
    }

    private function renderHtml(): string
    {
        $gridHtml = $this->renderGrid();
        $statusHtml = $this->statusVisible
            ? '<div class="status-bar">' . htmlspecialchars($this->status) . '</div>'
            : '';

        $metaRefresh = $this->clocked
            ? '<meta http-equiv="refresh" content="0.1">'
            : '';

        $patternSelector = $this->renderPatternSelector();
        $controls = $this->renderControls();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game of Life - PHP WebServerOnly</title>
    <style>
        {$this->getCss()}
    </style>
    {$metaRefresh}
</head>
<body>
    <div class="container">
        <h1>Conway's Game of Life</h1>
        
        <div class="controls">
            {$patternSelector}
            {$controls}
        </div>
        
        {$statusHtml}
        
        <div class="grid-wrapper">
            {$gridHtml}
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderGrid(): string
    {
        // Calculate grid bounds with padding
        $bounds = $this->calculateBounds($this->cells, $this->padding);
        $minX = $bounds['minX'];
        $maxX = $bounds['maxX'];
        $minY = $bounds['minY'];
        $maxY = $bounds['maxY'];

        $gridWidth = $maxX - $minX + 1;

        $html = '<div class="grid" style="grid-template-columns: repeat(' . $gridWidth . ', ' . $this->cellSize . 'px);">';

        for ($y = $minY; $y <= $maxY; $y++) {
            for ($x = $minX; $x <= $maxX; $x++) {
                $isAlive = $this->cells->contains(new Position($x, $y));
                $class = $isAlive ? 'cell alive' : 'cell';
                $url = '?action=stamp&pattern=' . urlencode($this->selectedPattern) . '&x=' . $x . '&y=' . $y;

                // Preserve clocked state in URL
                if ($this->clocked) {
                    $url .= '&clocked=1';
                }

                $html .= '<a href="' . htmlspecialchars($url) . '" class="' . $class . '"></a>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    private function renderPatternSelector(): string
    {
        // Pattern list - will be populated from PatternStore in RequestHandler
        // For now, include the v1 catalog patterns
        $patterns = [
            'acorn', 'beacon', 'blinker', 'block', 'glider',
            'gosperGun', 'hwss', 'lwss', 'mwss', 'pentadecathlon',
            'pulsar', 'queenBeeShuttle', 'rabbits', 'rpentomino',
            'spaceShip', 'toad', 'tub', 'pentominoR', 'beehive'
        ];

        $options = '';
        foreach ($patterns as $pattern) {
            $selected = $pattern === $this->selectedPattern ? 'selected' : '';
            $options .= '<option value="' . htmlspecialchars($pattern) . '" ' . $selected . '>' .
                        htmlspecialchars($pattern) . '</option>';
        }

        return '<form method="get"><select name="pattern">' .
               $options . '</select><button type="submit">Select Pattern</button></form>';
    }

    private function renderControls(): string
    {
        $clocked = $this->clocked;
        $clockAction = $clocked ? 'clock_off' : 'clock_on';
        $clockText = $clocked ? 'Clock Off' : 'Clock On';

        // Build query string preserving clocked state
        $stepUrl = $clocked ? '?clocked=1&action=tick' : '?action=tick';
        $toggleUrl = $clocked ? '?clocked=1&action=toggle_status' : '?action=toggle_status';
        $clockUrl = $clocked ? '?action=clock_off' : '?action=clock_on';

        return '<a href="' . $stepUrl . '">Step</a>' .
               '<a href="' . $toggleUrl . '">Toggle Status</a>' .
               '<a href="' . $clockUrl . '">' . $clockText . '</a>';
    }

    private function calculateBounds(Cells $cells, int $padding = 10): array
    {
        if ($cells->count() === 0) {
            return [
                'minX' => -$padding,
                'maxX' => $padding,
                'minY' => -$padding,
                'maxY' => $padding
            ];
        }

        $positions = $cells->all();
        $xs = array_map(fn($p) => $p->x, $positions);
        $ys = array_map(fn($p) => $p->y, $positions);

        return [
            'minX' => min($xs) - $padding,
            'maxX' => max($xs) + $padding,
            'minY' => min($ys) - $padding,
            'maxY' => max($ys) + $padding,
        ];
    }

    private function getCss(): string
    {
        return <<<CSS
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    margin: 0;
    padding: 20px;
    background: #f5f5f5;
}
.container {
    max-width: 1200px;
    margin: 0 auto;
}
h1 {
    text-align: center;
    color: #333;
    font-size: 24px;
    margin-bottom: 20px;
}
.controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 20px 0;
    justify-content: center;
}
.controls form {
    display: inline-block;
}
select, button {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
}
button {
    background: #007bff;
    color: white;
    cursor: pointer;
    border: none;
}
button:hover {
    background: #0056b3;
}
.grid-wrapper {
    overflow: auto;
    margin: 20px 0;
    border: 1px solid #ddd;
    padding: 15px;
    background: white;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.grid {
    display: grid;
    gap: 1px;
    margin: 0 auto;
}
.cell {
    display: block;
    width: 20px;
    height: 20px;
    border: 1px solid #eee;
    transition: opacity 0.1s;
}
.cell.alive {
    background: #000;
}
.cell:hover {
    opacity: 0.7;
    cursor: pointer;
}
.status-bar {
    padding: 10px;
    background: #e9ecef;
    text-align: center;
    margin: 10px 0;
    border-radius: 4px;
    font-size: 14px;
}
.controls a {
    display: inline-block;
    padding: 8px 12px;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
}
.controls a:hover {
    background: #5a6268;
    text-decoration: none;
}
CSS;
    }
}
