# Plan: PHP Classic WebServerOnly (v2) — Fixes for Remaining Issues

**Task:** Fix remaining issues in the WebServerOnly shell implementation.

**Location:** `php/classic/WebServerOnly/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

---

## 0. Issues to Address

### Issue 1: Scrollbar appears for grid display
**Problem:** The container has max-width of 1200px but the grid is 1600px wide (160 cells × 10px), causing horizontal scrollbar.

**Solution:** Set `.container` max-width to 1700px in DisplayPort CSS to display the full grid without scrolling.

### Issue 2: TICK_INTERVAL needs to be 2 seconds
**Problem:** Current config has TICK_INTERVAL = 0.5, and index.php uses 0.1. Need to standardize on 2 seconds.

**Solution:** 
- Update TICK_INTERVAL in config.php to 2.0
- Update ShellConfig default to 2.0
- Update index.php to use config constant or ShellConfig value
- Update DisplayPort meta refresh to use 2 seconds when clocked

### Issue 3: ShellConfig has unnecessary default values
**Problem:** ShellConfig.php defines default values in constructor (tickInterval = 0.5, patternsDir = '', debug = false) which brings confusion and serves no purpose.

**Solution:** Remove all default values from ShellConfig constructor parameters. Require explicit values on instantiation.

### Issue 4: DisplayPort hardcodes viewport bounds
**Problem:** DisplayPort hardcodes startX=-80, startY=-35, endX=79, endY=34 instead of computing from gridWidth and gridHeight.

**Solution:** Compute viewport bounds from grid dimensions:
- startX = -gridWidth / 2
- endX = -startX - 1 (ensures gridWidth cells)
- startY = -gridHeight / 2  
- endY = -startY - 1 (ensures gridHeight cells)

For gridWidth=160, gridHeight=70:
- startX = -160/2 = -80
- endX = -(-80) - 1 = 79
- startY = -70/2 = -35
- endY = -(-35) - 1 = 34

### Issue 5: Toggle status action persists across refreshes when clock is on
**Problem:** When clock is on and user clicks "Toggle Status", the `action=toggle_status` parameter is included in all URLs via buildUrl(). On each meta-refresh, this action gets re-executed, causing the status bar to flash on/off repeatedly.

**Solution:** The toggle_status action should NOT be preserved in URLs. Need to:
- Modify buildUrl() to NOT include action parameter when action is not explicitly requested
- OR: Handle toggle_status differently - it should toggle the state in session, not as an action parameter
- Best approach: Store status visibility in session state, and have toggle_status flip that flag

**Selected approach:** 
1. Add statusVisible to StatePort session storage
2. Modify RequestHandler to handle toggle_status by flipping the session flag
3. Update DisplayPort to read status visibility from session state instead of from Core
4. Remove the toggle_status program execution (since it's now UI-only, not Core-level)

Wait - looking at the code more carefully, the issue is different. The `buildUrl()` method in DisplayPort is used to generate URLs for controls. When clock is on, it includes `clocked=1` but NOT the action. The problem is that when user clicks toggle_status once, the action gets in the URL, and then when the page refreshes (due to meta refresh), that action is re-executed.

Actually, looking at renderControls():
```php
'<a href="' . $this->buildUrl('toggle_status') . '">Toggle Status</a>'
```

The buildUrl() method adds the action to the URL. So when clock is on and you click toggle_status, the URL becomes `?action=toggle_status&clocked=1`. Then when the meta refresh happens, it refreshes to that same URL, re-executing the toggle_status action.

**Solution:** The toggle_status action should NOT be included in the URL for the meta refresh. We need to separate:
1. User-initiated actions (which should NOT be in the URL for clock refreshes)
2. Clock refreshes (which should preserve only clocked and pattern, not action)

Better approach: Don't include action in the URL at all for clock mode. When clock is on, the action parameter should be ignored for the refresh. We need to track the "pending action" separately.

Actually, the simplest fix: When clock is on, the meta refresh should go to a URL without any action parameter. The action parameter should only be for user-initiated actions that happen once.

**Final solution:**
- Modify DisplayPort::buildUrl() to NOT include action parameter in URLs
- OR: Better, have the clock refresh go to a clean URL without action
- OR: Best - when building URLs for controls in clocked mode, use action=null or exclude action from clock refresh

Actually, re-reading the code: the meta refresh is `<meta http-equiv="refresh" content="0.5">` which refreshes to the same URL. So if the user clicked toggle_status, the URL has `action=toggle_status`, and the refresh keeps re-executing it.

**Solution:** Clear the action parameter after handling it. Or: when clock is on, ignore action parameter for refresh purposes.

Cleanest fix: After handling a non-clock action (like toggle_status), redirect to a URL without the action parameter. But PHP can't easily redirect without breaking the flow.

Alternative: In RequestHandler, if we're in clocked mode AND there's an action that shouldn't repeat (like toggle_status), handle it once and then clear it from the state.

Actually, the simplest: Don't include action in buildUrl() for the controls. The action should only be for the current request, not persisted in URLs.

Wait, but step and other actions DO need to be in URLs. The issue is specific to toggle_status.

**Best solution:** Track in session whether a toggle has been requested, and handle it only once. Or: separate the concept of "one-time actions" from "state-preserving parameters".

Simpler: In RequestHandler, after handling toggle_status, set a flag that it's been handled. But this gets complex.

**Cleanest solution:** Remove action parameter from meta refresh URL. When clock is on, the refresh should go to the base URL with only clocked=1 and pattern. The action parameter should only be for user clicks, not for automatic refreshes.

To do this: Change the meta refresh to use a URL without action. We can add a buildCleanUrl() method that includes clocked and pattern but NOT action.

```php
// In DisplayPort
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

// In renderHtml, for meta refresh:
$metaRefresh = $this->clocked
    ? '<meta http-equiv="refresh" content="2" url="' . htmlspecialchars($this->buildCleanUrl()) . '">'
    : '';
```

But wait, the meta refresh attribute doesn't support URL in HTML. It only supports seconds. So we can't use a different URL.

Alternative: Use JavaScript for the clock refresh instead of meta refresh, so we can control the URL.

OR: Simpler - when action is toggle_status and clock is on, after handling it, redirect to a clean URL. But this causes page flicker.

OR: In RequestHandler, check if we're in clocked mode and action is toggle_status. Handle it, but then don't pass action through to the next state. The action should be consumed and cleared.

Actually, the real issue is that the action parameter persists in the URL. The solution is: **Don't put action parameters in the URL for controls when clock is on.** Instead, use POST for actions that shouldn't repeat, or use session to track one-time actions.

**Final decision:** Use session to track that toggle_status was clicked. In RequestHandler:
1. Check if session has a pending toggle_status flag
2. If yes, handle it and clear the flag
3. When user clicks toggle_status link, set the session flag and redirect

But this requires redirects which breaks the flow.

**Simplest working solution:** In RequestHandler::handle(), after handling toggle_status (and any other one-time actions), we need to ensure that subsequent clock refreshes don't re-execute it. We can do this by:
- Storing a "last action" in session
- Only execute actions once (not on refresh)

Actually, the cleanest solution is to NOT include action in the URL at all for the controls. Instead:
- Use POST forms for all actions
- OR: Have the action be a one-time thing that's consumed from GET and then cleared

**Decision:** Modify the approach to NOT pass action through URLs. Instead:
1. User clicks "Toggle Status" link with action=toggle_status
2. RequestHandler handles it and sets a flag in session
3. On subsequent requests (including refreshes), check the session flag, not the GET action

But this means we need to change all action handling to use session-based state instead of GET parameters for stateful actions.

**Simpler pragmatic solution:** For toggle_status specifically, handle it in RequestHandler by flipping a session-based statusVisible flag, and don't use the Core's status visibility at all for the web UI. This separates UI state (whether status bar is shown) from Core state (whether to display status message).

The Core's status visibility is for the CLI shells. For the web shell, we maintain our own UI state in the session.

So:
1. Add isStatusVisible() and setStatusVisible() to StatePort
2. In RequestHandler, handle toggle_status by flipping the session flag
3. In DisplayPort, use the session flag instead of Core's status visibility
4. Remove the toggle_status program execution

This way, the toggle_status action only needs to be executed once (to flip the session flag), and subsequent refreshes don't re-execute it.

---

## 1. Decisions

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Grid container width** | max-width: 1700px | Eliminates horizontal scrollbar for 1600px grid |
| **Tick interval** | 2 seconds | User requirement |
| **ShellConfig defaults** | No default values | Removes confusion, forces explicit configuration |
| **Viewport calculation** | Compute from gridWidth/gridHeight using startX=-width/2, endX=-startX-1 | Cleaner, more maintainable |
| **Toggle status persistence** | Use session-based UI state, separate from Core status visibility | Fixes repeated execution on refresh |

---

## 2. Architecture Changes

### 2.1 DisplayPort
- Update `.container` CSS: change max-width from 1200px to 1700px
- Replace hardcoded startX, startY, endX, endY with computed values:
  - `$startX = -$this->gridWidth / 2;`
  - `$endX = -$startX - 1;`
  - `$startY = -$this->gridHeight / 2;`
  - `$endY = -$startY - 1;`
- Add `setStatusVisible(bool $visible)` method
- Update meta refresh interval from 0.5 to 2 seconds
- Update renderHtml() to use session-based status visibility (passed from RequestHandler) instead of internal $statusVisible

### 2.2 RequestHandler
- Add handling for toggle_status using StatePort's status visibility flag
- Remove the program execution for toggle_status (Core-level status visibility is not used for web UI)
- Ensure status visibility is passed to DisplayPort
- Update meta refresh to use 2 seconds

### 2.3 StatePort
- Add `isStatusVisible(): bool` method
- Add `setStatusVisible(bool $visible): void` method
- Add session key constant for status visibility

### 2.4 config.php
- Change TICK_INTERVAL from 0.5 to 2.0

### 2.5 ShellConfig.php
- Remove all default values from constructor parameters

### 2.6 index.php
- Update ShellConfig instantiation to pass all required parameters explicitly
- Update tickInterval from 0.1 to 2.0

---

## 3. File Changes

### DisplayPort.php
```php
// Update CSS
.container {
    max-width: 1700px;  // Changed from 1200px
    margin: 0 auto;
}

// Update viewport calculation in renderGrid()
private function renderGrid(): string
{
    $startX = -$this->gridWidth / 2;
    $startY = -$this->gridHeight / 2;
    $endX = -$startX - 1;
    $endY = -$startY - 1;
    
    // ... rest of method
}

// Add method to set status visibility
public function setStatusVisible(bool $visible): void
{
    $this->statusVisible = $visible;
}

// Update meta refresh to 2 seconds
private function renderHtml(): string
{
    // ...
    $metaRefresh = $this->clocked
        ? '<meta http-equiv="refresh" content="2">'
        : '';
    // ...
}
```

### RequestHandler.php
```php
public function handle(): string
{
    // ... existing setup ...
    
    // Handle toggle_status using session state
    if ($action === 'toggle_status') {
        $currentVisibility = $this->state->isStatusVisible();
        $this->state->setStatusVisible(!$currentVisibility);
        $statusVisible = !$currentVisibility;
    } else {
        $statusVisible = $this->state->isStatusVisible();
    }
    
    // ... other action handling ...
    
    return $this->renderFinalState($core, $clocked, $selectedPattern, $statusVisible);
}

private function renderFinalState(Core $core, bool $clocked, string $selectedPattern, bool $statusVisible): string
{
    $this->state->saveCore($core);
    
    $this->display->setClocked($clocked);
    $this->display->setSelectedPattern($selectedPattern);
    $this->display->setStatusVisible($statusVisible);
    $this->display->render($core->cellsOf());
    
    // Don't use Core's status visibility for web UI
    if ($statusVisible && $core->cellsOf()->count() > 0) {
        $this->display->show(Pattern::statusMessage($core->cellsOf()));
    } else {
        $this->display->hide();
    }
    
    return $this->display->getOutput();
}
```

### StatePort.php
```php
private const SESSION_KEY_STATUS_VISIBLE = 'gol_status_visible';

public function isStatusVisible(): bool
{
    return $_SESSION[self::SESSION_KEY_STATUS_VISIBLE] ?? false;
}

public function setStatusVisible(bool $visible): void
{
    $_SESSION[self::SESSION_KEY_STATUS_VISIBLE] = $visible;
}
```

### config.php
```php
const TICK_INTERVAL = 2.0;  // Changed from 0.5
```

### ShellConfig.php
```php
public function __construct(
    public float $tickInterval,  // No default
    public string $patternsDir,   // No default
    public bool $debug             // No default
) {}
```

### index.php
```php
$config = new ShellConfig(
    tickInterval: 2.0,  // Changed from 0.1
    patternsDir: __DIR__ . '/patterns',
    debug: false
);
```

---

## 4. Test Plan

1. ✅ Grid displays without horizontal scrollbar (container max-width: 1700px)
2. ✅ Grid viewport is computed from gridWidth and gridHeight
3. ✅ TICK_INTERVAL is 2 seconds in all locations
4. ✅ ShellConfig has no default parameter values
5. ✅ Toggle status works once and doesn't repeat on clock refresh
6. ✅ Status bar visibility persists correctly across actions
7. ✅ All existing functionality still works
8. ✅ Meta refresh uses 2 second interval

---

## 5. Acceptance Criteria

The implementation is complete when:
- Grid displays without scrollbar (1700px container)
- Viewport bounds are computed from gridWidth/gridHeight
- TICK_INTERVAL is consistently 2 seconds
- ShellConfig requires all parameters explicitly
- Toggle status action works correctly with clock on (no flashing)
- Status bar visibility state is maintained properly
- All existing tests pass

---

*Plan revised: 2026-10-20*
*Phase: PENDING APPROVAL*
