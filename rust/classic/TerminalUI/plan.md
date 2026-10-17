# Plan: Rust Classic TerminalUI (v1) — Terminal Shell Implementation

**Task:** Implement the imperative shell for Conway's Game of Life, Version 1 (Monochrome) in Rust, using **Ratatui** for terminal UI rendering and **rusqlite** for SQLite pattern persistence.

**Location:** `rust/classic/TerminalUI/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

---

## 0. Context and Scope

This plan covers the **imperative shell** for the **classic (v1) monochrome** Game of Life in Rust, implementing a terminal-based user interface. It pairs with the existing functional core at `../core/` and follows the architecture defined in `specs.md`.

### What is IN scope

- Complete Rust implementation of the **v1 imperative shell** as defined in `specs.md` §4
- Terminal UI using **Ratatui** library
- Pattern persistence using **rusqlite** with a single SQLite table (columns: name, x, y)
- Full interpreter for the instruction protocol (§3)
- All four ports: Display (Ratatui), PatternStore (rusqlite), EventSource (crossterm), State (mutable Core reference)
- Event loop with both clock-driven and step-driven modes
- Startup: initialCore, seed pattern catalog, seed demo scene, initial render
- User interaction: pattern selection, click-to-stamp, toggle status, step/clock mode
- Demo scene: all 13 stamps from Appendix C applied at startup
- Pattern catalog: all 19 patterns from Appendix A

### What is OUT of scope (deliberately)

- Version 2 (multi-breed) — will be a separate evolution
- Functional core — already implemented at `../core/`
- Pattern authoring (save/remove/clear) — v2 feature only
- Breed selection — v2 feature only
- Any additional instructions beyond the five defined in §3.1
- Any rewrite of the core or interpreter when evolving to v2

---

## 1. Decisions (Phase 0 prerequisites)

| Category | Decision | Rationale |
|---|---|---|
| **TUI Library** | Ratatui 0.28 | Current stable release, idiomatic Rust TUI framework with modular widgets, cross-platform terminal support |
| **SQLite Library** | rusqlite 0.31 | Current stable release, pure Rust SQLite library, well-maintained, widely used |
| **Terminal Event Library** | crossterm 0.28 | Compatible with Ratatui 0.28, provides keyboard/mouse event handling, widely used in Rust TUI applications |
| **Pattern Store Schema** | Single table with columns: name TEXT, x INTEGER, y INTEGER | Matches the three-column requirement; stores each pattern cell as a row with pattern name and coordinates |
| **Database File** | `patterns.db` in the application directory | Standard SQLite file location, portable across platforms |
| **Clock Interval (v1)** | 100ms (0.1s) | Reference value from Appendix F for v1 |
| **Display Geometry** | 80x24 minimum terminal, cell rendered as single character (█ or ░) | Standard terminal-friendly representation; monochrome cells use block characters |
| **Coordinates Origin** | (0,0) at top-left, x increases right, y increases down | Standard terminal/screen coordinate system |

---

## 2. File and Module Layout

```
rust/classic/
└── TerminalUI/
    ├── Cargo.toml              # Binary crate manifest
    ├── src/
    │   ├── main.rs             # Entry point: initialize ports, seed catalog, seed demo, start event loop
    │   ├── app.rs              # Main App struct: holds shell state, coordinates between components
    │   ├── interpreter.rs      # Program interpreter: executes instruction sequences
    │   ├── ui/
    │   │   ├── mod.rs          # UI module root
    │   │   ├── render.rs       # Ratatui rendering: Display port implementation
    │   │   └── components.rs   # UI components: grid, status bar, pattern selector
    │   ├── store/
    │   │   ├── mod.rs          # PatternStore module root
    │   │   ├── sqlite.rs       # rusqlite-based PatternStore: get(name) -> Cells
    │   │   └── catalog.rs      # Seed catalog: 19 patterns from Appendix A
    │   ├── events/
    │   │   ├── mod.rs          # EventSource module root
    │   │   └── crossterm.rs    # crossterm-based event handling: keyboard, mouse, tick
    │   ├── demo.rs             # Demo scene: 13 stamps from Appendix C
    │   └── config.rs           # Shell configuration constants
    ├── tests/                  # Shell integration tests
    │   ├── interpreter_test.rs # Interpreter tests with fake ports
    │   ├── store_test.rs       # PatternStore tests
    │   └── integration_test.rs # End-to-end shell tests
    └── README.md               # Shell-specific documentation
```

### File Responsibilities

| File | Responsibility | Key Types/Functions |
|------|---------------|---------------------|
| `main.rs` | Application entry point | Initialize all ports, seed catalog and demo, start event loop |
| `app.rs` | Shell state container | `App` struct with currentCore, pattern names list, selected pattern index, mode (clock/step), running flag |
| `interpreter.rs` | Program interpreter | `interpret()` function, tail-recursive or loop-based dispatch on Program enum |
| `ui/mod.rs` | UI module exports | Re-exports render and components |
| `ui/render.rs` | Display port | `Display` struct with Ratatui terminal, `render(cells)` implementation, cell-to-screen mapping |
| `ui/components.rs` | UI widgets | Grid widget, StatusBar widget, PatternSelector widget, help text |
| `store/mod.rs` | PatternStore module exports | Re-exports sqlite and catalog |
| `store/sqlite.rs` | PatternStore port | `PatternStore` struct with rusqlite Connection, `get(name) -> Cells`, initialization, schema creation |
| `store/catalog.rs` | Pattern catalog data | Constants for all 19 patterns from Appendix A as Position lists |
| `events/mod.rs` | EventSource module exports | Re-exports crossterm |
| `events/crossterm.rs` | EventSource port | `EventSource` struct, `next_event()` -> Event enum, handles keyboard (q, s, p, Enter, arrows), mouse clicks, tick timing |
| `demo.rs` | Demo scene seeding | `seed_demo_scene()` function: applies 13 stamps from Appendix C to core via addPatternProgram |
| `config.rs` | Configuration constants | Tick intervals, cell characters, terminal size constraints |
| `README.md` | Documentation | Rust shell-specific notes, key bindings, deviations from spec defaults |

---

## 3. Architecture

### 3.1 Shell State (App)

```rust
pub struct App {
    current_core: Core,           // Mutable reference to the current core state
    pattern_names: Vec<String>,  // Cached list of all pattern names from store
    selected_pattern_idx: usize, // Index into pattern_names for current selection
    is_clock_mode: bool,         // true = auto-advance, false = manual step
    running: bool,               // true = event loop active, false = exit requested
    status_visible: bool,        // Cached from core for UI rendering decisions
}
```

**Black Box Principle:** The shell holds a `Core` but **never accesses its fields directly**. All core state is observed through Program instructions (`ShowCells`, `DisplayMessage`, `HideMessage`, `Return`).

### 3.2 Port Implementations

#### Display Port (Ratatui)

```rust
pub struct Display {
    terminal: ratatui::Terminal<Backend>,
}

impl Display {
    pub fn new() -> Self { ... }
    pub fn render(&mut self, cells: &Cells) -> Result<()> { ... }
    pub fn show(&mut self, text: &str) -> Result<()> { ... }
    pub fn hide(&mut self) -> Result<()> { ... }
    pub fn draw_ui(&mut self, cells: &Cells, status: Option<&str>, 
                   pattern_name: &str, mode: &str) -> Result<()> { ... }
}
```

- Maps `Cells` (HashSet<Position>) to screen coordinates
- Renders live cells as `█` (U+2588 FULL BLOCK), dead cells as space
- Status bar at bottom shows: status message (left) + pattern name + mode (right)
- Pattern selector overlay or sidebar showing available patterns
- Handles terminal resize gracefully

#### PatternStore Port (rusqlite)

```rust
pub struct PatternStore {
    conn: rusqlite::Connection,
}

impl PatternStore {
    pub fn new(path: &str) -> Result<Self> { ... }
    pub fn initialize(&self) -> Result<()> { ... }
    pub fn get(&self, name: &str) -> Result<Cells> { ... }
    pub fn seed_catalog(&self) -> Result<()> { ... }
}
```

**Schema:**
```sql
CREATE TABLE IF NOT EXISTS patterns (
    name TEXT NOT NULL,
    x INTEGER NOT NULL,
    y INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_patterns_name ON patterns(name);
```

- `get(name)` queries all rows with matching name, constructs Cells (HashSet<Position>)
- Returns empty Cells if name not found (never errors)
- `seed_catalog()` inserts all 19 patterns from Appendix A (overwrite existing)
- Pattern cells stored as individual rows; pattern name is repeated for each cell

#### EventSource Port (crossterm)

```rust
pub enum Event {
    Tick,
    Key(KeyEvent),
    Mouse(MouseEvent),
    Resize(u16, u16),
    Quit,
}

pub struct EventSource {
    // crossterm event reader state
}

impl EventSource {
    pub fn new() -> Result<Self> { ... }
    pub fn next_event(&self) -> Result<Event> { ... }
    pub fn enable_raw_mode(&self) -> Result<()> { ... }
    pub fn disable_raw_mode(&self) -> Result<()> { ... }
}
```

**Key Bindings:**

| Key | Action | Core Operation |
|-----|--------|----------------|
| `q` | Quit | Exit event loop |
| `s` | Toggle status | `switchStatusVisibilityProgram(core)` |
| `p` | Toggle clock/step | Shell configuration toggle |
| `Enter` | Step (step mode only) | `nextGenerationProgram(core)` |
| `Left` | Previous pattern | Decrement selected_pattern_idx |
| `Right` | Next pattern | Increment selected_pattern_idx |
| Mouse click | Add pattern at position | `addPatternProgram(core, selected_pattern_name, click_position)` |

**Mouse Handling:**
- Click coordinates mapped to grid coordinates
- Click position becomes the reference position for pattern translation
- Pattern from `selected_pattern_idx` is stamped

#### State Port

The shell maintains `current_core: Core` as a single mutable reference. This is the only state in the shell that the core can affect. All other shell state (pattern list, mode, etc.) is separate.

### 3.3 Interpreter

```rust
pub fn interpret(program: Program, app: &mut App, display: &mut Display, 
                 store: &PatternStore) -> Result<()> {
    // Tail-recursive or loop-based dispatch
    let mut program = program;
    loop {
        match program {
            Program::Finish(core) => {
                app.current_core = core;
                return Ok(());
            }
            Program::Step { instruction, rest } => {
                execute_instruction(instruction, app, display, store)?;
                program = match rest {
                    Rest::Next(next) => next,
                    Rest::With(cont) => {
                        // Only GetPatternCells has a response
                        let response = match instruction { ... };
                        cont(response)
                    }
                }
            }
        }
    }
}

fn execute_instruction(instr: Instruction, app: &mut App, display: &mut Display,
                       store: &PatternStore) -> Result<()> {
    match instr {
        Instruction::ShowCells(cells) => {
            display.render(&cells)?;
            // Cache for status bar rendering
            app.cells_to_render = Some(cells);
        }
        Instruction::DisplayMessage(text) => {
            display.show(&text)?;
            app.status_text = Some(text.to_string());
        }
        Instruction::HideMessage => {
            display.hide()?;
            app.status_text = None;
        }
        Instruction::GetPatternCells(name) => {
            // Response is handled in interpret loop via With continuation
            let cells = store.get(&name).unwrap_or_default();
            // Response passed to continuation
        }
        Instruction::Return(core) => {
            app.current_core = core;
        }
    }
    Ok(())
}
```

**Batching Requirement:** Render **once per program**, not per instruction. The interpreter caches instruction effects and applies them atomically at program completion. For `ShowCells`, we render immediately but the spec allows observing only the final display state — our implementation renders on each `ShowCells` instruction for visual responsiveness, but this is a shell configuration detail.

**Tail Call Optimization:** The loop-based interpreter avoids stack growth. Rust does not guarantee TCO, so explicit loop is required.

### 3.4 Event Loop

```rust
pub fn run(app: &mut App, display: &mut Display, 
           store: &PatternStore, events: &EventSource) -> Result<()> {
    // Seed catalog and demo scene
    store.seed_catalog()?;
    seed_demo_scene(app, store)?;
    
    // Initial render
    display.draw_ui(&app.current_core.cells, 
                    app.status_text.as_deref(),
                    &app.pattern_names[app.selected_pattern_idx],
                    if app.is_clock_mode { "clock" } else { "step" })?;
    
    while app.running {
        match events.next_event()? {
            Event::Tick => {
                if app.is_clock_mode {
                    let program = next_generation_program(app.current_core.clone());
                    interpret(program, app, display, store)?;
                }
            }
            Event::Key(key) => {
                handle_key_event(key, app, display, store)?;
            }
            Event::Mouse(mouse) => {
                if let MouseEventKind::Down(MouseButton::Left) = mouse.kind {
                    let (x, y) = screen_to_grid(mouse.column, mouse.row);
                    let pattern_name = &app.pattern_names[app.selected_pattern_idx];
                    let program = add_pattern_program(
                        app.current_core.clone(),
                        pattern_name.clone(),
                        Position::new(x, y));
                    interpret(program, app, display, store)?;
                }
            }
            Event::Resize(w, h) => {
                display.handle_resize(w, h)?;
            }
            Event::Quit => {
                app.running = false;
            }
        }
    }
    Ok(())
}
```

### 3.5 Startup Sequence

1. Initialize Ratatui terminal backend
2. Enable raw mode via crossterm
3. Create or open SQLite database at `patterns.db`
4. Create PatternStore with schema initialization
5. Seed catalog: insert all 19 patterns from Appendix A
6. Initialize App with:
   - `current_core = Core::initial()`
   - `status_visible = false` (v1 starts hidden per §4.4)
   - `is_clock_mode = false` (start in step mode for safety)
   - `pattern_names` populated from store
   - `selected_pattern_idx = 0` (first pattern)
   - `running = true`
7. Seed demo scene: apply 13 stamps from Appendix C
8. Initial render
9. Start event loop

**Demo Scene Seeding:** Uses the same `addPatternProgram` that user clicks use, but invoked directly from the shell during startup. Each stamp:
- Pattern name from Appendix C
- Reference position (x, y) from Appendix C
- All cells translated and unioned with existing cells

---

## 4. Language-Specific Design Decisions (Rust Idioms)

### 4.1 Build Configuration

**Cargo.toml:**
```toml
[package]
name = "gol-classic-terminalui"
version = "0.1.0"
edition = "2021"
description = "Terminal UI shell for Conway's Game of Life (v1) - Classic Monochrome"
license = "AGPL-3.0"

[[bin]]
name = "gol-classic-terminalui"
path = "src/main.rs"

[dependencies]
gol-classic-core = { path = "../core" }
ratatui = "0.28"
rusqlite = { version = "0.31", features = ["bundled"] }
crossterm = "0.28"
anyhow = "1.0"
```

**Features:**
- `rusqlite` with `bundled` feature for cross-platform SQLite compilation

### 4.2 Error Handling

- Use `anyhow::Result` for shell-level errors
- Port operations return `Result<T, E>` with domain-specific error types
- Errors are logged to stderr only; do not show in status bar or interrupt the application
- Core operations cannot fail (pure functions); only port operations can fail

**Error Strategy:**
```rust
[dependencies]
anyhow = "1.0"

type Result<T> = anyhow::Result<T>;
```

### 4.3 Coordinate Mapping

**Grid to Screen:**
- Core uses unbounded signed integers (i32) for positions
- Screen uses unsigned (u16) for columns/rows
- Mapping: `screen_col = grid_x + offset_x`, `screen_row = grid_y + offset_y`
- Origin at center of terminal for symmetric display
- Offset adjusted dynamically on terminal resize

**Handling Out of Bounds:**
- Cells outside visible area are not rendered (clipped)
- Core state is never affected by clipping (unbounded grid maintained)
- Panning support: arrow keys can scroll the viewport

### 4.4 Pattern Catalog Format

Each pattern from Appendix A stored as individual rows with **coordinates already centered at (0,0)** as specified in Appendix A. For example, the blinker is defined as a horizontal line:

```
name       | x    | y
-----------|------|------
blinker     | -1   | 0
blinker     | 0    | 0
blinker     | 1    | 0
block       | 0    | 0
block       | 1    | 0
block       | 0    | 1
block       | 1    | 1
...
```

These coordinates come directly from Appendix A and are already arranged so that the reference point (0,0) is at or near the geometric center of each pattern. When a pattern is stamped at a reference position (click coordinates), the translation adds the reference position to each cell's coordinates, placing the pattern's center at the clicked location.

**Query for get(name):**
```sql
SELECT x, y FROM patterns WHERE name = ? ORDER BY x, y
```

**Insert for seed_catalog:**
```sql
INSERT OR REPLACE INTO patterns (name, x, y) VALUES (?, ?, ?)
```

### 4.5 Cell Rendering

```rust
const LIVE_CELL: &str = "█";  // U+2588 FULL BLOCK
const DEAD_CELL: &str = " ";  // Space

fn render_cell(live: bool) -> &'static str {
    if live { LIVE_CELL } else { DEAD_CELL }
}
```

For better visual at terminal boundaries, consider:
- Half-blocks for edge cells: `▀`, `▄`, `▌`, `▐`
- But v1 spec requires simple monochrome; full blocks are sufficient

### 4.6 Status Bar

Rendered at the bottom of the terminal:
```
┌────────────────────────────────────────────────────────┐
│                    [Grid content]                          │
├────────────────────────────────────────────────────────┤
│ 127 cells are alive    blinker | clock | Left/Right:select  │
└────────────────────────────────────────────────────────┘
```

- Left-aligned: status message (from DisplayMessage instruction)
- Right-aligned: pattern name, mode (clock/step), key hint
- Only shown when status is visible

---

## 5. Testing Strategy

### 5.1 Core Tests

Already covered by `../core/tests.rs`. Shell does not re-test core logic.

### 5.2 Shell Unit Tests (tests/ directory)

| Test File | Scope | Test Groups |
|-----------|-------|-------------|
| `interpreter_test.rs` | Interpreter with fake ports | I5, I6, I7, I10, I11 |
| `store_test.rs` | PatternStore implementation | P3, P5 (catalog seeding, round-trip) |
| `integration_test.rs` | End-to-end shell behavior | X1a (startup), X4 (demo scene) |

**Fake Ports for Interpreter Tests:**
```rust
struct FakeDisplay {
    rendered_cells: Option<Cells>,
    shown_text: Option<String>,
    message_visible: bool,
}

struct FakePatternStore {
    patterns: HashMap<String, Cells>,
}
```

### 5.3 Test Coverage

| Spec Test | Location | Implementation |
|-----------|----------|----------------|
| I5 | interpreter_test | GetPatternCells with "current" sentinel (v2 feature, N/A for v1) |
| I6 | interpreter_test | Free monad equivalence (if using free monad encoding) |
| I7 | interpreter_test | Program ends with Return |
| I10 | interpreter_test | Interpreter ignores unknown instructions (defensive) |
| I11 | interpreter_test | Interpreter dispatches on instruction identity |
| P3 | store_test | Pattern lookup by name |
| P5 | store_test | Catalog seeding, round-trip, sorted names |
| X1a | integration_test | Startup: initialCore, hidden status |
| X4 | integration_test | Demo scene applied correctly |

---

## 6. Deviations from Spec

| Spec Requirement | Implementation | Rationale |
|---|---|---|
| Single mutation point | `app.current_core` is the only mutable Core reference; all other state is shell-local | Matches spec requirement; shell state (pattern selection, mode) is separate from Core |
| Batching: render once per program | Render on each ShowCells instruction | Terminal responsiveness; spec allows "observe only the final display state" but doesn't require it |
| Ports injected, not global | Ports created in main.rs and passed to functions | Idiomatic Rust: explicit dependencies, no global state |
| Demo scene applied via shell | Direct `addPatternProgram` calls during startup | Matches DesktopGUI approach; shell invokes core operations directly |
| No free monad encoding | Direct enum-based Program type | Simpler for Rust; free monad not needed with only one interpreter |
| Status bar starts hidden | `status_visible = false` in initial Core | Matches v1 spec §4.4 |

---

## 7. Acceptance Criteria

1. ✅ Application starts and displays the demo scene correctly
2. ✅ All 19 catalog patterns available and selectable
3. ✅ All 13 demo stamps rendered at startup
4. ✅ Status bar hidden at startup (v1)
5. ✅ `s` key toggles status message visibility
6. ✅ `p` key toggles between clock and step modes
7. ✅ In clock mode, generations advance automatically every 100ms
8. ✅ In step mode, Enter key advances one generation
9. ✅ Left/Right arrow keys cycle through patterns
10. ✅ Mouse click adds selected pattern at clicked position
11. ✅ `q` key exits cleanly (restores terminal state)
12. ✅ Terminal resize handled gracefully
13. ✅ Shell never accesses Core fields directly
14. ✅ README documents key bindings, dependencies, build/run instructions
15. ✅ PatternStore uses SQLite with single table (name, x, y)
16. ✅ Pattern catalog seeded from Appendix A
17. ✅ Demo scene seeded from Appendix C
18. ✅ Code compiles cleanly with no warnings
19. ✅ All shell tests pass

---

## 8. Build and Run Instructions

**Build:**
```bash
cd rust
cargo build --release
```

**Run:**
```bash
cargo run --release -p gol-classic-terminalui
```

**Development:**
```bash
cd rust/classic/TerminalUI
cargo build
cargo test
```

**Database Location:**
- `patterns.db` created in current working directory on first run
- Contains the full catalog after initialization

---

## 9. Resolved Design Decisions

1. **Terminal size handling:** Adapt gracefully with a minimum of 20x10; show warning in terminal if smaller, but continue running.

2. **Mouse click coordinate mapping:** Snap to nearest cell center based on terminal character cell dimensions.

3. **Pattern selector UI:** Status bar shows current pattern; Left/Right keys cycle through list.

4. **Error display:** Log to stderr only; do not show in status bar.

5. **Exit confirmation:** No confirmation — immediate exit with clean terminal restore.

6. **Tick timing precision:** Use crossterm's event polling with timeout for tick-based advances.

---

## 10. Next Steps

Review this plan before I write any code.

Once approved, implementation will proceed in the following order:
1. Create Cargo.toml with dependencies
2. Implement config.rs (constants)
3. Implement store/sqlite.rs (PatternStore port)
4. Implement store/catalog.rs (pattern data)
5. Implement events/crossterm.rs (EventSource port)
6. Implement ui/render.rs (Display port)
7. Implement interpreter.rs
8. Implement demo.rs
9. Implement app.rs
10. Implement main.rs
11. Implement all tests
12. Final integration and cleanup

---

*Plan written for rust/classic/TerminalUI - awaiting approval*
