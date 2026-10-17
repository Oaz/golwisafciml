# Game of Life - TerminalUI (v1 Monochrome)

This is the imperative shell implementation for Conway's Game of Life (v1) using:
- **Ratatui 0.28** for terminal UI rendering
- **rusqlite 0.31** with SQLite for pattern persistence
- **crossterm 0.28** for terminal event handling

## Build

```bash
cd rust/classic
cargo build --release
```

## Run

```bash
cargo run --release -p gol-classic-terminalui
```

## Development

```bash
cd rust/classic/TerminalUI
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
| `Left` | Previous pattern |
| `Right` | Next pattern |
| `Mouse Click` | Stamp selected pattern at click position |

## Features

- Full implementation of the v1 imperative shell per the specification
- 19 patterns from Appendix A in the pattern catalog
- 13 stamps from Appendix C in the demo scene (applied at startup)
- SQLite-based pattern persistence
- Monochrome rendering using block characters
- Clock mode (100ms interval) and step mode
- Graceful terminal resize handling
- Clean terminal state restoration on exit (including mouse mode)

## Architecture

The implementation follows the Functional Core / Imperative Shell architecture:

- **Core** (from `../core`): Pure functional implementation of Life rules, cell algebra, and program generation
- **Shell** (this crate): Imperative event loop with ports for Display (Ratatui), PatternStore (rusqlite), EventSource (crossterm), and State (mutable Core reference)

The shell never accesses Core fields directly - all state observation is through Program instructions.

## Implementation Notes

### Pattern Catalog

All 19 patterns from Appendix A are stored in SQLite with coordinates already centered at (0,0). The catalog is seeded automatically on first run.

### Demo Scene

The demo scene applies 13 stamps from Appendix C at startup, before the initial render. Each stamp is applied using the same `addPatternProgram` that user clicks use.

### Display

- Live cells rendered as `█` (U+2588 FULL BLOCK)
- Dead cells rendered as space
- Status bar shows cell count (when visible), selected pattern, and mode
- Grid is centered in the terminal

### Clock Mode

- V1 uses 100ms (0.1s) interval per Appendix F
- Clock mode auto-advances generations
- Step mode requires manual `Enter` key press

### Error Handling

- Port operations use `anyhow::Result` for errors
- Core operations are pure and cannot fail
- Errors are logged to stderr

## Dependencies

- Rust 2021 edition
- ratatui = "0.28"
- rusqlite = { version = "0.31", features = ["bundled"] }
- crossterm = "0.28"
- anyhow = "1.0"
- gol-classic-core = { path = "../core" }

## Database

- Database file: `patterns.db` created in current working directory
- Schema: Single table `patterns` with columns (name, x, y)
- Index: `idx_patterns_name` on the name column
