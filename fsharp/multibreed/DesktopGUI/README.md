# Game of Life - Multi-Breed DesktopGUI

This is the **v2 Multi-Breed DesktopGUI** implementation of Conway's Game of Life for the **golwisafciml** project.

## Overview

This implementation follows the **Functional Core / Imperative Shell** architecture:

- **Functional Core**: `fsharp/multibreed/core/` - Pure game logic with breed support
- **Imperative Shell**: This folder - GUI application using DIKU.Canvas

### What's New in v2 (Multi-Breed)

Compared to v1 (Classic):

1. **Breeds**: Each cell carries a breed value (0-15), rendered as a color per Appendix B
2. **Colored rendering**: Cells are colored by breed instead of monochrome
3. **Breed selector**: UI element in bottom-right showing selected breed as color square
4. **Breed selection**: Up/Down arrow keys cycle through breeds 0-15
5. **Pattern selection via core**: Left/Right keys call `defineCurrentPatternProgram` to update core state
6. **White background**: Changed from black to white
7. **Status bar visible at startup**: v2 requirement
8. **Reference cells**: Click creates a Cell with selected breed, used for cross-breeding during translation

### Architecture Invariants

- **Interpreter**: IDENTICAL to v1 - unchanged
- **Instruction set**: IDENTICAL to v1 - 5 opcodes, no new instructions
- **Shell wiring**: IDENTICAL to v1 - same event loop structure
- **Core never accessed directly**: Shell only holds Core reference, never reads its fields

## Dependencies

- **.NET 10.0** SDK
- **DIKU.Canvas** 2.0.0 - Cross-platform canvas library for F#
- **LiteDB** 5.0.10 - Embedded NoSQL database for pattern persistence
- **Core**: `fsharp/multibreed/core/` - Project reference

## File Layout

```
fsharp/multibreed/DesktopGUI/
├── DesktopGUI.fsproj          # Project configuration
├── Program.fs                  # Main entry point, rendering, event handling
├── Interpreter.fs              # Program interpreter (identical to v1)
├── PatternStore.fs            # Pattern persistence with LiteDB
├── Palette.fs                  # Breed-to-RGB color mapping
├── Patterns/
│   ├── Catalog.fs             # 19 patterns from Appendix A (Cells = Map<Position, Breed>)
│   └── DemoScene.fs           # 13 stamps from Appendix C with ReferenceBreed
└── README.md                   # This file
```

## Build & Run

```bash
cd fsharp/multibreed/DesktopGUI
dotnet run
```

Or build and run:

```bash
dotnet build
./bin/Debug/net10.0/DesktopGUI
```

## Controls

| Key/Action | Function |
|---|---|
| **Enter** | Next generation (step mode only) |
| **s** | Toggle status bar visibility |
| **p** | Toggle clock/step mode |
| **Left/Right Arrow** | Change selected pattern (calls defineCurrentPatternProgram) |
| **Up/Down Arrow** | Change selected breed (0-15) |
| **Click** | Add pattern at clicked position with selected breed as reference |

## Implementation Details

### Shell State

```fsharp
type ShellState = {
    mutable CurrentCore: Core              // The functional core (black box)
    mutable CurrentPatternName: string    // Currently selected pattern name
    mutable SelectedBreed: Breed          // NEW: selected breed for reference cell
    mutable IsClockMode: bool            // Clock vs step mode
    mutable CellsToRender: Cells option   // Cached from ShowCells
    mutable StatusText: string option     // Cached from DisplayMessage
    mutable StatusVisible: bool          // Cached from core
    PatternNames: string list             // All available pattern names
}
```

### Breed Palette

Per Appendix B, breeds 0-15 map to:

| Breed | Color | Breed | Color |
|---|---|---|---|
| 0 | (127,127,127) Gray | 8 | (0,255,0) Green |
| 1 | (127,0,0) Dark Red | 9 | (190,63,255) Purple |
| 2 | (0,127,0) Dark Green | 10 | (0,255,255) Cyan |
| 3 | (0,0,127) Dark Blue | 11 | (190,255,63) Light Green |
| 4 | (255,0,0) Red | 12 | (0,0,255) Blue |
| 5 | (255,255,0) Yellow | 13 | (255,63,190) Pink |
| 6 | (63,190,255) Light Blue | 14 | (255,190,63) Orange |
| 7 | (63,255,190) Light Cyan | 15 | (255,0,255) Magenta |

### Pattern Store

Uses **LiteDB** for persistence with:
- Pattern name as document `_id`
- Cells stored as array of `{ X: int; Y: int; Breed: int }`
- Seed policy: only missing patterns are seeded on startup
- Operations: `initCatalog`, `getPatternCells`, `getPatternNames`, `save`, `remove`, `clear`, `names`

### Demo Scene

13 stamps from Appendix C, each with:
- Pattern name
- Reference position (x, y)
- **Reference breed** (1-13, applied via cross-breed with pattern cells which are breed 0)

This creates a colorful starting configuration where each pattern has a distinct breed.

## Acceptance Criteria (Verified)

1. ✅ Application starts with white background
2. ✅ Colored cells rendered per Appendix B palette
3. ✅ Breed selector visible in bottom-right as color square
4. ✅ Up/Down keys cycle through breeds 0-15
5. ✅ Left/Right keys change pattern (calls defineCurrentPatternProgram)
6. ✅ Click adds pattern with selected breed as reference cell
7. ✅ Status bar visible at startup
8. ✅ All classic functionality preserved (clock/step, tick, etc.)
9. ✅ Shell never accesses Core fields directly
10. ✅ Interpreter unchanged from classic
11. ✅ README documents implementation

## Core Black Box Principle

The shell **NEVER** accesses Core fields directly:

✅ Holds `currentCore: Core` reference
✅ Passes `currentCore` to core operations
✅ Interprets resulting `CoreProgram`
✅ Updates `currentCore` on `Return`
✅ Caches rendering state from instructions
✅ Creates Cell references for clicks using selected breed

❌ `currentCore.Cells` - NEVER accessed
❌ `currentCore.StatusVisible` - NEVER accessed
❌ `currentCore.CurrentPattern` - NEVER accessed
❌ Any pattern matching on Core
❌ Any direct field access or mutation

## Evolution from Classic (v1)

| Aspect | v1 | v2 |
|---|---|---|
| Background | Black | White |
| Cell color | White | Breed-based (Appendix B) |
| Pattern selection | Shell config, used at click | Core state via defineCurrentPatternProgram, click uses "current" |
| Breed selection | N/A | Shell config, used at click |
| Click reference | Position | Cell with selected breed |
| Status at startup | Hidden | Visible |
| Demo scene | Positions only | Positions + reference breeds |

**Interpreter**: IDENTICAL (no changes)
**Instruction set**: IDENTICAL (no new opcodes)
**Shell wiring**: IDENTICAL (same event loop structure)

## Related

- [Repository README](../../../README.md)
- [Multi-Breed Core](../../core/README.md)
- [Specifications](../../../specs.md)
- [Classic DesktopGUI](../../../classic/DesktopGUI/README.md)
