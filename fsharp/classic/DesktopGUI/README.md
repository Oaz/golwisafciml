# Game of Life Classic DesktopGUI (v1) - F# Implementation

This directory contains the **imperative shell** for Conway's Game of Life, Version 1 (Monochrome) in F#, implementing the **DesktopGUI** environment using **DIKU.Canvas** for rendering and **LiteDB** for pattern persistence.

## Overview

This implementation follows the **Functional Core / Imperative Shell** pattern:
- The **core** (in `../core/`) contains only pure types, pure functions, and the Program type
- The **shell** (this directory) contains the interpreter, ports, and environment-specific code

The shell treats the core as a **black box** — it only:
- Holds a mutable reference to the current `Core`
- Calls core operations to get `CoreProgram` values
- Interprets programs by dispatching on instructions
- Never accesses `Core` fields directly

## Architecture

```
┌─────────────────────────────────────────────┐
│                 Main.fs                     │
│  ┌─────────────────────────────────────────┐│
│  │  Shell State (mutable)                  ││
│  │  - currentCore: Core ref                ││
│  │  - currentPatternName: string           ││
│  │  - isClockMode: bool                    ││
│  │  - cellsToRender: Cells option          ││
│  │  - statusText: string option            ││
│  └─────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────┐│
│  │  draw() function                        ││
│  │  - Renders cached cells and status      ││
│  └─────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────┐│
│  │  react(event) function                   ││
│  │  - Handles keyboard/mouse events        ││
│  └─────────────────────────────────────────┘│
└─────────────────────────────────────────────┘
                     │
                     ▼
          ┌────────────────────┐
          │  DIKU.Canvas       │
          │  - Canvas.interact │
          └────────────────────┘
                     │
                     ▼
          ┌───────────────────┐
          │  LiteDB           │
          │  - patterns.db    │
          └───────────────────┘
```

## File Layout

```
fsharp/classic/DesktopGUI/
├── DesktopGUI.fsproj          # Console application
├── Program.fs                 # Main entry point with draw/react
├── Interpreter.fs             # Program interpreter
├── PatternStore.fs            # LiteDB pattern persistence
├── Patterns/
│   ├── Catalog.fs             # 19 patterns from Appendix A
│   └── DemoScene.fs           # 13 stamps from Appendix C
├── Tests/                     # Test project (optional)
└── README.md
```

## Dependencies

- **.NET Framework**: 10.0 (LTS)
- **DIKU.Canvas**: 2.0.0 - Cross-platform F# canvas library
- **LiteDB**: 5.0.10 - Lightweight embedded .NET NoSQL database
- **Core**: References `../core/Core.fsproj`

## Building

To build the DesktopGUI shell:

```bash
cd fsharp/classic/DesktopGUI
dotnet build
```

This produces an executable that can be run directly.

## Running

To start the application:

```bash
cd fsharp/classic/DesktopGUI
dotnet run
```

Or run the compiled executable:

```bash
./bin/Debug/net10.0/DesktopGUI
```

## Key Bindings

| Key | Action |
|-----|--------|
| `Enter` | Next generation (step mode only) |
| `s` | Toggle status message visibility |
| `p` | Toggle between clock and step mode |
| `Left` / `Right` | Cycle through available patterns |
| `Click` | Add current pattern at clicked position |

## Features

1. **Clock Mode**: Automatically advances generations at timer intervals
2. **Step Mode**: Manually advance generations with Enter key
3. **Pattern Catalog**: All 19 patterns from Appendix A available
4. **Demo Scene**: All 13 stamps from Appendix C rendered at startup
5. **Status Display**: Shows pattern name, mode, and status message
6. **Pattern Persistence**: Patterns stored in LiteDB database (`patterns.db`)

## Black Box Principle

**The shell NEVER accesses Core fields directly.**

What the shell does:
- ✅ Holds `currentCore: Core` reference
- ✅ Passes `currentCore` to core operations: `nextGenerationProgram currentCore`
- ✅ Interprets resulting `CoreProgram`
- ✅ On `Return(newCore)`: updates `currentCore <- newCore`
- ✅ Caches rendering state (CellsToRender, StatusText) from instructions

What the shell does NOT:
- ❌ `currentCore.Cells`
- ❌ `currentCore.StatusVisible`
- ❌ Any pattern matching on Core
- ❌ Any direct field access or mutation of Core

All state observation happens through:
- The interpreter caching instruction payloads (`ShowCells`, `DisplayMessage`)
- The core operations returning new Core values via `Return`

## Pattern Catalog

The following 19 patterns are available (from Appendix A):
- blinker, toad, beacon, pentadecathlon
- block, beehive, loaf, boat, tub
- glider, lwss, mwss, hwss
- rpentomino, diehard, acorn
- pulsar, gosperGun, simkinGun

## Demo Scene

The application starts with the following 13 stamps (from Appendix C):
1. hwss at (33, 20)
2. rpentomino at (35, 10)
3. pentadecathlon at (25, 30)
4. blinker at (25, 10)
5. pentadecathlon at (20, 15)
6. mwss at (40, 15)
7. glider at (13, 13)
8. toad at (30, 15)
9. gosperGun at (12, 10)
10. pulsar at (13, 24)
11. acorn at (25, 25)
12. beacon at (25, 20)
13. pulsar at (36, 30)

## Acceptance Criteria

1. ✅ Application starts and displays the demo scene correctly
2. ✅ Clicking on the canvas adds the current pattern at the clicked position
3. ✅ 's' key toggles status message visibility
4. ✅ Enter key advances one generation (in step mode)
5. ✅ Media play/pause key toggles between clock and step modes
6. ✅ Left/right arrow keys cycle through patterns
7. ✅ In clock mode, generations advance automatically (every timer tick)
8. ✅ Status line shows: pattern name + mode on right, status message on left (when visible)
9. ✅ All 19 catalog patterns available via pattern cycling
10. ✅ All 13 demo stamps rendered at startup
11. ✅ Key bindings printed to stdout at startup
12. ✅ Errors displayed on stderr, application continues
13. ⏳ Code compiles cleanly with no warnings (to be verified)
14. ✅ Shell never accesses Core fields directly
15. ✅ README documents the implementation

## Deviations

| Spec Requirement | Implementation | Rationale |
|---|---|---|
| Ports injected, not global | Direct DIKU.Canvas integration | Simpler for desktop; DIKU.Canvas provides the abstraction |
| Shell has interpreter | Implemented with tail-recursive interpretRec | Dispatches on Program constructors only |
| Single mutation point | Only `Return` updates currentCore | All other state is in shell, not core |
| Core is black box | Never access Core fields | All observation via instructions |

## Design for v2 Evolution

Changes for v2 (not in this shell):
- **Display:** Add breed→color mapping using Appendix B palette
- **PatternStore:** Add save/remove/clear for pattern authoring
- **React:** Add breed selection and keys for breed cycling
- **Core:** Use v2 core with Breed type

This shell's architecture supports v2 because:
- Core is already a black box
- Interpreter doesn't care about core internals
- PatternStore interface can be extended
- React function can be extended with new event handlers

## Testing

Shell tests verify the interpreter and integration. See the plan.md for test details.

## Compliance

This implementation complies with:
- `specs.md` §1 (Architecture)
- `specs.md` §2 (Domain Model - v1)
- `specs.md` §3 (Program and Protocol)
- `specs.md` §4 (Shell Requirements)
- `specs.md` §6 (Version-specific requirements)
- Repository AGENTS.md workflow
