<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell\Ports;

use GameOfLife\Classic\Core\Cells;
use GameOfLife\Classic\Core\Position;

/**
 * HTML/CSS Display port for the WebServerOnly shell.
 * Renders cells as a clickable grid of <a> tags in a CSS Grid.
 */
class DisplayPort implements Port
{
    private Cells $cells;
    private string $status = '';
    private bool $statusVisible = false;
    private bool $clocked = false;
    private string $selectedPattern = 'blinker';
    private int $gridWidth = 160;
    private int $gridHeight = 70;
    private int $cellSize = 10;
    private ?PatternStorePort $patternStore = null;

    public function __construct(?PatternStorePort $patternStore = null)
    {
        $this->patternStore = $patternStore;
    }

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

    public function setStatusVisible(bool $visible): void
    {
        $this->statusVisible = $visible;
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
            ? '<meta http-equiv="refresh" content="2;url=' . htmlspecialchars($this->buildCleanUrl()) . '">'
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
        // Compute viewport bounds from grid dimensions
        $startX = -$this->gridWidth / 2;
        $startY = -$this->gridHeight / 2;
        $endX = -$startX - 1;
        $endY = -$startY - 1;

        $html = '<div class="grid" style="grid-template-columns: repeat(' . $this->gridWidth . ', ' . $this->cellSize . 'px);">';

        for ($y = $startY; $y <= $endY; $y++) {
            for ($x = $startX; $x <= $endX; $x++) {
                $isAlive = $this->cells->contains(new Position($x, $y));
                $class = $isAlive ? 'cell alive' : 'cell';
                $url = $this->buildUrl('stamp', ['pattern' => $this->selectedPattern, 'x' => $x, 'y' => $y]);

                $html .= '<a href="' . htmlspecialchars($url) . '" class="' . $class . '"></a>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    private function buildUrl(string $action, ?array $extra = null): string
    {
        $params = ['action' => $action];
        if ($this->clocked) {
            $params['clocked'] = '1';
        }
        if ($this->selectedPattern !== 'blinker') {
            $params['pattern'] = $this->selectedPattern;
        }
        if ($extra) {
            $params = array_merge($params, $extra);
        }
        return '?' . http_build_query($params);
    }

    private function buildCleanUrl(): string
    {
        $params = [];
        if ($this->clocked) {
            $params['clocked'] = '1';
        }
        if ($this->selectedPattern !== 'blinker') {
            $params['pattern'] = $this->selectedPattern;
        }
        return '?' . http_build_query($params);
    }

    public function setPatternStore(?PatternStorePort $patternStore): void
    {
        $this->patternStore = $patternStore;
    }

    private function renderPatternSelector(): string
    {
        // Get pattern list from PatternStore
        $patterns = $this->patternStore ? $this->patternStore->list() : [];
        
        // Sort patterns alphabetically
        sort($patterns);

        $options = '';
        foreach ($patterns as $pattern) {
            $selected = $pattern === $this->selectedPattern ? 'selected' : '';
            $options .= '<option value="' . htmlspecialchars($pattern) . '" ' . $selected . '>' .
                        htmlspecialchars($pattern) . '</option>';
        }

        $clockedField = $this->clocked ? '<input type="hidden" name="clocked" value="1">' : '';
        return '<form method="get"><select name="pattern">' .
               $options . '</select>' .
               '<input type="hidden" name="action" value="select_pattern">' .
               $clockedField .
               '<button type="submit">Select Pattern</button></form>';
    }

    private function renderControls(): string
    {
        return '<a href="' . $this->buildUrl('step') . '">Step</a>' .
               '<a href="' . $this->buildUrl('toggle_status') . '">Toggle Status</a>' .
               '<a href="' . $this->buildUrl($this->clocked ? 'clock_off' : 'clock_on') . '">' .
               ($this->clocked ? 'Clock Off' : 'Clock On') . '</a>' .
               '<a href="' . $this->buildUrl('reset') . '">Reset</a>';
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
    max-width: 1700px;
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
    gap: 0;
    margin: 0 auto;
}
.cell {
    display: block;
    width: 10px;
    height: 10px;
    border: 0;
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
