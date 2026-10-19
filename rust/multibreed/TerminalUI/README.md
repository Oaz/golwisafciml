# Game of Life - TerminalUI (v2 Multi-Breed)

This is the imperative shell implementation for Conway's Game of Life (v2 Multi-Breed) using:
- **Ratatui 0.28** for terminal UI rendering with breed-based colors
- **rusqlite 0.31** with SQLite for pattern persistence with breed support
- **crossterm 0.28** for terminal event handling

This is an evolution of the v1 TerminalUI implementation, following the spec's evolution checklist (§13.2).

## Build

```bash
cd rust/multibreed
cargo build --release
```

## Run

```bash
cargo run --release -p gol-multibreed-terminalui
```

## Development

```bash
cd rust/multibreed/TerminalUI
cargo build
cargo test
```

## Key Bindings

| Key | Action |
|-----|--------|
| `q` | Quit |
| `s` | Toggle status bar visibility |
| `p` | Toggle clock/step mode |
| `Enter` | Advance one generation (step mode only) |
| `↑` (Up) | Cycle to next breed (0-15) |
| `↓` (Down) | Cycle to previous breed (0-15) |
| `←` (Left) | Previous pattern (updates core state) |
| `→` (Right) | Next pattern (updates core state) |
| `h` | Show help |
| `Mouse Click` | Stamp selected pattern at click position with selected breed as reference |

## Features

### v2 Multi-Breed Evolution

- **Colored cells**: Each breed (0-15) is rendered with its exact RGB color from Appendix B using Ratatui's true color support
- **Breed selection**: Up/Down arrow keys cycle through breeds 0-15, shown as a colored square in the status bar
- **Cross-breeding**: Patterns are stamped with a reference cell that includes the selected breed, enabling cross-breeding
- **Pattern selection promotion**: Left/Right keys now call `defineCurrentPatternProgram` to update core state
- **White background**: v2 spec requires white background for the status bar
- **Status visible at startup**: v2 spec requires status bar to be visible at startup
- **Clock interval**: 200ms (0.2s) per Appendix F for v2

### Core Features (from v1)

- Full implementation of the v2 imperative shell per the specification
- 19 patterns from Appendix A in the pattern catalog (stored with breed 0)
- 13 stamps from Appendix C in the demo scene (applied at startup with reference breeds)
- SQLite-based pattern persistence with breed column
- Clock mode (200ms interval) and step mode
- Graceful terminal resize handling
- Clean terminal state restoration on exit (including mouse mode)

### New v2 Authoring Operations

The PatternStore now supports pattern authoring operations:
- `save(name, cells)` - Save a pattern with its cells and breeds
- `remove(name)` - Remove a pattern by name
- `clear()` - Remove all patterns
- `names()` - Get sorted list of all pattern names

## Architecture

The implementation follows the Functional Core / Imperative Shell architecture:

- **Core** (from `../core`): Pure functional implementation of Life rules, cell algebra with breeds, and program generation
- **Shell** (this crate): Imperative event loop with ports for:
  - Display (Ratatui with breed colors)
  - PatternStore (rusqlite with breed support)
  - EventSource (crossterm with breed key handling)
  - State (mutable Core reference)

The shell never accesses Core fields directly - all state observation is through Program instructions.

### Key Differences from v1

| Feature | v1 | v2 |
|---------|----|----|
| Cell type | Position | Cell { position, breed } |
| Pattern store schema | (name, x, y) | (name, x, y, breed) |
| Click reference | Position | Cell with breed |
| Pattern selection | Shell-side index | Core-side current_pattern |
| Click sentinel | pattern_name | "current" |
| Breed selection | N/A | Shell-side selected_breed |
| Status at startup | Hidden | Visible |
| Background color | Dark | White |
| Clock interval | 100ms | 200ms |
| Interpreter | Monochrome | IDENTICAL (spec §13.1) |

## Implementation Notes

### Pattern Catalog

All 19 patterns from Appendix A are stored in SQLite with coordinates already centered at (0,0). Each pattern cell has breed 0. The catalog is seeded automatically on first run.

### Demo Scene

The demo scene applies 13 stamps from Appendix C at startup, before the initial render. Each stamp:
1. Gets the pattern cells from the catalog (breed 0)
2. Uses a reference cell with the stamp's reference breed from Appendix C
3. Translates using cross-breeding to produce cells with the reference breed

### Display

- Live cells rendered as `█` (U+2588 FULL BLOCK) with breed-based colors
- Dead cells rendered as space
- Status bar shows cell count (when visible), selected pattern, mode, and breed selector
- Grid is centered in the terminal with white background
- Breed selector displayed as colored square in status bar

### Color Mapping

Uses exact RGB values from Appendix B via Ratatui's `Color::Rgb`:
- Breed 0: RGB(127, 127, 127) - Gray
- Breed 1: RGB(127, 0, 0) - Dark Red
- Breed 2: RGB(0, 127, 0) - Dark Green
- Breed 3: RGB(0, 0, 127) - Dark Blue
- ... (all 16 breeds defined)

Requires terminal support for true color (24-bit). Most modern terminals support this.

### Clock Mode

- V2 uses 200ms (0.2s) interval per Appendix F
- Clock mode auto-advances generations
- Step mode requires manual `Enter` key press

### Error Handling

- Port operations use `anyhow::Result` for errors
- Core operations are pure and cannot fail
- Errors are logged to stderr

## Testing

Run the test suite:
```bash
cd rust/multibreed/TerminalUI
cargo test
```

Tests cover:
- Interpreter (identical to v1)
- PatternStore (breed round-trip, authoring operations)
- Integration (breed selection, demo scene with breeds)

## Dependencies

- Rust 2021 edition
- ratatui = "0.28"
- rusqlite = { version = "0.31", features = ["bundled"] }
- crossterm = "0.28"
- anyhow = "1.0"
- im = "15.1" (for HashMap in Cells type)
- gol-multibreed-core = { path = "../core" }

## Database

- Database file: `patterns.db` created in current working directory
- Schema: Single table `patterns` with columns (name TEXT, x INTEGER, y INTEGER, breed INTEGER)
- Index: `idx_patterns_name` on the name column
- Seeded with 19 patterns from Appendix A on first run
