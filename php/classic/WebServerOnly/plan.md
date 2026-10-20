# Plan: PHP Classic WebServerOnly (v1) — Imperative Shell for Web Server

**Task:** Implement the imperative shell for Conway's Game of Life, Version 1 (Monochrome) in PHP, running on a web server with HTML/CSS output and meta refresh for clocked mode.

**Location:** `php/classic/WebServerOnly/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

**Owner:** Requested by user

---

## 0. Context and Scope

This plan covers the **imperative shell** for the **classic (v1) monochrome** Game of Life in PHP, designed specifically for web server execution. It consumes the existing functional core from `php/classic/core/`.

### What is IN scope

- Complete PHP implementation of the **v1 imperative shell** as defined in `specs.md` §4
- Web server execution model: one HTTP request = one full program interpretation cycle
- **HTML/CSS only output** — NO JavaScript at all
- **Clocked mode** implemented via HTML `<meta http-equiv="refresh">` tag
- **Input mechanism** using clickable grid with `<a>` tags for cell selection
- **State persistence mechanism** between HTTP calls for maintaining core state (PHP sessions)
- All ports: Display (HTML/CSS), PatternStore (file-based JSON), EventSource (HTTP GET parameters), State (session-based)
- Interpreter loop that processes one program per request
- Event wiring for all user intents: tick, click/stamp, toggle status, select pattern, toggle clock
- Demo scene seeding at first request (13 stamps from Appendix C)
- Pattern catalog initialization (19 patterns from Appendix A)

### What is OUT of scope (deliberately)

- Version 2 (multi-breed) — separate future task
- JavaScript — explicitly forbidden by requirement
- AJAX or any client-side dynamic behavior
- Terminal UI, Desktop GUI, or any other environment
- Functional core — already implemented in `php/classic/core/`
- Test suite for the shell (shell tests would require HTTP testing infrastructure; core tests are in the core package)
- Production deployment configuration (Apache/Nginx/PHP-FPM setup)
- User authentication or multi-user support (handled automatically via PHP sessions)

---

## 1. Decisions (Phase 0 Prerequisites)

### 1.1 External Dependencies (Decided by Human)

| Dependency | Decision | Rationale |
|------------|----------|-----------|
| **PHP Version** | PHP 8.1+ | Required by existing core (uses `readonly` properties, modern type system) |
| **Web Server** | Any PHP-capable web server | Apache, Nginx, PHP built-in server all supported |
| **PHP-FPM** | Optional, supported if available | User requirement: "usable with php-fpm only if needed" — will work with or without it |
| **State Persistence** | PHP native sessions | Most idiomatic PHP approach; stateless alternative would require hidden form fields with full state serialization (rejected as less maintainable) |
| **Pattern Store** | File-based (JSON files) | Simple, portable, no database dependency; patterns stored as JSON files in a `patterns/` directory |
| **CSS Framework** | None (hand-rolled CSS) | Keep it simple; no external CSS libraries; inline or embedded stylesheet |
| **Session Storage** | PHP default (files) | Standard `session.save_handler = files`; can be configured to use other handlers if needed |

### 1.2 Clickable Grid Decision (Amended)

**User requirement:** Handle cell clicks to insert patterns with NO JavaScript.

**Decision:** Use a **clickable grid of `<a>` tags** styled with CSS.

- Each cell in the visible grid is an `<a>` tag linking to `?action=stamp&pattern=NAME&x=X&y=Y`
- User selects pattern via GET form with dropdown + submit button
- When user clicks a cell, browser navigates to the stamp URL
- EventSourcePort parses `x` and `y` from `$_GET` parameters
- Pure HTML/CSS, no JavaScript required

**Alternative considered and rejected:**
- Text inputs: Not user-friendly
- Image map with `ismap`: Requires image generation and coordinate mapping
- Form buttons: Difficult to encode both x and y coordinates without JavaScript

**Rollback plan:** If implementation hits dead end, fall back to text input form for coordinates.

### 1.3 Confirmed Prerequisites

- ✅ `php/classic/core/` exists and is complete (v1 functional core)
- ✅ Core uses Composer with PSR-4 autoloading (`GameOfLife\Classic\Core\`)
- ✅ Core has zero dependencies (matches our shell's preference)
- ✅ Core implements all v1 types: Position, Cells, Core, Program types
- ✅ Core implements all v1 operations: nextGenerationProgram, addPatternProgram, switchStatusVisibilityProgram

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        HTTP Request                               │
│  GET /?action=tick                                              │
│  GET /?action=stamp&pattern=blinker&x=10&y=20                   │
│  GET /?action=select_pattern&pattern=toad                      │
│  GET /?action=toggle_status                                      │
│  GET /?action=clock_on                                           │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                      WebServerOnly Shell                           │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    EventSource Port                           ││
│  │  - Parses $_GET parameters                                     ││
│  │  - Maps to: tick, stamp, select_pattern, toggle_status,     ││
│  │             clock_on, clock_off                               ││
│  └─────────────────────────────────────────────────────────────┘│
│                                    │                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                      State Port                                ││
│  │  - PHP $_SESSION for mutable Core reference                   ││
│  │  - Stores: Core, clocked flag, selected pattern (UI state)    ││
│  │  - Loads core at request start                               ││
│  │  - Saves core at request end                                 ││
│  └─────────────────────────────────────────────────────────────┘│
│                                    │                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                   PatternStore Port                           ││
│  │  - File-based JSON pattern storage                           ││
│  │  - Implements: get(name): Cells, list(): array<string>        ││
│  │  - Seeds 19 catalog patterns on first use                     ││
│  └─────────────────────────────────────────────────────────────┘│
│                                    │                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                      Display Port                              ││
│  │  - HTML/CSS renderer                                          ││
│  │  - Renders cells as styled <a> tags in CSS Grid               ││
│  │  - Shows status message in dedicated area                    ││
│  │  - Generates pattern selector form                            ││
│  │  - Generates control buttons                                  ││
│  │  - Generates meta refresh tag when clocked mode is active    ││
│  └─────────────────────────────────────────────────────────────┘│
│                                    │                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    Interpreter                                 ││
│  │  - Processes one Program per request                          ││
│  │  - Uses visitor pattern to dispatch on Program types          ││
│  │  - Collects all instructions, executes atomically              ││
│  │  - Commits final Core via State port                          ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                      HTML/CSS Response                              │
│  - Complete HTML page with embedded CSS                           │
│  - CSS Grid for cell display                                      │
│  - <a> tags for each cell linking to stamp action                  │
│  - Meta refresh tag (when clocked)                                 │
│  - Form with pattern selector + submit button                    │
│  - Control links for step, toggle status, toggle clock          │
└─────────────────────────────────────────────────────────────────┘
```

### 2.1 Request Lifecycle

```
1. Session Start
   └─ session_start();
   └─ $_SESSION['core'] loaded (or initialized if first request)
   └─ $_SESSION['clocked'] loaded (default: false)
   └─ $_SESSION['selected_pattern'] loaded (default: 'blinker')

2. Event Extraction (GET parameters)
   └─ Parse $_GET['action']
   └─ Parse $_GET['pattern'] (for stamp and select_pattern)
   └─ Parse $_GET['x'], $_GET['y'] (for stamp)
   └─ Map to: tick | stamp | select_pattern | toggle_status | clock_on | clock_off

3. Core Operation
   └─ If action == 'tick': nextGenerationProgram(core)
   └─ If action == 'stamp': addPatternProgram(core, pattern, Position(x,y))
   └─ If action == 'select_pattern': update $_SESSION['selected_pattern']
   └─ If action == 'toggle_status': switchStatusVisibilityProgram(core)
   └─ If action == 'clock_on': set $_SESSION['clocked'] = true
   └─ If action == 'clock_off': set $_SESSION['clocked'] = false
   └─ If no action: render current state (initial load)

4. Interpretation
   └─ Create interpreter with ports
   └─ interpret(program) executes all instructions
   └─ State port saves final Core to $_SESSION['core']

5. Rendering
   └─ Display port renders final Core state as HTML
   └─ Generate clickable grid with <a> tags for each cell
   └─ Generate meta refresh if $_SESSION['clocked'] is true
   └─ Generate pattern selector form
   └─ Generate control links

6. Response
   └─ Output complete HTML page
```

### 2.2 Clocked Mode Implementation

**Mechanism:** HTML `<meta http-equiv="refresh" content="0.1">` tag

- Default interval: 0.1 seconds (100ms) per Appendix F for v1
- When clocked mode is ON (`$_SESSION['clocked'] == true`):
  - Each page response includes meta refresh with 0.1s interval
  - Each page load triggers a `tick` action (nextGenerationProgram)
  - User actions (stamp, etc.) are processed normally
  - Clock continues after user actions (next auto-refresh triggers tick)
- When clocked mode is OFF: no meta refresh tag
- Toggling: user clicks "Toggle Clock" link which sets `action=clock_on` or `action=clock_off`

**Behavior details:**
- Clocked mode is independent of user actions
- Each page load in clocked mode executes ONE tick
- User can stamp patterns while clock is running — the stamp happens, then 0.1s later a tick happens
- This matches the spec's "forever loop" concept: `repeat { interpret(nextGeneration); wait(interval) }`

---

## 3. File and Module Layout

```
php/classic/WebServerOnly/
├── composer.json                 # Composer configuration with core dependency
├── index.php                    # Single entry point - handles all requests
├── config.php                   # Configuration (intervals, paths, etc.)
├── src/
│   ├── Shell/
│   │   ├── Interpreter.php      # Program interpreter with port injection
│   │   ├── Ports/
│   │   │   ├── DisplayPort.php   # HTML/CSS Display port
│   │   │   ├── PatternStorePort.php # File-based PatternStore port
│   │   │   ├── EventSourcePort.php # HTTP GET EventSource port
│   │   │   └── StatePort.php     # Session-based State port
│   │   └── RequestHandler.php   # Main request handling logic
│   └── Models/
│       └── ShellConfig.php       # Shell configuration value object
├── patterns/                    # Pattern catalog storage (JSON files)
│   ├── blinker.json
│   ├── toad.json
│   └── ... (19 patterns)
├── public/                      # Web root
│   └── index.php -> ../index.php (symlink)
└── README.md                    # Shell-specific documentation
```

### 3.1 File Responsibilities

| File | Responsibility | Key Components |
|------|---------------|----------------|
| `index.php` | Entry point | Requires bootstrap, instantiates RequestHandler, outputs HTML |
| `config.php` | Configuration | Defines intervals, paths, debug mode |
| `Shell/Interpreter.php` | Program interpreter | `interpret()` method, visitor pattern for Program types |
| `Shell/Ports/DisplayPort.php` | HTML Display | `render(Cells)`, `show(string)`, `hide()`, `setClocked(bool)`, `getOutput(): string` |
| `Shell/Ports/PatternStorePort.php` | Pattern storage | `get(string): Cells`, `list(): array`, file-based JSON storage |
| `Shell/Ports/EventSourcePort.php` | Event extraction | `getAction(): ?string`, `getReference(): ?Position`, `getPatternName(): ?string`, parses $_GET |
| `Shell/Ports/StatePort.php` | State persistence | `loadCore(): Core`, `saveCore(Core)`, `isClocked(): bool`, `setClocked(bool)`, `getSelectedPattern(): string`, `setSelectedPattern(string)` |
| `Shell/RequestHandler.php` | Request processing | Main handler: parse → dispatch → interpret → render |
| `Models/ShellConfig.php` | Shell config | Value object for shell configuration (interval, paths, etc.) |
| `bootstrap.php` | Initialization | Composer autoload, session start, error handling |
| `patterns/*.json` | Pattern data | JSON files with pattern coordinates |

---

## 4. Public API and Key Types

### 4.1 Port Interfaces

```php
// All ports implement a common Port interface (marker interface)
interface Port {}

// DisplayPort
final readonly class DisplayPort implements Port
{
    public function render(Cells $cells): void;
    public function show(string $message): void;
    public function hide(): void;
    public function setClocked(bool $clocked): void;
    public function setSelectedPattern(string $pattern): void;
    public function getOutput(): string; // Returns complete HTML page
}

// PatternStorePort
final readonly class PatternStorePort implements Port
{
    public function __construct(private string $patternsDir);
    public function get(string $name): Cells;
    public function list(): array; // Returns sorted pattern names
    public function seedCatalog(): void; // Seeds catalog patterns to disk (called once)
    public function isCatalogSeeded(): bool; // Check if all patterns exist
}

// EventSourcePort
final readonly class EventSourcePort implements Port
{
    public function __construct(private array $get);
    public function getAction(): ?string; // tick, stamp, select_pattern, toggle_status, clock_on, clock_off
    public function getReference(): ?Position; // Click coordinates from x,y GET params
    public function getPatternName(): ?string; // Pattern name from GET
}

// StatePort
final readonly class StatePort implements Port
{
    public function loadCore(): Core;
    public function saveCore(Core $core): void;
    public function isClocked(): bool;
    public function setClocked(bool $clocked): void;
    public function getSelectedPattern(): string;
    public function setSelectedPattern(string $pattern): void;
}
```

### 4.2 Interpreter

```php
final readonly class Interpreter
{
    public function __construct(
        private DisplayPort $display,
        private PatternStorePort $patternStore,
        private StatePort $state
    ) {}

    public function interpret(Program $program): void
    {
        // Recursive interpretation using visitor pattern
        // Executes all instructions in order
        // Threads responses for GetPatternCells via Ports
    }
}
```

### 4.3 Request Handler

```php
final readonly class RequestHandler
{
    public function __construct(
        private Interpreter $interpreter,
        private DisplayPort $display,
        private PatternStorePort $patternStore,
        private StatePort $state,
        private EventSourcePort $eventSource,
        private ShellConfig $config
    ) {}

    public function handle(): string
    {
        // 1. Load core and UI state from session
        $core = $this->state->loadCore();
        $clocked = $this->state->isClocked();
        $selectedPattern = $this->state->getSelectedPattern();
        
        // 2. Seed demo scene on first request
        if ($this->isFirstRequest()) {
            $core = $this->seedDemoScene($core);
        }
        
        // 3. Get action from event source
        $action = $this->eventSource->getAction();
        
        // 4. Handle UI state actions (don't build programs)
        if ($action === 'select_pattern') {
            $pattern = $this->eventSource->getPatternName() ?? 'blinker';
            $this->state->setSelectedPattern($pattern);
            $core = $this->state->loadCore(); // Reload in case it was modified
        } elseif ($action === 'clock_on') {
            $this->state->setClocked(true);
            $clocked = true;
        } elseif ($action === 'clock_off') {
            $this->state->setClocked(false);
            $clocked = false;
        }
        
        // 5. Build and interpret program based on action
        if ($action === 'tick' || ($clocked && $action === null)) {
            $program = CoreOperations::nextGenerationProgram($core);
            $this->interpreter->interpret($program);
            $core = $this->state->loadCore(); // Get updated core
        } elseif ($action === 'stamp') {
            $patternName = $this->eventSource->getPatternName() ?? $selectedPattern;
            $reference = $this->eventSource->getReference() ?? Position::origin();
            $program = CoreOperations::addPatternProgram($core, $patternName, $reference);
            $this->interpreter->interpret($program);
            $core = $this->state->loadCore();
        } elseif ($action === 'toggle_status') {
            $program = CoreOperations::switchStatusVisibilityProgram($core);
            $this->interpreter->interpret($program);
            $core = $this->state->loadCore();
        }
        
        // 6. Save final core
        $this->state->saveCore($core);
        
        // 7. Configure display and render
        $this->display->setClocked($clocked);
        $this->display->setSelectedPattern($selectedPattern);
        $this->display->render($core->cellsOf());
        
        if ($core->statusVisibleOf()) {
            $this->display->show(Pattern::statusMessage($core->cellsOf()));
        } else {
            $this->display->hide();
        }
        
        // 8. Return output
        return $this->display->getOutput();
    }

    private function seedDemoScene(Core $core): Core
    {
        // Apply the 13 stamps from Appendix C
        // ...
    }
}
```

---

## 5. Implementation Details

### 5.1 Session-Based State Port

**Mechanism:** PHP's native `$_SESSION` superglobal

```php
final class StatePort implements Port
{
    private const SESSION_KEY_CORE = 'gol_core';
    private const SESSION_KEY_CLOCKED = 'gol_clocked';
    private const SESSION_KEY_PATTERN = 'gol_selected_pattern';

    public function loadCore(): Core
    {
        if (!isset($_SESSION[self::SESSION_KEY_CORE])) {
            return Core::initial();
        }
        return $_SESSION[self::SESSION_KEY_CORE];
    }

    public function saveCore(Core $core): void
    {
        $_SESSION[self::SESSION_KEY_CORE] = $core;
    }

    public function isClocked(): bool
    {
        return $_SESSION[self::SESSION_KEY_CLOCKED] ?? false;
    }

    public function setClocked(bool $clocked): void
    {
        $_SESSION[self::SESSION_KEY_CLOCKED] = $clocked;
    }

    public function getSelectedPattern(): string
    {
        return $_SESSION[self::SESSION_KEY_PATTERN] ?? 'blinker';
    }

    public function setSelectedPattern(string $pattern): void
    {
        $_SESSION[self::SESSION_KEY_PATTERN] = $pattern;
    }
}
```

**Session Configuration:**
- Session auto-start in `bootstrap.php`: `session_start()`
- Session cookie lifetime: configurable (default: 0 = until browser close)
- Session save handler: files (default)
- Session name: configurable (default: `PHPSESSIONID`)

### 5.2 File-Based Pattern Store

**Storage Format:** JSON files in `patterns/` directory

**File Structure (blinker.json):**
```json
{
  "name": "blinker",
  "cells": [
    {"x": -1, "y": 0},
    {"x": 0, "y": 0},
    {"x": 1, "y": 0}
  ]
}
```

**Implementation:**
```php
final class PatternStorePort implements Port
{
    private string $patternsDir;

    public function __construct(string $patternsDir)
    {
        $this->patternsDir = $patternsDir;
    }

    public function get(string $name): Cells
    {
        $path = $this->getPatternPath($name);
        if (!file_exists($path)) {
            return Cells::empty();
        }
        $data = json_decode(file_get_contents($path), true);
        if ($data === null) {
            return Cells::empty(); // Invalid JSON
        }
        $cells = [];
        foreach ($data['cells'] ?? [] as $coord) {
            $cells[] = new Position((int)$coord['x'], (int)$coord['y']);
        }
        return new Cells($cells);
    }

    public function list(): array
    {
        $files = glob($this->patternsDir . '/*.json');
        if ($files === false) {
            return [];
        }
        $names = [];
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $name = $data['name'] ?? pathinfo($file, PATHINFO_FILENAME);
            $names[] = $name;
        }
        sort($names);
        return $names;
    }

    /**
     * Seed all 19 catalog patterns to disk.
     * Called only once during first request (in seedDemoScene).
     */
    public function seedCatalog(): void
    {
        // Seed the 19 catalog patterns if not already present
        $catalog = $this->getCatalogPatterns();
        foreach ($catalog as $name => $coordinates) {
            $path = $this->getPatternPath($name);
            if (!file_exists($path)) {
                $data = ['name' => $name, 'cells' => $coordinates];
                file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * Check if all catalog patterns exist on disk.
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

    private function getPatternPath(string $name): string
    {
        return $this->patternsDir . '/' . $name . '.json';
    }

    private function getCatalogPatterns(): array
    {
        // Return the 19 patterns from Appendix A
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
            // ... all 19 patterns
        ];
    }
}
```

### 5.3 HTML/CSS Display Port with Clickable Grid

**Rendering Approach:**
- Generate complete HTML page with embedded CSS
- Use CSS Grid for cell layout
- Live cells: colored `<a>` tags
- Dead cells: empty `<a>` tags with different background
- Status bar: positioned element, shown/hidden via PHP logic
- Grid is dynamically sized based on current cells

**HTML Structure:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game of Life - PHP WebServerOnly</title>
    <style>
        /* Embedded CSS */
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { text-align: center; color: #333; }
        
        /* Pattern selector */
        .controls { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
        .controls form { display: inline-block; }
        select, button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007bff; color: white; cursor: pointer; }
        button:hover { background: #0056b3; }
        
        /* Grid */
        .grid-wrapper { overflow: auto; margin: 20px 0; border: 1px solid #ddd; padding: 10px; background: white; }
        .grid { display: grid; gap: 1px; margin: 0 auto; }
        .cell { display: block; width: 20px; height: 20px; border: 1px solid #eee; }
        .cell.alive { background: #000; }
        .cell:hover { opacity: 0.7; }
        
        /* Status bar */
        .status-bar { padding: 10px; background: #e9ecef; text-align: center; margin: 10px 0; border-radius: 4px; }
        
        /* Control links */
        .controls a { display: inline-block; padding: 8px 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
        .controls a:hover { background: #5a6268; }
    </style>
    <!-- Meta refresh for clocked mode -->
    <meta http-equiv="refresh" content="0.1">
</head>
<body>
    <div class="container">
        <h1>Conway's Game of Life</h1>
        
        <div class="controls">
            <!-- Pattern selector -->
            <form method="get">
                <select name="pattern" onchange="this.form.submit()">
                    <option value="blinker">Blinker</option>
                    <option value="toad">Toad</option>
                    ...
                </select>
                <noscript><button type="submit">Select</button></noscript>
            </form>
            
            <!-- Control links -->
            <a href="?action=step">Step</a>
            <a href="?action=toggle_status">Toggle Status</a>
            <a href="?action=clock_on">Clock On</a>
            <a href="?action=clock_off">Clock Off</a>
        </div>
        
        <!-- Status bar (conditionally shown) -->
        <div class="status-bar">
            123 cells are alive
        </div>
        
        <!-- Clickable grid -->
        <div class="grid-wrapper">
            <div class="grid" style="grid-template-columns: repeat(<?= $gridWidth ?>, 20px);">
                <a href="?action=stamp&pattern=blinker&x=-1&y=-1" class="cell"></a>
                <a href="?action=stamp&pattern=blinker&x=0&y=-1" class="cell"></a>
                <a href="?action=stamp&pattern=blinker&x=1&y=-1" class="cell"></a>
                ...
            </div>
        </div>
    </div>
</body>
</html>
```

**DisplayPort Implementation:**
```php
final class DisplayPort implements Port
{
    private Cells $cells;
    private string $status = '';
    private bool $statusVisible = false;
    private bool $clocked = false;
    private string $selectedPattern = 'blinker';
    private int $cellSize = 20;

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
        $bounds = $this->calculateBounds($this->cells, 10);
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
        // In a real implementation, this would use PatternStorePort->list()
        // For the plan, we show the structure
        $patterns = ['blinker', 'toad', 'beacon', 'glider', 'block', 'pulsar', 'gosperGun'];
        
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
        return '<a href="?' . ($clocked ? '' : 'clocked=1&') . 'action=step">Step</a>' .
               '<a href="?action=toggle_status">Toggle Status</a>' .
               '<a href="?action=' . ($clocked ? 'clock_off' : 'clock_on') . '">' .
               ($clocked ? 'Clock Off' : 'Clock On') . '</a>';
    }

    private function calculateBounds(Cells $cells, int $padding = 10): array
    {
        if ($cells->count() === 0) {
            return ['minX' => -$padding, 'maxX' => $padding, 'minY' => -$padding, 'maxY' => $padding];
        }
        
        $positions = $cells->all();
        $xs = array_map(fn($p) => $p->xOf(), $positions);
        $ys = array_map(fn($p) => $p->yOf(), $positions);
        
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
```

**Note on `onchange="this.form.submit()"`:** This uses JavaScript. Since the requirement is **NO JavaScript at all**, we need to remove this and use a submit button instead. The `noscript` tag is kept for browsers without JavaScript, but since we're forbidding JavaScript entirely, we should just always show the submit button.

**Revised pattern selector (pure HTML):**
```php
private function renderPatternSelector(): string
{
    $patterns = ['blinker', 'toad', 'beacon', 'glider', 'block', 'pulsar', 'gosperGun'];
    
    $options = '';
    foreach ($patterns as $pattern) {
        $selected = $pattern === $this->selectedPattern ? 'selected' : '';
        $options .= '<option value="' . htmlspecialchars($pattern) . '" ' . $selected . '>' . 
                    htmlspecialchars($pattern) . '</option>';
    }
    
    return '<form method="get"><select name="pattern">' . 
           $options . '</select><button type="submit">Select Pattern</button></form>';
}
```

### 5.4 Event Source Port

**Request Parameters (GET):**

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `action` | string | The action to perform | `tick`, `stamp`, `select_pattern`, `toggle_status`, `clock_on`, `clock_off`, `step` |
| `pattern` | string | Pattern name for stamp or select_pattern | `blinker` |
| `x` | int | X coordinate for stamp action | `10` |
| `y` | int | Y coordinate for stamp action | `20` |
| `clocked` | flag | Indicates clocked mode is active | `1` |

**Implementation:**
```php
final readonly class EventSourcePort implements Port
{
    public function __construct(private array $get)
    {
        // $get is $_GET
    }

    public function getAction(): ?string
    {
        return $this->get['action'] ?? null;
    }

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

    public function getPatternName(): ?string
    {
        return $this->get['pattern'] ?? null;
    }

    public function isClocked(): bool
    {
        return ($this->get['clocked'] ?? '0') === '1';
    }
}
```

### 5.5 Demo Scene Seeding

**Implementation:** Performed on first request (detected by checking if session core exists)

```php
private function seedDemoScene(Core $core): Core
{
    // Apply the 13 stamps from Appendix C
    $stamps = [
        ['pattern' => 'hwss', 'x' => 33, 'y' => 20],
        ['pattern' => 'rpentomino', 'x' => 35, 'y' => 10],
        ['pattern' => 'pentadecathlon', 'x' => 25, 'y' => 30],
        ['pattern' => 'blinker', 'x' => 25, 'y' => 10],
        ['pattern' => 'pentadecathlon', 'x' => 20, 'y' => 15],
        ['pattern' => 'mwss', 'x' => 40, 'y' => 15],
        ['pattern' => 'glider', 'x' => 13, 'y' => 13],
        ['pattern' => 'toad', 'x' => 30, 'y' => 15],
        ['pattern' => 'gosperGun', 'x' => 12, 'y' => 10],
        ['pattern' => 'pulsar', 'x' => 13, 'y' => 24],
        ['pattern' => 'acorn', 'x' => 25, 'y' => 25],
        ['pattern' => 'beacon', 'x' => 25, 'y' => 20],
        ['pattern' => 'pulsar', 'x' => 36, 'y' => 30],
    ];

    // Ensure catalog patterns are seeded to disk (one-time operation)
    if (!$this->patternStore->isCatalogSeeded()) {
        $this->patternStore->seedCatalog();
    }

    foreach ($stamps as $stamp) {
        $patternCells = $this->patternStore->get($stamp['pattern']);
        if ($patternCells->count() === 0) {
            continue; // Pattern not found
        }
        $reference = new Position($stamp['x'], $stamp['y']);
        $translated = Pattern::translate($patternCells, $reference);
        $core = $core->withCells($core->cellsOf()->union($translated));
    }

    return $core;
}
```

**Note:** This seeds the catalog patterns to disk only on first request, then stamps the 13 demo scene patterns from Appendix C.

### 5.6 Clock Mode Handling

**Shell Configuration:**
- Clock mode is stored in session: `$_SESSION['gol_clocked'] = true/false`
- Default: off (step mode)
- Interval: 0.1 seconds (100ms) for v1 per Appendix F

**Behavior:**
- When clocked mode is ON: each page response includes `<meta http-equiv="refresh" content="0.1">`
- When clocked mode is OFF: no meta refresh tag
- Toggling clock mode: user clicks link with `action=clock_on` or `action=clock_off`

**Implementation in DisplayPort:**
```php
private function renderHtml(): string
{
    // ...
    $metaRefresh = $this->clocked 
        ? '<meta http-equiv="refresh" content="0.1">'
        : '';
    // ...
}
```

**Implementation in RequestHandler:**
```php
// In handle() method:
$clocked = $this->state->isClocked();

// Handle clock toggle actions
if ($action === 'clock_on') {
    $this->state->setClocked(true);
    $clocked = true;
} elseif ($action === 'clock_off') {
    $this->state->setClocked(false);
    $clocked = false;
}

// Auto-tick when clocked mode is active and no explicit action
if ($clocked && $action === null) {
    $program = CoreOperations::nextGenerationProgram($core);
    $this->interpreter->interpret($program);
    $core = $this->state->loadCore();
}
```

---

## 6. Composer Configuration

```json
{
    "name": "golwisafciml/php-classic-webserveronly",
    "type": "project",
    "description": "Imperative shell for Conway's Game of Life (v1) - Web Server Only",
    "license": "AGPL-3.0",
    "require": {
        "php": "^8.1",
        "golwisafciml/php-classic-core": "@dev"
    },
    "autoload": {
        "psr-4": {
            "GameOfLife\\Classic\\WebServerOnly\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "GameOfLife\\Classic\\WebServerOnly\\Tests\\": "tests/"
        }
    },
    "minimum-stability": "dev",
    "config": {
        "optimize-autoloader": true
    },
    "repositories": [
        {
            "type": "path",
            "url": "../core",
            "options": {
                "symlink": false
            }
        }
    ]
}
```

---

## 7. Entry Point (index.php)

```php
<?php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../bootstrap.php';

use GameOfLife\Classic\Core\Core;
use GameOfLife\Classic\WebServerOnly\Shell\RequestHandler;
use GameOfLife\Classic\WebServerOnly\Shell\Interpreter;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\DisplayPort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\PatternStorePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\EventSourcePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\StatePort;
use GameOfLife\Classic\WebServerOnly\Models\ShellConfig;

// Start session
session_start();

// Configuration
$config = new ShellConfig(
    tickInterval: 0.1, // seconds
    patternsDir: __DIR__ . '/patterns',
    debug: false
);

// Initialize PatternStore (lazy initialization - patterns are created on first use)
$patternStore = new PatternStorePort($config->patternsDir);

// Create ports
$displayPort = new DisplayPort();
$statePort = new StatePort();
$eventSourcePort = new EventSourcePort($_GET);

// Create interpreter
$interpreter = new Interpreter($displayPort, $patternStore, $statePort);

// Create request handler
$handler = new RequestHandler(
    $interpreter,
    $displayPort,
    $patternStore,
    $statePort,
    $eventSourcePort,
    $config
);

// Handle request and output HTML
header('Content-Type: text/html; charset=utf-8');
echo $handler->handle();
```

---

## 8. Deployment

### 8.1 Directory Structure

```
project-root/
├── php/
│   └── classic/
│       ├── core/          # Existing functional core
│       └── WebServerOnly/ # This shell
│           ├── composer.json
│           ├── index.php
│           ├── config.php
│           ├── bootstrap.php
│           ├── src/
│           │   └── Shell/
│           │       ├── Interpreter.php
│           │       ├── Ports/
│           │       │   ├── DisplayPort.php
│           │       │   ├── PatternStorePort.php
│           │       │   ├── EventSourcePort.php
│           │       │   └── StatePort.php
│           │       └── RequestHandler.php
│           │   └── Models/
│           │       └── ShellConfig.php
│           ├── patterns/
│           │   └── *.json (will be auto-generated)
│           └── public/
│               └── index.php (symlink to ../index.php)
└── web/                  # Optional: symlink to php/classic/WebServerOnly/public/
```

**Web Server Configuration:**
- Document root: `php/classic/WebServerOnly/public/`
- PHP must be configured to handle `.php` files
- No special requirements beyond PHP 8.1+

**Note:** Pattern Store initialization happens only once (during first request's demo scene seeding), not on every request.

### 8.2 PHP-FPM Support

**Requirement:** "usable with php-fpm only if needed"

**Implementation:**
- Works with PHP-FPM via FastCGI
- Also works with mod_php (Apache) or PHP built-in server
- No FPM-specific code; standard PHP execution
- Recommended: Enable `cgi.fix_pathinfo = 1` in php.ini for Nginx/PHP-FPM

### 8.3 Running with PHP Built-in Server

```bash
cd php/classic/WebServerOnly/public
php -S localhost:8000
# Access at http://localhost:8000
```

---

## 9. Test Strategy

### 9.1 Manual Testing
- **Primary verification method** for web UI
- Test with PHP built-in server
- Test with PHP-FPM if available

**Test Cases:**
1. ✅ First load: demo scene displays correctly
2. ✅ Click "Step": advances one generation
3. ✅ Click "Toggle Status": shows/hides cell count
4. ✅ Select pattern + click cell: stamps pattern at that location
5. ✅ Click "Clock On": enables auto-ticking every 100ms
6. ✅ Click "Clock Off": stops auto-ticking
7. ✅ Stamp while clocked: pattern appears, clock continues
8. ✅ Multiple browser tabs: independent game states
9. ✅ Refresh page: state persists
10. ✅ Invalid coordinates: handled gracefully (no crash)

### 9.2 Unit Tests (Optional)
- **PatternStorePortTest**: file read/write, catalog initialization
- **DisplayPortTest**: HTML generation correctness
- **StatePortTest**: session load/save
- **EventSourcePortTest**: parameter parsing

**Test Framework:** PHPUnit (already available via core's composer.json)

**Decision:** Shell tests are lower priority than core tests; manual verification is primary for initial implementation.

---

## 10. Deviations from Spec

| Aspect | Spec Requirement | Our Implementation | Justification |
|--------|------------------|---------------------|---------------|
| **Clock mechanism** | Clock or manual step | Meta refresh tag | Only practical HTML/CSS solution without JavaScript |
| **State persistence** | Mutable reference | PHP sessions | Web-specific adaptation of State port |
| **Event source** | Tickers, GUI events | HTTP GET parameters | Web-specific adaptation |
| **Display** | Port-based | HTML/CSS renderer | Web-specific adaptation |
| **Pattern store** | Shell-managed | File-based JSON | Simple, portable, no DB dependency |
| **Tick interval** | Configurable | 0.1s (v1) | Per Appendix F |
| **Startup visibility** | Hidden (v1) | Hidden | Per spec §4.4 |
| **JavaScript** | Not mentioned | Explicitly none | User requirement |
| **Input mechanism** | Not specified | Clickable grid of `<a>` tags | Most user-friendly pure HTML solution |
| **Pattern catalog initialization** | Not specified | Lazy initialization during first request's demo scene seeding | Avoids systematic disk access on every HTTP call |

All deviations are **environment-specific adaptations** required by the WebServerOnly environment. The **functional core remains unchanged** and fully compliant with the spec.

---

## 11. Open Questions

1. **Grid sizing:** Should we add a configuration option for cell size and grid padding?
   - **Recommendation:** Keep defaults (20px cells, 10-cell padding) but allow override via config

2. **Performance:** For very large patterns, rendering many `<a>` tags might be slow. Should we limit the grid size?
   - **Recommendation:** Dynamic grid based on cell bounds with reasonable padding is sufficient

3. **Pattern selector UX:** With 19 patterns, should we organize them into categories?
   - **Recommendation:** Simple alphabetical dropdown is sufficient for v1

4. **Reset functionality:** Should we add a "Reset" button to return to initial demo scene?
   - **Recommendation:** Yes, add as `action=reset` which re-seeds the demo scene

5. **Mobile support:** Should we add responsive design for mobile browsers?
   - **Recommendation:** Basic responsive CSS is already included; full mobile optimization is out of scope

6. **Error handling:** How to display errors (file not found, etc.) to users?
   - **Recommendation:** Show user-friendly message in the HTML output; log details to error log

---

## 12. Implementation Order

1. **Setup and Infrastructure**
   - Create directory structure
   - Write `composer.json` with core dependency
   - Run `composer install` to verify dependency resolution
   - Create `bootstrap.php` with autoloading and session start
   - Create `config.php` with default configuration

2. **State Port**
   - Implement `StatePort` (session-based)
   - Test session load/save

3. **Pattern Store Port**
   - Implement `PatternStorePort` (file-based JSON)
   - Implement `seedCatalog()` and `isCatalogSeeded()` methods
   - Seed 19 catalog patterns only during first request's demo seeding
   - Verify patterns load correctly

4. **Event Source Port**
   - Implement `EventSourcePort` (GET parameter parsing)
   - Test action, pattern, coordinate parsing

5. **Display Port**
   - Implement `DisplayPort` with clickable grid
   - Test HTML generation
   - Test CSS styling

6. **Interpreter**
   - Implement visitor pattern for Program types
   - Wire up ports to interpreter

7. **Request Handler**
   - Implement main request processing logic
   - Implement action dispatching
   - Implement demo scene seeding

8. **Entry Point**
   - Create `index.php`
   - Create `public/index.php` symlink
   - Test with PHP built-in server

9. **Testing**
   - Manual testing with PHP built-in server
   - Verify all user actions work
   - Verify clocked mode with meta refresh
   - Verify state persistence across page reloads

---

## 13. Acceptance Criteria

The implementation is complete when:

1. ✅ `php/classic/WebServerOnly/` directory exists with all planned files
2. ✅ `composer install` succeeds and autoloading works
3. ✅ PHP built-in server can start: `php -S localhost:8000 -t public/`
4. ✅ Accessing `http://localhost:8000` displays the Game of Life with demo scene
5. ✅ All 19 catalog patterns are available in the selector
6. ✅ Clicking "Step" advances one generation
7. ✅ Clicking "Toggle Clock" enables auto-ticking every 100ms
8. ✅ Clicking "Toggle Status" shows/hides the cell count message
9. ✅ Selecting a pattern from dropdown + clicking "Select Pattern" updates the selected pattern
10. ✅ Clicking on a cell stamps the selected pattern at that location
11. ✅ All core tests still pass (`../core/vendor/bin/phpunit`)
12. ✅ No JavaScript in the output HTML (verify with browser dev tools)
13. ✅ Meta refresh tag is present when clocked mode is active
14. ✅ Multiple browser tabs can have independent game states (via sessions)
15. ✅ Works with PHP-FPM (if available in test environment)
16. ✅ Clickable grid uses `<a>` tags with coordinates in query parameters

---

## 14. Ready for Review

**This plan is ready for review.** The key amendment from the previous version is the **clickable grid implementation** using `<a>` tags with coordinates encoded in GET parameters, which satisfies the "NO JavaScript" requirement while providing a user-friendly interface.

Please confirm:

1. The clickable grid approach is acceptable
2. The scope is correct (WebServerOnly shell, HTML/CSS only, no JS, meta refresh for clock)
3. The architecture aligns with your expectations
4. The file layout is acceptable
5. The state persistence mechanism (PHP sessions) is acceptable
6. The pattern store mechanism (file-based JSON) is acceptable

Once approved, I will proceed to Phase 2 — IMPLEMENT.

---

*Plan created: 2026-10-20*
*Amended: 2026-10-20 (added clickable grid implementation)*
*Phase: PLAN (awaiting approval)*
