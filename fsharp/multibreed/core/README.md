# F# Multi-Breed Core (v2) — Functional Core Implementation

**Location:** `fsharp/multibreed/core/`

**Status:** Implemented (evolved from `fsharp/classic/core/`)

---

## Overview

This folder contains the **functional core** for Version 2 (Multi-Breed) of Conway's Game of Life in F#. This is a **direct evolution** of the v1 classic core, following the spec's central quality: **"The shell never changes. The core's data and decisions grow."**

The core is **pure** — it contains only types, pure functions, and the Program type. It has:
- **No dependencies on ports**
- **No interpreter** (belongs in the shell)
- **No test doubles** (fakes, mocks, spies)
- **No I/O or mutable state**

---

## Architecture

The core follows the **Functional Core / Imperative Shell** pattern:

```
Functional Core (pure, deterministic)
├── Domain types: Position, Breed, Cell, Cells (Map<Position, Cell>)
├── Core state: Core record with Cells, StatusVisible, CurrentPattern
├── Pure functions: neighbors, shouldLive, nextGenerationCells, translate, statusMessage
├── New v2 functions: cross_breed, positionOf, bump
├── Program type: Free-monad-inspired with computation expressions
└── Core operations: nextGenerationProgram, addPatternProgram, switchStatusVisibilityProgram, defineCurrentPatternProgram

Imperative Shell (not in this folder)
├── Interpreter (loops over Program instructions)
├── Ports: Display, PatternStore, EventSource, State
└── Event wiring
```

---

## Changes from v1 (Classic)

This implementation was created by **copying and editing** the v1 core. The changes are confined to the §13.2 checklist:

### Domain Type Changes

1. **Added `Breed` type** - Private discriminated union with smart constructor (0-15)
2. **Added `Cell` type** - Record with `Position` and `Breed` fields
3. **Changed `Cells` type** - From `Set<Position>` to `Map<Position, Cell>` to enforce at most one cell per position
4. **Added `CurrentPattern` field** - To `Core` type (string)

### New Functions

1. **`cross_breed`** - (a + b) mod 16, commutative and associative
2. **`positionOf`** - Projects Cell to Position, dropping breed
3. **`bump`** - Updates neighbor count map: increments count and cross-breeds
4. **`nextGenerationCells`** - Reimplemented with persistent-map fold strategy (replaces candidate-set filtering)
5. **`translate`** - Now accepts `Cell` reference and cross-breeds each pattern cell's breed

### Core Operations Changes

1. **`addPatternProgram`** - Reference parameter changed from `Position` to `Cell`
2. **`addPatternProgram`** - Added `"current"` sentinel resolution to `currentPatternOf core`
3. **`addPatternProgram`** - Uses `Map.fold` for union instead of `Set.union`
4. **`defineCurrentPatternProgram`** - NEW operation for v2

### What Did NOT Change

✅ Instruction set (5 opcodes unchanged)
✅ Program type definition
✅ Interpreter (belongs in shell)
✅ Shell wiring
✅ Life rules (`shouldLive`)
✅ Status message grammar
✅ Pattern catalog data
✅ Demo scene (same 13 stamps, with breeds applied)

---

## File Structure

```
fsharp/multibreed/core/
├── Core.fs              # Domain types and pure functions
├── Program.fs           # Program type and core operations
├── Core.fsproj          # Project file (zero dependencies)
├── Tests/
│   ├── CoreTests.fs     # Domain, neighbor, life rule, pattern tests (C, N, G, P, X)
│   ├── ProgramTests.fs  # Program structure tests (I)
│   ├── PropertyTests.fs # Property-based tests (G12)
│   ├── PersistentMapTests.fs # Map tests (D1-D6)
│   └── Core.Tests.fsproj
└── README.md            # This file
```

---

## Design Decisions

### Breed Representation

```fsharp
type Breed = private Breed of int

module Breed =
    let create n = Breed (n &&& 0xF)
    let value (Breed b) = b
```

- **Private constructor** ensures all breeds are valid (0–15)
- **Smart constructor** uses bitwise AND (`&&& 0xF`) for efficient mod 16
- **Type safety** prevents confusing breed with other integers
- **Idiomatic F#** approach for bounded values

### Cell Representation

```fsharp
type Cell = { Position: Position; Breed: Breed }
```

- **Record type** with structural equality and hashing
- **Position is the key** for Map-based Cells collection
- **Custom Equals/GetHashCode** ensures Position's custom equality is used

### Cells Collection

```fsharp
type Cells = Map<Position, Cell>
```

- **`Map<Position, Cell>`** instead of `Set<Cell>`
- **Enforces at most one cell per position** at the type level
- **Efficient O(log n) lookup** by position (needed for neighbor counting)
- **Ordered iteration by key** (ascending)
- **Order-insensitive equality** based on key-value pairs (satisfies §2.2)

### Persistent Map

Uses F# `Map<'Key, 'Value>` as the idiomatic persistent (immutable) map:
- **Immutability**: inserts/updates return new maps, old maps unchanged
- **Efficient lookup**: O(log n)
- **Ordered iteration by key** (ascending)
- **Structural equality**
- Satisfies all D1–D6 requirements

### Neighbor Counting Strategy

**v1**: Candidate-set filtering
```fsharp
let allCellsAndNeighbors cells = ...
let nextGen = Set.filter (fun pos -> shouldLive ...) (allCellsAndNeighbors cells)
```

**v2**: Persistent-map fold with breed threading
```fsharp
let counts = Map.fold (fun acc pos cell -> ...) Map.empty cells
let nextGen = Map.fold (fun pos (count, breed) acc -> ...) Map.empty counts
```

The v2 strategy:
- Builds a `Map<Position, int * Breed>` by folding over all cells and their neighbors
- For each neighbor position, `bump` increments count and cross-breeds with existing breed
- First bump for a position sets `(count=1, breed=contributing cell's breed)`
- Subsequent bumps: `count++` and `breed = cross_breed(existing, new)`
- Newborn breed = sum of all contributing live neighbors' breeds mod 16
- **Order-independent** by construction (addition mod 16 is commutative and associative)
- Produces **identical position sets** as v1 (test G10)

### Program Type

Identical to v1 - free-monad-inspired with computation expressions:

```fsharp
type Program<'a> =
    | Pure of 'a
    | ShowCells of Cells * Program<'a>
    | DisplayMessage of string * Program<'a>
    | HideMessage of Program<'a>
    | GetPatternCells of string * (Cells -> Program<'a>)
```

- **No new opcodes** in v2
- **`defineCurrentPatternProgram`** only emits `Return`, not a new instruction
- **Computation expressions** provide clean imperative-looking syntax
- **Pattern matching** on Program<'a> works naturally for tests

---

## Public API

### Core.fs

```fsharp
namespace GameOfLife.Multibreed.Core

// Domain types
type Position = { X: int; Y: int }
type Breed = private Breed of int
type Cell = { Position: Position; Breed: Breed }
type Cells = Map<Position, Cell>
type NeighborCounts = Map<Position, int * Breed>
type Core = { Cells: Cells; StatusVisible: bool; CurrentPattern: string }

// Breed module
module Breed =
    val create: int -> Breed
    val value: Breed -> int
    val ofInt: int -> Breed
    val toInt: Breed -> int

// Core module functions
module Core =
    val initialCore: Core
    val withCells: Core -> Cells -> Core
    val withStatusVisible: Core -> bool -> Core
    val withCurrentPattern: Core -> string -> Core
    val cellsOf: Core -> Cells
    val statusVisibleOf: Core -> bool
    val currentPatternOf: Core -> string
    
    val neighbors: Position -> Set<Position>
    val shouldLive: bool -> int -> bool
    val cross_breed: Breed -> Breed -> Breed
    val positionOf: Cell -> Position
    val bump: NeighborCounts -> Position -> Breed -> NeighborCounts
    val nextGenerationCells: Cells -> Cells
    val translate: Cells -> Cell -> Cells
    val statusMessage: Cells -> string
```

### Program.fs

```fsharp
namespace GameOfLife.Multibreed.Core

// Program type (unchanged from v1)
type Program<'a> =
    | Pure of 'a
    | ShowCells of Cells * Program<'a>
    | DisplayMessage of string * Program<'a>
    | HideMessage of Program<'a>
    | GetPatternCells of string * (Cells -> Program<'a>)

type CoreProgram = Program<Core>

// Program operations
module ProgramOperations =
    val program: ProgramBuilder
    val nextGenerationProgram: Core -> CoreProgram
    val addPatternProgram: Core -> string -> Cell -> CoreProgram
    val switchStatusVisibilityProgram: Core -> CoreProgram
    val defineCurrentPatternProgram: Core -> string -> CoreProgram  // NEW for v2
```

---

## Testing

### Test Framework

- **Expecto** (pure F#, lightweight, functional style)
- **FsCheck** for property-based testing
- **No test doubles** (the core is pure, so no fakes/mocks needed)

### Test Groups

| Group | Tests | Description |
|-------|-------|-------------|
| C | C1-C4 | Cell algebra (position, breed, cell, cross-breed, positionOf) |
| N | N1-N5 | Neighbors and counting (neighbors, bump, memoization) |
| G | G1-G11 | Life rules (truth table, oscillators, still lifes, extinction, purity, newborn breed) |
| P | P1-P5, P8 | Patterns, translation, status (translate with cross-breed, save/load) |
| I | I1-I9 | Programs and protocol (opcodes, program shapes, "current" sentinel, well-formedness) |
| X | X1a/X1b, X2-X4 | Cross-cutting (initialCore, non-destructive updates, catalog) |
| D | D1-D6 | Persistent map (basics, persistence, absent key, updates, insertion order, scale) |

### Running Tests

```bash
# From the project root
cd fsharp/multibreed/core

# Build and run tests
dotnet test Tests/Core.Tests.fsproj

# Or use the solution file
cd ../..
dotnet test fsharp/multibreed/core/Tests/Core.Tests.fsproj
```

---

## Compliance with Spec

This implementation satisfies all **normative** requirements from `specs.md`:

✅ Architecture (FC/IS split, instruction protocol)
✅ Domain model (Cell = Position + Breed, Cells = Map<Position, Cell>)
✅ Life rules (Conway's B3/S23, breeds don't affect survival)
✅ Neighbor counting (persistent-map fold with breed threading)
✅ Status message (exact normative text)
✅ Cross-breeding ((a + b) mod 16, order-independent)
✅ Pattern translation (re-center + cross-breed with reference)
✅ Program shape (free monad with 5 opcodes)
✅ Core operations (nextGeneration, addPattern with "current", switchStatus, defineCurrentPattern)
✅ Purity (no I/O, no side effects, deterministic)
✅ Immutability (records, Maps, no mutation)
✅ Structural equality and hashing
✅ Memoization of neighbors (ConcurrentDictionary, observationally pure)
✅ Test suite (all groups C, N, G, P, I, X, D)

### Deviations from Spec

| Spec Requirement | F# Implementation | Rationale |
|-----------------|-------------------|-----------|
| Cells as Set | Cells as Map<Position, Cell> | Enforces at most one cell per position at type level; idiomatic F# |
| Breed as integer | Breed as private DU | Type safety, ensures 0–15 range |
| Pattern save/load | Not implemented in core | Core never touches storage; belongs in shell |
| Persistent map | F# Map | Idiomatic F# persistent map |
| Packed encoding | Not used | More idiomatic to use value records in F# |

---

## Evolution from v1

The v2 core was produced by **copying and editing** the v1 core. The git diff shows:

- **Additions**: Breed type, Cell type, CurrentPattern field, cross_breed, positionOf, bump, nextGenerationCells (map fold version), defineCurrentPatternProgram
- **Modifications**: Cells type (Set<Position> → Map<Position, Cell>), Core type (+CurrentPattern), translate (Cell reference, cross-breed), addPatternProgram (Cell reference, "current" sentinel)
- **Unchanged**: Position, neighbors, shouldLive, statusMessage, Program type, interpreter, shell wiring

This demonstrates the spec's central quality: **"The shell never changes. The core's data and decisions grow."**

---

## Dependencies

The core has **zero external dependencies**:
- Only depends on .NET BCL (Base Class Library)
- Targets .NET 10.0
- Uses F# language features: records, discriminated unions, computation expressions, pattern matching

Test projects depend on:
- **Expecto** (12.0.0) - Test framework
- **FsCheck** (4.0.0) - Property-based testing
- **FsCheck.Expecto** (4.0.0) - FsCheck integration with Expecto

---

## Build Configuration

```xml
<!-- Core.fsproj -->
<Project Sdk="Microsoft.NET.Sdk">
  <PropertyGroup>
    <TargetFramework>net10.0</TargetFramework>
    <ImplicitUsings>enable</ImplicitUsings>
    <GenerateProgramFile>false</GenerateProgramFile>
  </PropertyGroup>
  
  <ItemGroup>
    <Compile Include="Core.fs" />
    <Compile Include="Program.fs" />
  </ItemGroup>
</Project>
```

---

## Performance Considerations

- **Memoization**: `neighbors` uses `ConcurrentDictionary` for thread-safe caching
- **Neighbor counting**: O(n log n) with Map (n = number of cells + their neighbors)
- **Map operations**: All O(log n) for insert, lookup, fold
- **Coordinate range**: Int32 (-2^31 to 2^31-1) - practically unbounded
- **Breed arithmetic**: Bitwise AND for mod 16 - very efficient

---

## License

This project is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.
