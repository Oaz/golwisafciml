# Plan: Rust Multi-Breed TerminalUI (v2) — Terminal Shell Implementation

**Task:** Implement the imperative shell for Conway's Game of Life, Version 2 (Multi-Breed) in Rust, evolving from the existing `rust/classic/TerminalUI` implementation. Uses **Ratatui 0.28** for terminal UI rendering, **rusqlite 0.31** for SQLite pattern persistence with breed support, and **crossterm 0.28** for terminal event handling.

**Location:** `rust/multibreed/TerminalUI/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

**Evolution Source:** This is an evolution of `rust/classic/TerminalUI` (v1) to v2 (multi-breed), following the spec's evolution checklist (§13.2). The classic implementation will be copied and modified according to the checklist.

---

## 0. Context and Scope

This plan covers the **imperative shell** for the **v2 Multi-Breed** Game of Life in Rust. It follows the Functional Core / Imperative Shell architecture from `specs.md` and evolves from the existing v1 TerminalUI implementation at `../classic/TerminalUI`.

### What is IN scope

- Complete Rust implementation of the **v2 imperative shell** as defined in `specs.md` §4
- Evolution from the existing v1 TerminalUI codebase by modification (not rewrite)
- Terminal UI using **Ratatui** library with **colored cell rendering**
- Pattern persistence using **rusqlite** with breed-aware SQLite schema (columns: name, x, y, breed)
- Full interpreter for the instruction protocol (§3) — **IDENTICAL to v1**
- All four ports: Display (Ratatui with colors), PatternStore (rusqlite with breed), EventSource (crossterm with breed keys), State (mutable Core reference)
- Event loop with both clock-driven and step-driven modes
- Startup: initialCore with status visible (v2), seed pattern catalog with breed support, seed demo scene with breeds, initial render
- User interaction: **NEW** breed selection (Up/Down keys), pattern selection via core state (Left/Right keys call `defineCurrentPatternProgram`), click-to-stamp with breed reference
- Demo scene: all 13 stamps from Appendix C applied at startup with reference breeds
- Pattern catalog: all 19 patterns from Appendix A (with breed data)
- Pattern authoring: save/remove/clear/names operations (v2 additions)

### What is OUT of scope (deliberately)

- Version 1 (monochrome) — already implemented at `../classic/TerminalUI`
- Functional core — already implemented at `../core/` (multibreed version)
- Any rewrite of the interpreter, instruction set, or shell wiring from v1
- Any additional instructions beyond the five defined in §3.1
- Desktop GUI or other platforms

---

## 1. Decisions (Phase 0 prerequisites)

| Category | Decision | Rationale |
|---|---|---|
| **Source Implementation** | Evolve from `rust/classic/TerminalUI` | Spec §13.1: "the shell never changes"; v2 is v1 edited, not rewritten |
| **TUI Library** | Ratatui 0.28 | Inherited from v1; current stable release, idiomatic Rust TUI framework |
| **SQLite Library** | rusqlite 0.31 with bundled feature | Inherited from v1; pure Rust SQLite, cross-platform |
| **Terminal Event Library** | crossterm 0.28 | Inherited from v1; compatible with Ratatui, provides events |
| **Pattern Store Schema** | Single table with columns: name TEXT, x INTEGER, y INTEGER, breed INTEGER | v2 addition: breed column for per-cell breed storage |
| **Database File** | `patterns.db` in the application directory | Inherited from v1; consistent with classic implementation |
| **Clock Interval (v2)** | 200ms (0.2s) | Reference value from Appendix F for v2 (v1 used 100ms) |
| **Display Geometry** | Same as v1: 80x24 minimum, cell rendered as single character | Inherited from v1; terminal-friendly |
| **Background Color** | White | v2 spec: status bar visible at startup, white background |
| **Cell Rendering** | Breed-based RGB colors from Appendix B mapped to terminal colors | v2 spec: breed 0-15 rendered with exact palette |
| **Color Mapping** | Map Appendix B RGB to Ratatui Color enum | Ratatui supports 256-color terminals; map RGB to closest xterm-256 color |

---

## 2. File and Module Layout

**Starting Point:** Copy the entire `rust/classic/TerminalUI` directory structure, then modify according to v2 requirements.

```
rust/multibreed/
└── TerminalUI/
    ├── Cargo.toml              # Binary crate manifest (update name, path to core)
    ├── src/
    │   ├── main.rs             # Entry point: initialize ports, seed catalog, seed demo, start event loop
    │   ├── app.rs              # Main App struct: holds shell state, coordinates between components
    │   ├── interpreter.rs      # Program interpreter: **IDENTICAL to v1**
    │   ├── ui/
    │   │   ├── mod.rs          # UI module root
    │   │   ├── render.rs       # Ratatui rendering: **MODIFIED** for breed colors
    │   │   ├── components.rs   # UI components: **MODIFIED** add breed selector
    │   │   └── palette.rs       # **NEW** Breed-to-color mapping (Appendix B)
    │   ├── store/
    │   │   ├── mod.rs          # PatternStore module root
    │   │   ├── sqlite.rs       # rusqlite-based PatternStore: **MODIFIED** for breed support
    │   │   └── catalog.rs      # Seed catalog: 19 patterns from Appendix A (**MODIFIED** with breed 0)
    │   ├── events/
    │   │   ├── mod.rs          # EventSource module root
    │   │   └── crossterm.rs    # crossterm-based event handling: **MODIFIED** for Up/Down breed keys
    │   ├── demo.rs             # Demo scene: **MODIFIED** for breed-aware stamping
    │   └── config.rs           # Shell configuration constants: **MODIFIED** for v2 defaults
    ├── tests/                  # Shell integration tests
    │   ├── interpreter_test.rs # Interpreter tests: **IDENTICAL to v1** (I6 if free monad used)
    │   ├── store_test.rs       # PatternStore tests: **MODIFIED** for breed round-trip
    │   └── integration_test.rs # End-to-end shell tests: **MODIFIED** for v2 features
    └── README.md               # Shell-specific documentation: **NEW** for v2
```

### File Responsibilities and Changes from v1

| File | v1 Responsibility | v2 Changes |
|------|-------------------|------------|
| `main.rs` | Application entry point | Add breed state initialization; status visible at startup |
| `app.rs` | Shell state container | Add `selected_breed: Breed`; update pattern selection to use `defineCurrentPatternProgram`; change Left/Right to call core operation |
| `interpreter.rs` | Program interpreter | **NO CHANGES** — interpreter is identical per spec §13.1 |
| `ui/mod.rs` | UI module exports | Add palette export |
| `ui/render.rs` | Display port | **MAJOR CHANGE**: Add colored rendering using breed palette; update status bar for v2 |
| `ui/components.rs` | UI widgets | Add `BreedSelector` widget; update status bar to show breed |
| `ui/palette.rs` | **NEW** | Breed (0-15) → Ratatui Color mapping per Appendix B RGB values |
| `store/mod.rs` | PatternStore module exports | Unchanged |
| `store/sqlite.rs` | PatternStore port | **MODIFIED**: Add breed column to schema; update get() to return Cells with breeds; add save/remove/clear/names authoring operations |
| `store/catalog.rs` | Pattern catalog data | **MODIFIED**: Store patterns with breed 0 (v2 patterns carry breed data) |
| `events/mod.rs` | EventSource module exports | Unchanged |
| `events/crossterm.rs` | EventSource port | **MODIFIED**: Add Up/Down key handling for breed selection |
| `demo.rs` | Demo scene seeding | **MODIFIED**: Use breed-aware reference cells with Appendix C breeds |
| `config.rs` | Configuration constants | **MODIFIED**: Update HELP_TEXT for v2 keys; add breed selector constants; clock interval to 200ms |
| `README.md` | Documentation | **NEW**: Document v2 features, evolution from v1, new key bindings |

---

## 3. Architecture

### 3.1 Shell State (App) — Changes from v1

**v1 App State:**
```rust
pub struct App {
    current_core: Core,
    pattern_names: Vec<String>,
    selected_pattern_idx: usize,
    is_clock_mode: bool,
    running: bool,
    status_text: Option<String>,
    current_cells: Cells,
    status_visible: bool,
}
```

**v2 App State (Evolution):**
```rust
pub struct App {
    current_core: Core,           // UNCHANGED: holds Core reference
    pattern_names: Vec<String>,  // UNCHANGED: cached from store
    // CHANGED: Pattern selection is now in core state, not shell config
    // But we keep selected_pattern_idx for UI display purposes
    selected_pattern_idx: usize,
    is_clock_mode: bool,         // UNCHANGED
    running: bool,               // UNCHANGED
    status_text: Option<String>, // UNCHANGED
    current_cells: Cells,        // UNCHANGED: Cells now contains Cell (with breed)
    status_visible: bool,        // UNCHANGED: but starts true in v2
    // NEW for v2:
    selected_breed: Breed,       // Currently selected breed (0-15) from UI selector
}
```

**Key Evolution Notes:**
- **Pattern selection promoted to core:** In v1, `selected_pattern_idx` was used directly at click time. In v2, Left/Right keys invoke `defineCurrentPatternProgram` to update `core.current_pattern`, and click uses the sentinel `"current"` which the core resolves. The shell still maintains `selected_pattern_idx` for UI display but doesn't use it for pattern resolution.
- **Breed selection stays shell-side:** The `selected_breed` is shell configuration only, used to build the reference cell at click time. It is **not** promoted to core state.
- **Click handler change:** In v1, click used `Position`. In v2, click builds a `Cell` with the `selected_breed` and passes it as the reference.

### 3.2 Pattern Selection Evolution

**v1 Flow:**
```
User presses Left/Right → shell updates selected_pattern_idx → 
User clicks → shell looks up pattern_names[selected_pattern_idx] → 
addPatternProgram(core, pattern_name, position)
```

**v2 Flow:**
```
User presses Left/Right → shell calls defineCurrentPatternProgram(core, pattern_name) → 
core.current_pattern updated → 
User presses Up/Down → shell updates selected_breed → 
User clicks → shell builds Cell(position, selected_breed) → 
addPatternProgram(core, "current", cell_reference)
```

**Note:** The pattern name for Left/Right is still derived from `pattern_names[selected_pattern_idx]` in the shell UI, but instead of storing it in shell state, we immediately call `defineCurrentPatternProgram` to update the core. The core then stores the name, and clicks use the sentinel.

### 3.3 Port Implementations — Changes

#### Display Port (Ratatui) — Modified for Colors

**v1 Display:**
```rust
pub struct Display {
    terminal: Terminal<CrosstermBackend<Stdout>>,
    width: u16,
    height: u16,
    view_x: i32,
    view_y: i32,
}

impl Display {
    pub fn render(&mut self, cells: &Cells) -> Result<()> { ... }
    // Monochrome: live cell = "█", dead cell = " "
}
```

**v2 Display:**
```rust
pub struct Display {
    terminal: Terminal<CrosstermBackend<Stdout>>,
    width: u16,
    height: u16,
    view_x: i32,
    view_y: i32,
}

impl Display {
    pub fn render(&mut self, cells: &Cells) -> Result<()> { ... }
    // Colored: Each live cell uses breed-based color from palette
    // Dead cell = " " with default/background color
}
```

**Color Mapping Strategy:**
- Ratatui supports:
  - 16 standard ANSI colors
  - 256-color palette (xterm-256)
  - RGB (true color) via `Color::Rgb(r, g, b)`
- We use `Color::Rgb` with the exact RGB values from Appendix B
- Terminal must support true color (most modern terminals do)
- Fallback: if RGB not supported, map to closest 256-color index

**Palette Module (NEW):**
```rust
// ui/palette.rs
use ratatui::style::Color;
use crate::gol_multibreed_core::Breed;

/// Returns the Ratatui Color for a given breed
pub fn breed_to_color(breed: Breed) -> Color {
    let rgb = breed_to_rgb(breed);
    Color::Rgb(rgb.0, rgb.1, rgb.2)
}

/// Returns RGB tuple (r, g, b) for a breed per Appendix B
fn breed_to_rgb(breed: Breed) -> (u8, u8, u8) {
    match breed.value() {
        0 => (127, 127, 127),
        1 => (127, 0, 0),
        2 => (0, 127, 0),
        3 => (0, 0, 127),
        4 => (255, 0, 0),
        5 => (255, 255, 0),
        6 => (63, 190, 255),
        7 => (63, 255, 190),
        8 => (0, 255, 0),
        9 => (190, 63, 255),
        10 => (0, 255, 255),
        11 => (190, 255, 63),
        12 => (0, 0, 255),
        13 => (255, 63, 190),
        14 => (255, 190, 63),
        15 => (255, 0, 255),
        _ => (127, 127, 127), // unreachable
    }
}
```

**Breed Selector UI (NEW):**
- Displayed in the status bar or as a separate widget
- Shows the currently selected breed as a colored square/block
- Up/Down arrow keys cycle through breeds 0-15
- The selector provides visual feedback of the current breed

#### PatternStore Port (rusqlite) — Modified for Breed Support

**v1 Schema:**
```sql
CREATE TABLE IF NOT EXISTS patterns (
    name TEXT NOT NULL,
    x INTEGER NOT NULL,
    y INTEGER NOT NULL
);
```

**v2 Schema:**
```sql
CREATE TABLE IF NOT EXISTS patterns (
    name TEXT NOT NULL,
    x INTEGER NOT NULL,
    y INTEGER NOT NULL,
    breed INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_patterns_name ON patterns(name);
```

**v2 PatternStore Operations:**
```rust
pub struct PatternStore {
    conn: rusqlite::Connection,
}

impl PatternStore {
    pub fn new(path: &str) -> Result<Self> { ... }
    
    // v1 operations (modified to handle breed):
    pub fn get(&self, name: &str) -> Result<Cells> { ... }
        // Returns Cells (HashSet<Cell> with breed)
    pub fn seed_catalog(&self) -> Result<()> { ... }
        // Insert patterns with breed 0 (Appendix A coordinates)
    
    // NEW v2 operations:
    pub fn save(&self, name: &str, cells: &Cells) -> Result<()> { ... }
    pub fn remove(&self, name: &str) -> Result<()> { ... }
    pub fn clear(&self) -> Result<()> { ... }
    pub fn names(&self) -> Result<Vec<String>> { ... }
        // Returns sorted list of pattern names
}
```

**get() Implementation:**
```rust
pub fn get(&self, name: &str) -> Result<Cells> {
    let mut stmt = self.conn.prepare(
        "SELECT x, y, breed FROM patterns WHERE name = ? ORDER BY x, y")?;
    let mut rows = stmt.query([name])?;
    
    let mut cells = Cells::new();
    while let Some(row) = rows.next()? {
        let x: i32 = row.get(0)?;
        let y: i32 = row.get(1)?;
        let breed_value: u8 = row.get(2)?;
        let breed = Breed::new(breed_value);
        let position = Position::new(x, y);
        cells.insert(Cell::new(position, breed));
    }
    Ok(cells)
}
```

**Seed Catalog:**
- All 19 patterns from Appendix A stored with breed = 0
- Coordinates already centered at (0,0) as in v1
- Each pattern cell: (name, x, y, 0)

**Authoring Operations:**
```rust
pub fn save(&self, name: &str, cells: &Cells) -> Result<()> {
    let tx = self.conn.transaction()?;
    
    // Remove existing pattern with this name
    tx.execute("DELETE FROM patterns WHERE name = ?", [name])?;
    
    // Insert all cells with breed
    for cell in cells {
        tx.execute(
            "INSERT INTO patterns (name, x, y, breed) VALUES (?, ?, ?, ?)",
            (name, cell.position.x, cell.position.y, cell.breed.value()),
        )?;
    }
    
    tx.commit()?;
    Ok(())
}

pub fn remove(&self, name: &str) -> Result<()> {
    self.conn.execute("DELETE FROM patterns WHERE name = ?", [name])?;
    Ok(())
}

pub fn clear(&self) -> Result<()> {
    self.conn.execute("DELETE FROM patterns", [])?;
    Ok(())
}

pub fn names(&self) -> Result<Vec<String>> {
    let mut stmt = self.conn.prepare("SELECT DISTINCT name FROM patterns ORDER BY name")?;
    let mut rows = stmt.query([])?;
    let mut names = Vec::new();
    while let Some(row) = rows.next()? {
        names.push(row.get::<_, String>(0)?);
    }
    Ok(names)
}
```

#### EventSource Port (crossterm) — Modified for Breed Keys

**v2 EventSource additions:**
```rust
// In events/crossterm.rs or app.rs key handler:

// NEW: Up/Down arrow keys for breed selection
KeyCode::Up => {
    // Cycle selected_breed up (0-15)
    app.selected_breed = Breed::new(app.selected_breed.value().wrapping_add(1));
}
KeyCode::Down => {
    // Cycle selected_breed down (0-15)
    app.selected_breed = Breed::new(app.selected_breed.value().wrapping_sub(1));
}

// MODIFIED: Left/Right now update core pattern via defineCurrentPatternProgram
KeyCode::Left => {
    if !app.pattern_names.is_empty() {
        if app.selected_pattern_idx == 0 {
            app.selected_pattern_idx = app.pattern_names.len() - 1;
        } else {
            app.selected_pattern_idx -= 1;
        }
        // NEW: Update core's current_pattern
        let pattern_name = &app.pattern_names[app.selected_pattern_idx];
        let program = define_current_pattern_program(&app.current_core, pattern_name);
        execute_program(program, app, display, store)?;
    }
}
KeyCode::Right => {
    if !app.pattern_names.is_empty() {
        app.selected_pattern_idx = (app.selected_pattern_idx + 1) % app.pattern_names.len();
        // NEW: Update core's current_pattern
        let pattern_name = &app.pattern_names[app.selected_pattern_idx];
        let program = define_current_pattern_program(&app.current_core, pattern_name);
        execute_program(program, app, display, store)?;
    }
}
```

**v2 Click Handler:**
```rust
// In app.rs handle_mouse_event:
fn handle_mouse_event(&mut self, mouse: MouseEvent) -> Result<()> {
    if let MouseEventKind::Down(MouseButton::Left) = mouse.kind {
        let (screen_col, screen_row) = (mouse.column, mouse.row);
        let (world_x, world_y) = screen_to_world(
            screen_col - 1,
            screen_row - 1,
            self.display.view_x,
            self.display.view_y,
        );

        // NEW for v2: Create Cell with selected breed as reference
        let position = Position::new(world_x, world_y);
        let reference = Cell::new(position, self.selected_breed);
        
        // Use sentinel "current" — core resolves to current_pattern
        let program = add_pattern_program(&self.app.current_core, "current", &reference);
        execute_program(program, &mut self.app, &mut self.display, &self.store)?;
    }
    Ok(())
}
```

#### State Port

Unchanged from v1: single mutable `current_core: Core` reference. The core in v2 has an additional `current_pattern` field, but this is handled by the core implementation, not the shell.

### 3.4 Interpreter — IDENTICAL to v1

**CRITICAL:** Per spec §13.1, the interpreter does NOT change between v1 and v2. The same `interpret()` function from v1 is used unchanged.

```rust
// interpreter.rs is COPIED from v1 WITHOUT MODIFICATION
pub fn interpret(program: Program, app: &mut App, 
                 display: &mut Display, store: &PatternStore) -> Result<()> {
    // Same implementation as v1
    // Handles all five instructions: ShowCells, DisplayMessage, HideMessage, 
    // GetPatternCells, Return
}
```

**v2 Program Types:**
- The Program enum in the v2 core is the same shape (five opcodes)
- `add_pattern_program` now accepts `Cell` instead of `Position` for reference
- `define_current_pattern_program` is a new core operation (v2 only)
- But these are core-level changes; the interpreter in the shell doesn't care

### 3.5 Event Loop — Modified for v2

```rust
pub fn run(&mut self) -> Result<()> {
    // Seed catalog
    self.store.seed_catalog()?;

    // Initialize app with status VISIBLE (v2 difference from v1)
    // v1: status_visible = false; v2: status_visible = true
    let mut app = App::new(self.store.get_pattern_names()?);
    app.current_core = app.current_core.with_status_visible(true);  // v2: visible at startup
    app.status_visible = true;

    // Seed demo scene with breeds (v2)
    let initial_core = Core::initial().with_status_visible(true);
    let (seeded_core, demo_cells) = seed_demo_scene(initial_core, &self.store)?;
    app.current_core = seeded_core;
    app.current_cells = demo_cells;

    // Initial render
    self.update_display()?;

    while self.app.running {
        match self.events.next_event()? {
            Event::Tick => {
                if self.app.is_clock_mode {
                    let program = next_generation_program(&self.app.current_core);
                    execute_program(program, &mut self.app, 
                                    &mut self.display, &self.store)?;
                    self.update_display()?;
                }
            }
            Event::Key(key) => {
                self.handle_key_event(key)?;  // Modified for breed keys
                self.update_display()?;
            }
            Event::Mouse(mouse) => {
                self.handle_mouse_event(mouse)?;  // Modified for Cell reference
                self.update_display()?;
            }
            Event::Resize(width, height) => {
                self.display.handle_resize(width, height)?;
                self.update_display()?;
            }
            Event::Quit => {
                self.app.running = false;
            }
        }
    }
    Ok(())
}
```

### 3.6 Startup Sequence — v2 Differences

**v1 Startup:**
1. Create terminal, enable raw mode
2. Initialize PatternStore
3. Seed catalog (19 patterns)
4. Create App with `status_visible = false`
5. Seed demo scene (13 stamps, positions only)
6. Initial render
7. Start event loop

**v2 Startup:**
1. Create terminal, enable raw mode
2. Initialize PatternStore with breed-aware schema
3. Seed catalog (19 patterns with breed 0)
4. Create App with `status_visible = true` **← v2: starts visible**
5. **NEW:** Initialize `selected_breed = Breed::new(0)`
6. Seed demo scene (13 stamps with reference breeds from Appendix C)
7. Initial render
8. Start event loop

---

## 4. Language-Specific Design Decisions (Rust Idioms)

### 4.1 Build Configuration

**Cargo.toml:**
```toml
[package]
name = "gol-multibreed-terminalui"
version = "0.1.0"
edition = "2021"
description = "Terminal UI shell for Conway's Game of Life (v2) - Multi-Breed"
license = "AGPL-3.0"

[[bin]]
name = "gol-multibreed-terminalui"
path = "src/main.rs"

[dependencies]
gol-multibreed-core = { path = "../core" }  # v2 core
ratatui = "0.28"
rusqlite = { version = "0.31", features = ["bundled"] }
crossterm = "0.28"
anyhow = "1.0"

[dev-dependencies]
tempfile = "3.0"
```

**Key Change:** Dependency on `gol-multibreed-core` instead of `gol-classic-core`.

### 4.2 Error Handling

Unchanged from v1: use `anyhow::Result` for shell-level errors.

### 4.3 Type Usage from v2 Core

**v2 Core Types:**
```rust
// From gol-multibreed-core:
use gol_multibreed_core::{
    Breed,           // NEW: breed type (0-15)
    Cell,           // NEW: Cell = { position: Position, breed: Breed }
    Cells,          // CHANGED: HashSet<Cell> instead of HashSet<Position>
    Core,           // CHANGED: has current_pattern field
    Position,       // UNCHANGED
    // Core operations:
    next_generation_program,
    add_pattern_program,    // CHANGED: takes &Cell instead of &Position
    switch_status_visibility_program,
    define_current_pattern_program,  // NEW: v2 only
    // Pure functions:
    status_message,
    translate,      // CHANGED: translates Cells (with breed) and cross-breeds
};
```

### 4.4 Coordinate and Breed Handling

**Position:** Same as v1 — i32 coordinates, unbounded.

**Cell:** New type in v2 — contains Position + Breed. The shell never accesses Cell fields directly; it passes Cells to the Display port which extracts position and breed for rendering.

**Cells:** Changed from `HashSet<Position>` to `HashSet<Cell>`. The display iterates over Cells and renders each at its position with its breed's color.

### 4.5 Color Rendering Strategy

**Approach:**
1. Ratatui's `Text` widget supports styled spans
2. The grid is rendered as a `Paragraph` with a multi-line string
3. Each character in the grid string corresponds to one cell
4. We use styled spans to color individual cells

**Implementation in render.rs:**
```rust
fn create_grid_buffer(
    cells: &Cells,
    area: Rect,
    view_x: i32,
    view_y: i32,
) -> Text<'static> {
    let mut lines = Vec::new();
    
    let content_height = area.height.saturating_sub(2);
    let content_width = area.width.saturating_sub(2);
    
    for row in 0..content_height {
        let world_y = view_y + (row as i32);
        let mut spans = Vec::new();
        
        for col in 0..content_width {
            let world_x = view_x + (col as i32);
            let pos = Position::new(world_x, world_y);
            
            // Find cell at this position with its breed
            if let Some(cell) = cells.iter().find(|c| c.position == pos) {
                let color = palette::breed_to_color(cell.breed);
                spans.push(Span::styled(
                    LIVE_CELL,
                    Style::default().fg(color),
                ));
            } else {
                spans.push(Span::raw(DEAD_CELL));
            }
        }
        lines.push(Line::from(spans));
    }
    
    Text::from(lines)
}
```

**Note:** This approach uses one Span per cell, which may have performance implications for very large visible areas. Alternative: build a string with ANSI color codes, but Ratatui's styled approach is more portable.

### 4.6 Status Bar Format — v2 Updates

**v2 Status Bar Content:**
```
┌────────────────────────────────────────────────────────┐
│                    [Grid content]                          │
├────────────────────────────────────────────────────────┤
│ 127 cells are alive    █ Pattern: blinker | Mode: clock | ↑↓:breed | ←→:pattern | Click:stamp │
└────────────────────────────────────────────────────────┘
```

- Left: status message (from DisplayMessage)
- Colored square: current selected breed (colored block character)
- Pattern name
- Mode (clock/step)
- Key hints updated for v2

**Breed Selector Display:**
```rust
fn create_breed_selector(breed: Breed) -> Span<'static> {
    let color = palette::breed_to_color(breed);
    Span::styled(
        " █ ",  // Space-padded block for visibility
        Style::default().fg(color),
    )
}
```

---

## 5. Testing Strategy

### 5.1 Core Tests

Already covered by `../core/tests.rs`. Shell does not re-test core logic.

### 5.2 Shell Unit Tests

| Test File | Scope | v2 Additions |
|-----------|-------|--------------|
| `interpreter_test.rs` | Interpreter | **UNCHANGED** — interpreter identical to v1 |
| `store_test.rs` | PatternStore | **MODIFIED**: Add breed round-trip tests; test authoring operations |
| `integration_test.rs` | End-to-end | **MODIFIED**: Test breed selection, colored rendering, demo scene with breeds |

**NEW v2 Tests:**

**store_test.rs additions:**
```rust
// Test breed round-trip
#[test]
fn test_breed_round_trip() {
    let store = create_test_store();
    
    let test_cells = Cells::from_iter([
        Cell::new(Position::new(0, 0), Breed::new(5)),
        Cell::new(Position::new(1, 0), Breed::new(10)),
    ]);
    
    store.save("test_breed", &test_cells).unwrap();
    let loaded = store.get("test_breed").unwrap();
    
    assert_eq!(loaded, test_cells);
}

// Test authoring operations
#[test]
fn test_pattern_authoring() {
    let store = create_test_store();
    
    let cells = Cells::from_iter([
        Cell::new(Position::new(0, 0), Breed::new(1)),
    ]);
    
    store.save("new_pattern", &cells).unwrap();
    assert!(store.names().unwrap().contains(&"new_pattern".to_string()));
    
    store.remove("new_pattern").unwrap();
    assert!(!store.names().unwrap().contains(&"new_pattern".to_string()));
}

// Test names are sorted
#[test]
fn test_names_sorted() {
    let store = create_test_store();
    store.seed_catalog().unwrap();
    let names = store.names().unwrap();
    assert!(names.windows(2).all(|w| w[0] <= w[1]));
}
```

**integration_test.rs additions:**
```rust
// Test breed selection with Up/Down keys
#[test]
fn test_breed_selection() {
    let mut app = create_test_app();
    
    assert_eq!(app.selected_breed.value(), 0);
    
    // Simulate Up key 5 times
    for _ in 0..5 {
        app.handle_key_event(create_up_key()).unwrap();
    }
    assert_eq!(app.selected_breed.value(), 5);
    
    // Simulate Down key 3 times
    for _ in 0..3 {
        app.handle_key_event(create_down_key()).unwrap();
    }
    assert_eq!(app.selected_breed.value(), 2);
}

// Test pattern selection via core
#[test]
fn test_pattern_selection_via_core() {
    let mut app = create_test_app();
    
    // Select second pattern via Left/Right
    app.handle_key_event(create_right_key()).unwrap();
    
    // Verify core's current_pattern was updated
    // (This requires inspecting core state, but shell shouldn't access fields directly)
    // Instead, verify that subsequent operations use the new pattern
}

// Test demo scene has correct breeds
#[test]
fn test_demo_scene_breeds() {
    let store = create_test_store_with_catalog();
    let initial_core = Core::initial().with_status_visible(true);
    let (seeded_core, cells) = seed_demo_scene(initial_core, &store).unwrap();
    
    // Check that cells contain the expected breeds from Appendix C
    // Stamp 1: hwss at (33, 20) with breed 1
    // Verify at least one cell at that region has breed 1
    let hwss_region = cells.iter()
        .filter(|c| c.position.x >= 33 && c.position.x < 47 && 
                       c.position.y >= 20 && c.position.y < 33);
    assert!(hwss_region.any(|c| c.breed.value() == 1));
}
```

### 5.3 Acceptance Criteria

1. ✅ Application starts with **white background** and **status bar visible** (v2)
2. ✅ All 19 catalog patterns available with breed support
3. ✅ All 13 demo stamps rendered at startup with correct breeds
4. ✅ Cells colored per Appendix B palette
5. ✅ **Breed selector visible** in status bar as colored square
6. ✅ **Up/Down keys cycle through breeds 0-15**
7. ✅ **Left/Right keys** call `defineCurrentPatternProgram` to update core state
8. ✅ **Click** adds pattern with selected breed as reference cell
9. ✅ `s` key toggles status visibility
10. ✅ `p` key toggles between clock and step modes
11. ✅ In clock mode, generations advance automatically every **200ms** (v2)
12. ✅ In step mode, Enter key advances one generation
13. ✅ `q` key exits cleanly (restores terminal state)
14. ✅ Terminal resize handled gracefully
15. ✅ Shell never accesses Core fields directly
16. ✅ PatternStore supports authoring (save/remove/clear/names)
17. ✅ README documents v2 features, evolution, new key bindings
18. ✅ Interpreter is **identical** to v1
19. ✅ Code compiles cleanly with no warnings
20. ✅ All shell tests pass

---

## 6. Deviations from Spec

| Spec Requirement | Implementation | Rationale |
|---|---|---|
| Clock interval | 200ms for v2 | Appendix F reference value for v2 (v1 was 100ms) |
| Background color | White | v2 spec §4.4: background changed from black to white |
| Status bar start | Visible | v2 spec §4.4: status bar starts visible |
| Breed color mapping | RGB via Ratatui `Color::Rgb` | Exact Appendix B colors; requires true-color terminal support |
| Pattern persistence | SQLite with breed column | Appropriate for terminal application; schema carries breed data |
| Click reference | Cell with selected breed | v2 spec: reference cell carries breed for cross-breeding |
| Pattern selection | Left/Right invoke defineCurrentPatternProgram | Pattern selection promoted to core state per spec |
| Breed selection | Shell-side only | Per spec: breed selection stays shell config, not core state |
| Demo scene | Applied via shell using addPatternProgram | Same approach as v1; uses breed-aware translation |

---

## 7. Evolution Checklist (v1 → v2)

### 7.1 What MUST NOT Change (per spec §13.1)

- [ ] Instruction set (five opcodes: ShowCells, DisplayMessage, HideMessage, GetPatternCells, Return)
- [ ] Program shape and semantics
- [ ] Interpreter implementation
- [ ] Shell wiring structure
- [ ] Life truth table (shouldLive)
- [ ] Neighbor function (8 Moore neighbors, memoized)
- [ ] Status message grammar (three-way text)
- [ ] Pattern catalog (19 patterns from Appendix A)
- [ ] Demo scene (13 stamps from Appendix C, same positions)
- [ ] Architecture: pure core, dumb shell, single mutation point, ports

### 7.2 What Changes (per spec §13.2)

- [x] **Cell gains breed**: Position → Cell { position, breed } + positionOf projection
- [x] **Cross-breeding**: cross_breed(a,b) = (a+b) mod 16, threaded through newborn cells and translation
- [x] **Neighbor counting strategy**: candidate-set filtering → persistent-map fold with (count, breed) entries
- [x] **Core state**: + currentPattern field, withCurrentPattern, currentPatternOf
- [x] **New core operation**: defineCurrentPatternProgram
- [x] **addPattern**: reference is now Cell; "current" sentinel resolves from core state
- [x] **Store**: read-only → authoring (save/remove/clear/names); schema adds breed column
- [x] **UI and rendering**: + breed/color selector with Appendix B palette; click creates Cell reference
- [x] **Tests**: Add breed-related tests; all v1 tests still pass

### 7.3 Implementation Checklist

- [ ] Copy `rust/classic/TerminalUI` to `rust/multibreed/TerminalUI`
- [ ] Update Cargo.toml: rename package, point to multibreed core
- [ ] Update all `gol_classic_core` imports to `gol_multibreed_core`
- [ ] Update config.rs: change clock interval to 200ms, update HELP_TEXT
- [ ] Add `selected_breed: Breed` to App struct
- [ ] Modify app.rs: initialize selected_breed, update key handlers for breed and pattern
- [ ] Create ui/palette.rs with breed_to_color mapping
- [ ] Modify ui/render.rs: use colored rendering with palette, update status bar
- [ ] Modify ui/components.rs: add breed selector display
- [ ] Modify store/sqlite.rs: add breed column, implement authoring operations
- [ ] Modify store/catalog.rs: store patterns with breed 0
- [ ] Modify demo.rs: use breed-aware reference cells from Appendix C
- [ ] Update events/crossterm.rs or app.rs: add Up/Down key handling for breed
- [ ] Update main.rs: set status_visible = true at startup
- [ ] Create README.md documenting v2 features
- [ ] Update tests: add breed tests, verify v1 tests still pass
- [ ] Clean build and test

---

## 8. Build and Run Instructions

**Build:**
```bash
cd rust
cargo build --release
```

**Run:**
```bash
cargo run --release -p gol-multibreed-terminalui
```

**Development:**
```bash
cd rust/multibreed/TerminalUI
cargo build
cargo test
```

**Database Location:**
- `patterns.db` created in current working directory on first run
- Contains breed-aware catalog patterns

---

## 9. Resolved Design Decisions

1. **Evolution approach:** Copy v1 codebase and modify, rather than writing from scratch. This ensures the interpreter and shell wiring remain identical.

2. **True color vs 256-color:** Use Ratatui's `Color::Rgb` with exact Appendix B values. This requires terminal support for true color. If not supported, the terminal will fallback gracefully.

3. **Breed selector location:** Display in the status bar as a colored square, between the status message and pattern name.

4. **Pattern selection behavior:** Left/Right keys immediately call `defineCurrentPatternProgram` to update core state, rather than storing selection in shell. The shell's `selected_pattern_idx` is only for UI display.

5. **Click reference:** When user clicks, create a `Cell` at the click position with the currently `selected_breed`, and pass it as the reference to `addPatternProgram` with the `"current"` sentinel.

6. **Demo scene with breeds:** Each of the 13 stamps from Appendix C has a reference position AND a reference breed. The stamp uses the breed-0 catalog pattern, but cross-breeds with the reference breed during translation, resulting in cells with the reference breed.

7. **Clock interval:** Changed from 100ms (v1) to 200ms (v2) per Appendix F.

8. **Status visibility:** Starts visible in v2 (per spec §4.4), compared to hidden in v1.

---

## 10. Next Steps

**Review this plan before I write any code.**

Once approved, implementation will proceed in the following order:

1. **Copy v1 codebase** to `rust/multibreed/TerminalUI`
2. **Update Cargo.toml** with v2 dependencies
3. **Update imports** from `gol_classic_core` to `gol_multibreed_core`
4. **Update config.rs** with v2 constants (clock interval, help text)
5. **Create palette.rs** with breed-to-color mapping
6. **Modify app.rs** to add selected_breed, update key handlers
7. **Modify interpreter.rs** — verify it's identical to v1 (no changes)
8. **Modify ui/render.rs** for colored rendering
9. **Modify store/sqlite.rs** for breed support and authoring
10. **Modify store/catalog.rs** to store patterns with breed 0
11. **Modify demo.rs** for breed-aware stamping
12. **Modify events/crossterm.rs or app.rs** for breed key handling
13. **Update main.rs** for v2 startup (status visible)
14. **Update tests** for v2 features
15. **Create README.md** for v2
16. **Final integration, build, and test**

---

*Plan written for rust/multibreed/TerminalUI - awaiting approval*
