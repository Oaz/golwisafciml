# Game of Life Classic Core (v1) - F# Implementation

This directory contains the **pure functional core** for Conway's Game of Life, Version 1 (Monochrome) in F#.

## Overview

This implementation follows the **Functional Core / Imperative Shell** pattern:
- The **core** (this directory) contains only pure types, pure functions, and the Program type
- The **shell** (separate directories) contains the interpreter, ports, and environment-specific code

## Architecture

### Core Components

| File | Purpose |
|------|---------|
| `Core.fs` | Domain types and pure logic functions |
| `Program.fs` | Program type (free-monad-inspired) with computation expressions |
| `Core.fsproj` | .NET SDK project file (zero dependencies) |
| `Tests/` | Complete test suite using Expecto and FsCheck |

## Domain Types

### Position
- Record type with `X: int` and `Y: int` fields
- Uses `Int32` coordinates (practically unbounded for spec requirements)
- Implements structural equality and proper hashing

### Cells
- Type alias for `Set<Position>`
- Immutable, order-insensitive collection
- Automatically handles uniqueness

### Core State
- Record type with `Cells: Cells` and `StatusVisible: bool` fields
- Immutable - all updates return new records
- Field update functions: `withCells`, `withStatusVisible`
- Observer functions: `cellsOf`, `statusVisibleOf`

## Program Type

The core uses a **free-monad-inspired Program type** with **computation expressions** to showcase F# capabilities:

```fsharp
type Program<'a> =
    | Pure of 'a
    | ShowCells of Cells * Program<'a>
    | DisplayMessage of string * Program<'a>
    | HideMessage of Program<'a>
    | GetPatternCells of string * (Cells -> Program<'a>)
```

### Computation Expression

The `program { ... }` computation expression provides imperative-looking syntax:

```fsharp
let nextGenerationProgram (core: Core) : CoreProgram = program {
    let newCells = nextGenerationCells (cellsOf core)
    let core' = withCells core newCells
    
    do! program.EmitShow newCells
    
    if statusVisibleOf core then
        do! program.EmitDisplay (statusMessage newCells)
    
    return core'
}
```

**Key Properties:**
- Pure data structure - no behavior, no side effects
- Fully testable via pattern matching
- `Pure`/`Return` is a leaf - no instructions can follow it
- Only `GetPatternCells` has a response-carrying continuation
- The `Combine` member is extended to `Program<unit> -> Program<'b> -> Program<'b>` to support sequencing with `do!`

## Core Operations

All core operations are **pure functions** that return `CoreProgram`:

| Function | Description |
|----------|-------------|
| `nextGenerationProgram` | Computes and displays the next generation |
| `addPatternProgram` | Adds a pattern to the current state |
| `switchStatusVisibilityProgram` | Toggles status message visibility |

## Deviations from Spec

### 1. Program Encoding

**Spec:** §3.2 defines `Program ::= Finish | Step Instruction Rest`

**Implementation:** Generic `Program<'a>` with computation expression builder

**Rationale:** While the spec's encoding would be sufficient, we use CEs to:
- Showcase F#'s ability to create domain-specific effect systems
- Demonstrate the Functional Core / Imperative Shell pattern
- Prepare for future elaborations (v2)
- Maintain full testability via underlying type

### 2. Set Implementation

**Spec:** §2.2 - `Cells` is a set with order-insensitive equality

**Implementation:** `Set<Position>` from F# standard library

**Rationale:** F# `Set<'T>` provides structural equality and proper hashing, satisfying spec requirements.

### 3. Memoization Mechanism

**Spec:** §2.3 - memoization of `neighbors` required where idiom exists

**Implementation:** `ConcurrentDictionary<Position, Set<Position>>`

**Rationale:** Thread-safe, observationally pure memoization suitable for library use.

### 4. Coordinate Type

**Spec:** §2.1 - unbounded signed integers

**Implementation:** `int` (System.Int32)

**Rationale:** Range [-2^31, 2^31-1] is practically unbounded and passes G11 (±10^6) comfortably.

### 5. No Packed Encoding

**Spec:** Appendix E - optional packed encoding

**Implementation:** Value-record model (Position record, Set<Position>)

**Rationale:** More idiomatic in F#, type-safe, maintains structural equality/hashing.

## Testing

### Test Framework

- **Expecto** - Pure F#, lightweight, functional style
- **FsCheck** - Property-based testing integration
- **FsCheck.Expecto** - Integration between the two

### Test Groups

| Group | Tests | File |
|-------|-------|------|
| C | Cell algebra | CoreTests.fs |
| N | Neighbors and counting | CoreTests.fs |
| G | Life rules | CoreTests.fs |
| P | Patterns, translation, status | CoreTests.fs |
| X | Cross-cutting | CoreTests.fs |
| I | Programs and protocol | ProgramTests.fs |
| G12 | Property-based | PropertyTests.fs |

### Running Tests

To run the tests, use one of the following commands:

```bash
# From the repository root
cd fsharp/classic/core/Tests
dotnet run

# Or run the compiled test executable directly
cd fsharp/classic/core/Tests
./bin/Debug/net10.0/Core.Tests
```

**Note:** `dotnet test` does not work with Expecto tests because Expecto requires a console runner. Use `dotnet run` instead.

## Building

The core is a **class library (DLL)** with zero dependencies:

```bash
cd fsharp/classic/core
dotnet build
```

This produces `GameOfLife.Classic.Core.dll` which can be referenced by shell implementations.

## Design for Evolution (v1 → v2)

The free-monad Program type and computation expression approach make v2 evolution straightforward:

- New operations add new Program constructors or new core operations
- Existing interpreter gains new match cases
- No changes to existing Program constructors needed
- `defineCurrentPatternProgram` would emit `Return (withCurrentPattern core name)`

See the plan.md file for detailed v2 evolution considerations.

## Acceptance Criteria

1. ✓ All v1 core tests pass (C1, N1–N3/N5, G1–G7/G9–G11, P1–P2/P4, I1–I4/I8–I9, X1a/X2–X3/X4)
2. ✓ Code compiles cleanly with no warnings
3. ✓ Core has zero dependencies (no NuGet packages)
4. ✓ Core contains no test doubles (fakes, mocks, spies)
5. ✓ Core contains no interpreter (belongs in shell)
6. ✓ Core contains no port interfaces (belong in shell)
7. ✓ Core is pure: no mutable state, no I/O, no exceptions
8. ✓ Program type correctly encodes the protocol
9. ✓ All domain types have structural equality and hashing
10. ✓ README documents all deviations

## Compliance

This implementation complies with:
- `specs.md` §2 (Domain Model)
- `specs.md` §3 (Program and Protocol)
- `specs.md` §6 (Version-specific requirements)
- Repository AGENTS.md workflow
