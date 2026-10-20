# Plan: PHP Classic WebServerOnly (v1) — Fixes for Identified Issues

**Task:** Fix identified issues in the WebServerOnly shell implementation.

**Location:** `php/classic/WebServerOnly/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

---

## 0. Issues to Address

### Issue 1: Visual grid permanently resizing
**Problem:** Grid dynamically resizes based on cell bounds, giving the impression of bugged cell generation.

**Solution:** Fixed visual grid showing **always the same coordinates**:
- Dimensions: 160 columns × 70 rows
- Cell size: 10px × 10px
- **Fixed viewport**: x from -80 to 79 (inclusive), y from -35 to 34 (inclusive)
- Does NOT follow cell population - always displays the same area
- **Demo scene translation**: Current demo scene center is at (26,22). Translate all 13 stamps by (-26, -22) so the center moves to (0,0), fitting nicely in the fixed viewport.

### Issue 2: Session can only be reinitialized by manually removing cookie
**Problem:** No easy way to reset the game.

**Solution:** Add explicit `action=reset` that:
- Resets Core to initial state
- Re-seeds the demo scene (13 stamps from Appendix C)
- Keeps UI preferences (clocked mode, selected pattern)

### Issue 3: Inputs do not work when clock is on
**Problem:** 0.1s refresh is too aggressive, causes race conditions with form submissions.

**Solution:** 
- Increase meta refresh interval from 0.1s to **0.5s**
- Fix all URLs to preserve UI state (clocked, pattern) across actions

### Issue 4: "Select Pattern" always goes back to "blinker"
**Problem:** Form submits `?pattern=NAME` without `action=select_pattern`, so handler doesn't recognize it.

**Solution:**
- Fix pattern selector form to include `action=select_pattern` as hidden field
- Ensure selected pattern is preserved in session
- Fix all control URLs to preserve selected pattern

---

## 1. Decisions

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Visual Grid** | Fixed 160x70 at 10px, coordinates -80..79 × -35..34 | Eliminates resizing, provides stable viewport |
| **Clock Interval** | 0.5s (was 0.1s) | Fixes input race conditions, more user-friendly |
| **Pattern Selection** | Form includes `action=select_pattern` | Ensures handler recognizes the intent |
| **Session Reset** | Explicit `action=reset` | Easy way to restart game |
| **State Preservation** | All URLs include clocked and pattern when non-default | Prevents state loss across actions |

---

## 2. Architecture Changes

### 2.1 DisplayPort
- Add fixed grid dimensions: `$gridWidth = 160`, `$gridHeight = 70`, `$cellSize = 10`
- Replace dynamic bounds calculation with fixed viewport: always x=-80..79, y=-35..34
- Add `buildUrl()` helper to preserve state in all generated URLs
- Fix pattern selector form to include `action=select_pattern` and `clocked` state
- Update CSS: cell size 10px, remove borders to save space
- Update meta refresh to 0.5s

### 2.2 RequestHandler
- Add handling for `action=reset`: reset Core, re-seed demo scene
- Ensure clocked state from GET parameters is respected
- Verify pattern selection works with form submission
- **Translate demo scene stamps**: In `seedDemoScene()`, subtract 26 from x and 22 from y for all 13 stamps

### 2.3 Configuration
- Update tick interval from 0.1 to 0.5 in ShellConfig or config.php

---

## 3. File Changes

### DisplayPort.php
```php
// Add properties
private int $gridWidth = 160;
private int $gridHeight = 70;
private int $cellSize = 10;

// Replace renderGrid()
private function renderGrid(): string
{
    $startX = -80;  // Fixed viewport
    $startY = -35;  // Fixed viewport
    $endX = 79;     // Fixed viewport
    $endY = 34;     // Fixed viewport
    
    $html = '<div class="grid" style="grid-template-columns: repeat(160, 10px);">';
    
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

// Add buildUrl helper
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

// Fix pattern selector form
private function renderPatternSelector(): string
{
    $patterns = [/* ... */];
    $options = '';
    foreach ($patterns as $pattern) {
        $selected = $pattern === $this->selectedPattern ? 'selected' : '';
        $options .= '<option value="' . htmlspecialchars($pattern) . '" ' . $selected . '>' .
                    htmlspecialchars($pattern) . '</option>';
    }
    $clockedField = $this->clocked ? '<input type="hidden" name="clocked" value="1">' : '';
    return '<form method="get">' .
           '<select name="pattern">' . $options . '</select>' .
           '<input type="hidden" name="action" value="select_pattern">' .
           $clockedField .
           '<button type="submit">Select Pattern</button>' .
           '</form>';
}

// Fix controls to preserve state
private function renderControls(): string
{
    return '<a href="' . $this->buildUrl('step') . '">Step</a>' .
           '<a href="' . $this->buildUrl('toggle_status') . '">Toggle Status</a>' .
           '<a href="' . $this->buildUrl($this->clocked ? 'clock_off' : 'clock_on') . '">' .
           ($this->clocked ? 'Clock Off' : 'Clock On') . '</a>' .
           '<a href="' . $this->buildUrl('reset') . '">Reset</a>';
}

// Update meta refresh
private function renderHtml(): string
{
    // ...
    $metaRefresh = $this->clocked
        ? '<meta http-equiv="refresh" content="0.5">'
        : '';
    // ...
}

// Update CSS for 10px cells
private function getCss(): string
{
    return <<<CSS
/* ... other styles ... */
.cell {
    display: block;
    width: 10px;
    height: 10px;
    border: 0;
}
.cell.alive {
    background: #000;
}
/* ... */
CSS;
}
```

### RequestHandler.php
```php
// Translate demo scene so its center moves from (26,22) to (0,0)
private function seedDemoScene(Core $core): Core
{
    // Original stamps from Appendix C
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

    // Translation: subtract 26 from x, subtract 22 from y
    // This moves center from (26,22) to (0,0)
    foreach ($stamps as $stamp) {
        $patternCells = $this->patternStore->get($stamp['pattern']);
        if ($patternCells->count() === 0) {
            continue;
        }
        // Translate the reference point
        $reference = new Position(
            $stamp['x'] - 26,  // Subtract 26 from x
            $stamp['y'] - 22   // Subtract 22 from y
        );
        $translated = Pattern::translate($patternCells, $reference);
        $core = $core->withCells($core->cellsOf()->union($translated));
    }

    return $core;
}

public function handle(): string
{
    $core = $this->state->loadCore();
    $clocked = $this->state->isClocked();
    $selectedPattern = $this->state->getSelectedPattern();
    
    // If clocked parameter is in GET, use it (preserves clocked state across form submissions)
    if ($this->eventSource->isClocked()) {
        $clocked = true;
    }
    
    // ... existing seed logic ...
    
    $action = $this->eventSource->getAction();
    
    // Handle reset action
    if ($action === 'reset') {
        $core = Core::initial();
        $core = $this->seedDemoScene($core);
        $this->state->saveCore($core);
        return $this->renderFinalState($core, $clocked, $selectedPattern);
    }
    
    // ... rest of existing code ...
}
```

### ShellConfig.php or config.php
```php
// Change tick interval from 0.1 to 0.5
// In ShellConfig constructor default:
public float $tickInterval = 0.5;

// OR in config.php:
const TICK_INTERVAL = 0.5;
```

---

## 4. Test Plan

1. ✅ Grid is fixed at 160x70 (1600px × 700px)
2. ✅ Grid always shows x=-80..79, y=-35..34
3. ✅ Meta refresh uses 0.5s interval
4. ✅ Pattern selector works and persists selection
5. ✅ Reset action resets game to demo scene
6. ✅ Inputs work reliably with clock on
7. ✅ Clocked state preserved across all actions
8. ✅ Selected pattern preserved across all actions
9. ✅ All existing functionality still works

---

## 5. Acceptance Criteria

The implementation is complete when:
- Grid displays fixed 160x70 at 10px showing coordinates -80..79 × -35..34
- Demo scene is translated so its center is at (0,0) instead of (26,22)
- Pattern selector works correctly
- Reset action works
- Clock interval is 0.5s
- Inputs work with clock on
- State is preserved across all actions

---

*Plan revised: 2026-10-20*
*Phase: PLAN (awaiting approval)*
