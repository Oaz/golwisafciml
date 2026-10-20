# Plan: Unit Tests for RequestHandler — WebServerOnly Shell

**Task:** Define comprehensive unit tests for `GameOfLife\Classic\WebServerOnly\Shell\RequestHandler` to clarify expected behavior and enable refactoring.

**Location:** `php/classic/WebServerOnly/`

**Phase:** Phase 1 — PLAN

---

## 0. Context

The current `RequestHandler::handle()` method contains multiple conditional branches handling different actions (`tick`, `step`, `stamp`, `clock_on`, `clock_off`, `select_pattern`, `toggle_status`, `reset`). The absence of unit tests makes it difficult to:
- Verify correct behavior for each action
- Understand the interaction between GET parameters and session state
- Identify edge cases (e.g., clocked mode with various GET parameter combinations)
- Safely refactor the conditional logic

The user reported that in clocked mode, "it looks like 2 generations are computed at once" — this suggests a state management issue between session, GET parameters, and the clocked flag.

---

## 1. Decisions

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| **Test Framework** | PHPUnit | Already used in the project (php/classic/core uses PHPUnit) |
| **Test Type** | Unit tests with mocked dependencies | RequestHandler depends on ports (DisplayPort, StatePort, EventSourcePort, PatternStorePort) which need mocking |
| **Test Scope** | Public behavior only | Test the `handle()` method's output and side effects on ports |
| **Test Style** | Given/When/Then with data providers | Follows PHPUnit best practices, matches existing core tests |
| **Mock Library** | PHPUnit's built-in mock objects | No external dependencies needed |

---

## 2. Scope

### 2.1 In Scope
- Unit tests for `RequestHandler::handle()` method
- Tests for all supported actions: `tick`, `step`, `stamp`, `clock_on`, `clock_off`, `select_pattern`, `toggle_status`, `reset`
- Tests for action-less requests (initial load, clocked auto-refresh)
- Tests for interaction between GET parameters (`action`, `clocked`, `pattern`, `x`, `y`) and session state
- Tests for state transitions (session writes, core persistence)

### 2.2 Out of Scope
- Integration tests (HTTP-level, browser-level)
- Tests for DisplayPort rendering (visual output)
- Tests for PatternStorePort file I/O
- Tests for the Core (already tested in php/classic/core/tests/)
- Performance tests

---

## 3. Test Groups and Test Cases

The test suite is organized into **test groups** that map to the spec's §11 test groups and the RequestHandler's responsibilities.

### Group R1 — Initial Request Handling

Tests for the first request to the application (no session state).

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R1-1 | Initial request seeds demo scene | Empty session, no GET params | handle() called | Demo scene (13 stamps from Appendix C) is seeded in returned core |
| R1-2 | Initial request marks session as initialized | Empty session, no GET params | handle() called | Session flag `gol_initialized` is set to true |
| R1-3 | Initial request with clocked=1 GET param | Empty session, GET['clocked']='1' | handle() called | Demo scene is seeded AND session clocked flag remains false (GET param only affects local variable) |
| R1-4 | Pattern catalog is seeded on first request | Empty session, empty patterns directory | handle() called | Pattern catalog contains all 19 patterns from Appendix A |

### Group R2 — Session State Loading

Tests for loading state from session.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R2-1 | Load core from session | Session contains serialized Core with 5 cells | handle() called with no action | Returned HTML displays those 5 cells |
| R2-2 | Load clocked state from session | Session `gol_clocked` = true, no GET clocked param | handle() called | Local $clocked variable is true |
| R2-3 | GET clocked param overrides session | Session `gol_clocked` = false, GET['clocked']='1' | handle() called | Local $clocked variable is true |
| R2-4 | GET clocked param and session both true | Session `gol_clocked` = true, GET['clocked']='1' | handle() called | Local $clocked variable is true |

### Group R3 — Clock Mode Behavior (CRITICAL - addresses reported bug)

Tests for clocked mode auto-refresh behavior. These tests verify that exactly one generation is computed per request.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R3-1 | Clocked mode with null action runs one generation | Session `gol_clocked`=true, GET has no action param | handle() called | Exactly one call to interpreter->interpret() with nextGenerationProgram |
| R3-2 | Clocked mode preserves clocked=1 in meta refresh URL | Session `gol_clocked`=true, GET['clocked']='1' | handle() called | Returned HTML contains `<meta http-equiv="refresh" ... url="?clocked=1">` |
| R3-3 | Clock on action sets session flag | GET['action']='clock_on', Session `gol_clocked`=false | handle() called | Session `gol_clocked` is set to true, NO generation is run |
| R3-4 | Clock off action clears session flag | GET['action']='clock_off', Session `gol_clocked`=true | handle() called | Session `gol_clocked` is set to false, NO generation is run |
| R3-5 | Clocked mode with action parameter does NOT auto-tick | Session `gol_clocked`=true, GET['action']='stamp', GET['clocked']='1' | handle() called | Generation is NOT run (stamp program is run instead) |
| R3-6 | Clicking Clock On then refreshing runs generation | Session `gol_clocked`=true, GET['clocked']='1', no action | handle() called | Exactly ONE generation is computed |
| R3-7 | Multiple rapid clock refreshes each run one generation | Session `gol_clocked`=true | 3 consecutive handle() calls with GET['clocked']='1' | Each call runs exactly ONE generation, 3 total generations advanced |

### Group R4 — Action Handling

Tests for each supported action.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R4-1 | Tick action runs one generation | Session has core with N cells, GET['action']='tick' | handle() called | interpreter->interpret() called with nextGenerationProgram, core advanced by 1 generation |
| R4-2 | Step action runs one generation | Session has core with N cells, GET['action']='step' | handle() called | interpreter->interpret() called with nextGenerationProgram, core advanced by 1 generation |
| R4-3 | Reset action creates new core and seeds demo | GET['action']='reset' | handle() called | Core is replaced with initial core + demo scene, session updated |
| R4-4 | Stamp action adds pattern at position | GET['action']='stamp', GET['pattern']='blinker', GET['x']='10', GET['y']='20' | handle() called | interpreter->interpret() called with addPatternProgram, pattern added at (10,20) |
| R4-5 | Stamp action uses selected pattern when pattern not in GET | Session has selectedPattern='glider', GET['action']='stamp', GET['x']='5', GET['y']='5', no pattern param | handle() called | Pattern 'glider' is stamped at (5,5) |
| R4-6 | Stamp action with invalid position does nothing | GET['action']='stamp', GET['x']='invalid', GET['y']='invalid' | handle() called | No pattern is added, no error |
| R4-7 | Select pattern action updates selected pattern | GET['action']='select_pattern', GET['pattern']='glider' | handle() called | Session selectedPattern is set to 'glider' |
| R4-8 | Toggle status action flips UI state | GET['action']='toggle_status', Session statusVisible=false | handle() called | Session statusVisible is set to true |

### Group R5 — State Persistence

Tests for saving state between requests.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R5-1 | Core is saved after generation | GET['action']='tick' | handle() called | Session `gol_core` contains the new core with advanced generation |
| R5-2 | Core is saved after stamp | GET['action']='stamp', GET['pattern']='blinker', GET['x']='0', GET['y']='0' | handle() called | Session `gol_core` contains core with stamped pattern |
| R5-3 | Clocked flag persists across requests | GET['action']='clock_on' | handle() called, then new request with no action | Session `gol_clocked` remains true |
| R5-4 | Selected pattern persists across requests | GET['action']='select_pattern', GET['pattern']='glider' | handle() called, then new request with no action | Session selectedPattern remains 'glinker' |

### Group R6 — URL Generation (buildUrl and buildCleanUrl)

Tests for URL building in DisplayPort.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R6-1 | Clean URL with clocked=true includes clocked=1 | DisplayPort.clocked=true | buildCleanUrl() called | Returns '?clocked=1' |
| R6-2 | Clean URL with clocked=false has no clocked param | DisplayPort.clocked=false | buildCleanUrl() called | Returns '?' or '' |
| R6-3 | Clean URL with non-default pattern includes pattern | DisplayPort.clocked=false, selectedPattern='glider' | buildCleanUrl() called | Returns '?pattern=glider' |
| R6-4 | Action URL includes action parameter | buildUrl('step') called | Returns '?action=step' |
| R6-5 | Action URL preserves clocked=1 when clocked | DisplayPort.clocked=true, buildUrl('step') | Returns '?action=step&clocked=1' |
| R6-6 | Action URL preserves pattern when non-default | DisplayPort.selectedPattern='glider', buildUrl('stamp') | Returns URL containing 'pattern=glider' |

### Group R7 — Display Configuration

Tests for how the display is configured before rendering.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R7-1 | Display is configured with clocked state | Session `gol_clocked`=true | handle() called | DisplayPort.setClocked(true) is called before rendering |
| R7-2 | Display is configured with selected pattern | Session selectedPattern='glider' | handle() called | DisplayPort.setSelectedPattern('glider') is called before rendering |
| R7-3 | Display is configured with status visibility | Session statusVisible=true | handle() called | DisplayPort.setStatusVisible(true) is called before rendering |
| R7-4 | Display receives updated core cells | GET['action']='tick' | handle() called | DisplayPort.render() is called with the new cells |

### Group R8 — Edge Cases and Error Handling

Tests for edge cases and error conditions.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R8-1 | Unknown action is ignored | GET['action']='unknown_action' | handle() called | No error, no generation run, default behavior (display current state) |
| R8-2 | Missing x/y parameters for stamp | GET['action']='stamp', GET['pattern']='blinker', no x/y | handle() called | No pattern added, no error |
| R8-3 | Unknown pattern for stamp | GET['action']='stamp', GET['pattern']='nonexistent' | handle() called | Pattern store returns empty cells, no change to core |
| R8-4 | Empty GET parameters | GET is empty array | handle() called | Displays current state from session, no generation |
| R8-5 | Both clock_on action and clocked=1 in GET | GET['action']='clock_on', GET['clocked']='1' | handle() called | Session `gol_clocked` set to true, NO generation run |
| R8-6 | Clock off with clocked=1 in GET | GET['action']='clock_off', GET['clocked']='1' | handle() called | Session `gol_clocked` set to false, NO generation run |

### Group R9 — Complete Request Flows

End-to-end tests for common user flows.

| # | Test Case | Given | When | Then |
|---|---|---|---|---|
| R9-1 | Full clock cycle: on → refresh → refresh → off | Session empty | Clock On clicked, then 2 auto-refreshes, then Clock Off clicked | 2 generations advanced, clock turned off |
| R9-2 | Stamp in clocked mode | Session `gol_clocked`=true | Stamp pattern at position, then auto-refresh | Pattern is stamped, then 1 generation advances on refresh |
| R9-3 | Select pattern then stamp | Session empty | Select 'glider', then stamp at (10,10) | Pattern 'glider' is stamped at (10,10) |
| R9-4 | Multiple rapid actions | Session empty | Click Step, Step, Step in sequence | 3 generations advanced |

---

## 4. Test Implementation Strategy

### 4.1 Test Class Structure

```php
<?php

namespace GameOfLife\Classic\WebServerOnly\Tests;

use PHPUnit\Framework\TestCase;
use GameOfLife\Classic\WebServerOnly\Shell\RequestHandler;
use GameOfLife\Classic\WebServerOnly\Shell\Interpreter;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\DisplayPort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\StatePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\EventSourcePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\PatternStorePort;
use GameOfLife\Classic\Core\Core;

class RequestHandlerTest extends TestCase
{
    private RequestHandler $handler;
    private DisplayPort $displayPort;
    private StatePort $statePort;
    private EventSourcePort $eventSourcePort;
    private PatternStorePort $patternStorePort;
    private Interpreter $interpreter;

    protected function setUp(): void
    {
        $this->displayPort = $this->createMock(DisplayPort::class);
        $this->statePort = $this->createMock(StatePort::class);
        $this->eventSourcePort = $this->createMock(EventSourcePort::class);
        $this->patternStorePort = $this->createMock(PatternStorePort::class);
        $this->interpreter = $this->createMock(Interpreter::class);

        $this->handler = new RequestHandler(
            $this->interpreter,
            $this->displayPort,
            $this->patternStorePort,
            $this->statePort,
            $this->eventSourcePort,
            new ShellConfig(2.0, __DIR__.'/patterns', false)
        );
    }

    // Test methods go here
}
```

### 4.2 Mocking Strategy

- **StatePort**: Mock to return controlled session values and track writes
- **EventSourcePort**: Mock to return controlled GET parameter values
- **DisplayPort**: Mock to verify render/show/hide calls and track configuration
- **PatternStorePort**: Mock to return controlled pattern data
- **Interpreter**: Mock to verify program interpretation calls

### 4.3 Assertion Strategy

- Assert that interpreter->interpret() is called with expected Program type
- Assert that statePort methods (saveCore, setClocked, etc.) are called with expected values
- Assert that displayPort methods (render, show, hide, setClocked, etc.) are called with expected values
- Assert that returned HTML contains expected meta refresh tags (when applicable)
- Assert that no unexpected methods are called

---

## 5. Test Data Providers

Use PHPUnit data providers to avoid duplication:

```php
public static function clockModeProvider(): array
{
    return [
        'clocked from session' => [true, false, null],
        'clocked from GET' => [false, true, null],
        'clocked from both' => [true, true, null],
        'clocked with action' => [true, true, 'step'],
    ];
}
```

Where parameters are: (session_clocked, get_clocked, action)

---

## 6. Success Criteria

The test suite is complete when:

1. All test groups (R1-R9) are implemented with at least one test per test case
2. All tests pass with the current implementation
3. Tests cover all code paths in RequestHandler::handle()
4. Tests are deterministic and isolated (each test sets up its own mock state)
5. The test file follows PHPUnit best practices

---

## 7. Files to Create/Modify

| File | Action | Description |
|------|--------|-------------|
| `tests/RequestHandlerTest.php` | CREATE | New PHPUnit test class with all test cases |
| `composer.json` | MODIFY | Ensure PHPUnit is in require-dev |
| `phpunit.xml` or `phpunit.xml.dist` | CREATE | PHPUnit configuration file |

---

## 8. Decisions on Test Approach

| Question | Decision | Rationale |
|----------|----------|-----------|
| Should we test exact HTML output or method calls? | Test method calls on displayPort, not HTML string matching | More maintainable, decouples tests from rendering details |
| Single class or multiple test classes? | One test class with clear grouping via comments and data providers | Keeps tests cohesive, easier to navigate |
| Do we need integration tests for HTML? | No integration tests verifying HTML output | Unit tests focus on behavior, not visual rendering |

---

## 9. Open Questions

None - all test approach decisions have been made.

---

**Status:** PENDING APPROVAL
**Date:** 2026-10-20
**Next Step:** Await user approval, then proceed to Phase 2 (implementation)
