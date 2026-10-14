# Plan: F# Multi-Breed Core (v2) — Functional Core Implementation

**Task:** Implement the functional core for Conway's Game of Life, Version 2 (Multi-Breed) in F#.

**Location:** `fsharp/multibreed/core/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

**Owner:** Olivier (Oaz)

---

## 0. Context and Scope

This plan covers the **functional core** for the **multi-breed (v2)** Game of Life in F#, following the repository's architecture and the language-agnostic specification in `specs.md`.

**Critical constraint:** Per the spec's central quality (§5) and §13.1-13.2, **v2 must be produced by editing the v1 codebase**. The architecture, instruction set, interpreter, status grammar, Life rule, and shell wiring must remain **untouched**. The diff must be confined to the §13.2 checklist.

This implementation will **copy and edit** the existing `fsharp/classic/core/` files, applying the v2-specific changes while preserving all v1 invariants.

### What is IN scope

- Complete F# implementation of the **v2 functional core** as defined in `specs.md` §2, §3, §6 (v2 column), and §13.2
- All pure domain types: `Position`, `Breed`, `Cell` (position + breed), `Cells` (set of cells)
- All pure core state: `Core` record with `cells`, `statusVisible`, and `currentPattern`
- All pure functions: `neighbors`, `shouldLive`, `nextGenerationCells` (persistent-map fold strategy), `translate` (with cross-breed), `statusMessage`, `cross_breed`, `positionOf`
- Persistent map implementation for neighbor counting (F# `Map`)
- All core operations producing programs: `nextGenerationProgram`, `addPatternProgram` (with `"current"` sentinel and `Cell` reference), `switchStatusVisibilityProgram`, `defineCurrentPatternProgram`
- The **free-monad-inspired Program type** (same as v1, unchanged)
- Full test suite for v2: Groups C (C1–C4), N (N1–N5), G (G1–G11), P (P1–P5, P8), I (I1–I9), X (X1a/X1b, X2–X4), D (D1–D6)

### What is OUT of scope (deliberately)

- Version 1 (classic) — already implemented in `fsharp/classic/core/`
- Imperative shell (separate task: `fsharp/multibreed/<environment>/` folders)
- **Interpreter** — belongs in the shell, not the core; **unchanged from v1**
- **Ports and port interfaces** — belong in the shell; **unchanged from v1**
- **Demo scene seeding** — shell responsibility; **unchanged logic, same 13 stamps**
- **Pattern catalog (19 patterns)** — fully handled by the shell's PatternStore; catalog data unchanged
- Any rewrites of the Program type, interpreter, or shell wiring
- Any changes to the instruction set (only additions where specified, but §13.2 adds no new instructions)

---

## 1. File and Module Layout

```
fsharp/multibreed/core/
├── Core.fs              # Domain types, core state, pure functions
├── Program.fs           # Program type, core operations
├── Core.fsproj          # F# project file for the core library
├── Tests/
│   ├── CoreTests.fs     # Tests for domain types and pure functions
│   ├── ProgramTests.fs  # Tests for Program structure and core operations
│   ├── PropertyTests.fs # Property-based tests
│   └── PersistentMapTests.fs # Tests for persistent map (D1–D6)
└── README.md            # Core-specific documentation
```

**Note:** The v1 core will be **copied** to this location, then **edited** to add v2-specific features. The file structure remains identical to v1.

### File Responsibilities

| File | Responsibility | Key Types/Functions |
|------|---------------|---------------------|
| `Core.fs` | Domain model and pure logic | Position, Breed, Cell, Cells, Core, neighbors, shouldLive, nextGenerationCells (map fold), translate, statusMessage, cross_breed, positionOf, withCells, withStatusVisible, withCurrentPattern, cellsOf, statusVisibleOf, currentPatternOf |
| `Program.fs` | Program type and core operations | Program<'a>, CoreProgram, ProgramBuilder, nextGenerationProgram, addPatternProgram, switchStatusVisibilityProgram, **defineCurrentPatternProgram** |
| `Core.fsproj` | Build configuration | Library project with **zero dependencies** |
| `Tests/*.fs` | Test suite | All v1 tests + v2-specific tests (C2–C4, N4, G8, P3, P5, P8, I5, X1b, D1–D6) |
| `README.md` | Documentation | F#-specific notes, deviations from spec defaults, evolution from v1 |

---

## 2. Language-Specific Design Decisions (F# Idioms)

### 2.1 Build Tooling

Same as v1:
- **.NET SDK** project file (`Core.fsproj`) targeting .NET 10.0
- **Multi-file compilation**: files ordered by dependency (Core.fs, Program.fs)
- **Project type**: Class library (DLL)
- **Target framework**: `net10.0`
- **LangVersion**: `latest`
- **Zero external dependencies** — core depends only on .NET BCL

**Decision:** Copy `fsharp/classic/core/Core.fsproj` verbatim; only the namespace changes.

### 2.2 Data Representations

#### Cells Representation Decision

**Critical decision:** How to represent `Cells` to satisfy "at most one cell per position" (§2.1).

**Options:**
- **A) `Set<Cell>` with validation**: Use F# `Set<Cell>`, document that callers must ensure position uniqueness. Simple but doesn't enforce the constraint.
- **B) `Map<Position, Cell>`**: Use F# `Map<Position, Cell>`, guarantees at most one cell per position by construction. Provides efficient O(log n) lookup by position.

**Decision: B) `Map<Position, Cell>`**

**Rationale:**
- Enforces the "at most one cell per position" constraint at the type level
- Provides efficient lookup by position (needed for neighbor counting)
- Has order-insensitive equality based on key-value pairs (satisfies §2.2)
- Idiomatic F# approach for position-based collections
- Matches the neighbor counting strategy which uses `Map<Position, (int * Breed)>`

**Consequences:**
- `Cells` type changes from `Set<Position>` (v1) to `Map<Position, Cell>` (v2)
- Functions operating on Cells must be updated (e.g., `Set.count` → `Map.count`, `Set.map` → `Map.map`)
- Translation must return a `Map<Position, Cell>` with unique positions
- All v1 tests using Set operations need to be adapted

#### Breed
```fsharp
/// Represents a breed value (0-15)
/// Uses a bounded integer with smart constructor to ensure validity
type Breed = private Breed of int

module Breed =
    /// Smart constructor ensures breed is in range 0-15
    let create n = Breed (n &&& 0xF)
    
    /// Raw value accessor
    let value (Breed b) = b
    
    /// Overloads for convenience
    let ofInt = create
    let toInt = value
```

**Rationale:** 
- Private constructor ensures all breeds are valid (0–15)
- Smart constructor uses bitwise AND to wrap any int into range
- Value accessor for use in arithmetic
- Type safety prevents confusing breed with other integers

#### Position
**No change from v1.** Keep the existing `Position` record with structural equality and hashing.

#### Cell
```fsharp
/// Represents a cell with position and breed
/// Two cells are equal only if both position and breed are equal
type Cell = { Position: Position; Breed: Breed }
    with
    /// Static factory method for Cell creation
    static member create x y breed = { Position = Position.create x y; Breed = breed }
```

**Note:** F# records have structural equality and hashing by default. We don't need custom overrides since Position already has proper equality.

#### Cells
```fsharp
/// Represents the live cells, with at most one cell per position
/// Uses F# Map<Position, Cell> to enforce position uniqueness
/// Equality is order-insensitive (Map compares by key-value pairs)
type Cells = Map<Position, Cell>
```

**Important:** The `Cells` type is a `Set<Cell>`, not a `Set<Position>`. However, the spec's `positionOf` projection and the neighbor counting strategy key by position, not by full cell.

#### Core State
```fsharp
type Core = {
    Cells: Cells
    StatusVisible: bool
    CurrentPattern: string
}

let initialCore = {
    Cells = Map.empty
    StatusVisible = false
    CurrentPattern = ""
}

let withCells core cells = { core with Cells = cells }
let withStatusVisible core visible = { core with StatusVisible = visible }
let withCurrentPattern core patternName = { core with CurrentPattern = patternName }

let cellsOf core = core.Cells
let statusVisibleOf core = core.StatusVisible
let currentPatternOf core = core.CurrentPattern
```

### 2.3 Persistent Map

**Decision:** Use F# `Map<'Key, 'Value>` as the idiomatic persistent (immutable) map.

```fsharp
/// Type alias for the neighbor count map: Position -> (count, breed)
type NeighborCounts = Map<Position, int * Breed>
```

F# `Map` provides:
- Immutability: inserts/updates return new maps, old maps unchanged
- Efficient lookup: O(log n)
- Ordered iteration by key (ascending)
- Structural equality

This satisfies all D1–D6 requirements:
- D1: basics — Map construction, get, keys, values, entries
- D2: persistence — old map unchanged after set
- D3: absent key — Map.tryFind returns `None` (option type)
- D4: update existing — Map.add replaces value
- D5: insertion-order independence — Map is always ordered by key
- D6: at scale — Map handles 1000 keys efficiently

### 2.4 Position-of Projection

```fsharp
/// Extracts the position from a cell, dropping the breed
/// Two cells at the same position with different breeds share the same position
let positionOf (cell: Cell) : Position = cell.Position
```

### 2.5 Cross-Breeding

```fsharp
/// Cross-breeds two breeds: (a + b) mod 16
/// Addition mod 16 is commutative and associative, ensuring order-independence
let cross_breed (a: Breed) (b: Breed) : Breed =
    Breed.create (Breed.value a + Breed.value b)
```

**Rationale:** Since `Breed.create` already masks with `&&& 0xF`, the result is always mod 16.

### 2.6 Neighbor Counting Strategy (v2)

Per spec §2.4.2, v2 uses persistent-map fold with breed threading:

```fsharp
/// Bump function: updates the neighbor count map for a position with a given breed
/// If position exists: increment count and cross-breed with existing breed
/// If position doesn't exist: set count to 1 with the given breed
let bump (counts: NeighborCounts) (position: Position) (breed: Breed) : NeighborCounts =
    match Map.tryFind position counts with
    | Some (count, existingBreed) ->
        let newCount = count + 1
        let newBreed = cross_breed existingBreed breed
        Map.add position (newCount, newBreed) counts
    | None ->
        Map.add position (1, breed) counts

/// Computes the next generation of cells using the persistent-map fold strategy
/// Breeds of newborn cells are the sum mod 16 of all contributing neighbors
let nextGenerationCells (cells: Cells) : Cells =
    // Get the set of positions that are currently alive (keys of the map)
    let livingPositions = Set.ofArray (Map.keys cells |> Array.ofSeq)
    
    // Build the neighbor count map by folding over all cells and their neighbors
    let counts =
        (Map.empty, cells)
        ||> Map.fold (fun acc pos cell ->
            let neighborsSet = neighbors pos
            Set.fold (fun acc' neighborPos ->
                bump acc' neighborPos cell.Breed
            ) acc neighborsSet
        )
    
    // Determine which positions survive or are born
    // Result is a Map<Position, Cell> where each position has the computed breed
    let survivors =
        Map.fold (fun pos (count, breed) acc ->
            let isAlive = Set.contains pos livingPositions
            if shouldLive isAlive count then
                Map.add pos { Position = pos; Breed = breed } acc
            else
                acc
        ) counts Map.empty
    
    survivors
```

**Key points:**
- The fold starts from an empty map
- For each cell, we iterate over its 8 neighbors
- For each neighbor position, we bump the count and cross-breed
- The first bump for a position sets count=1, breed=contributing cell's breed
- Subsequent bumps increment count and cross-breed with existing breed
- Newborn breed = sum of all contributing live neighbors' breeds mod 16
- The result preserves the exact same position set as v1 (test G10)

### 2.7 Translation (v2)

```fsharp
/// Translates a set of cells by a reference cell
/// Each cell's position is offset by the reference position,
/// and each cell's breed is cross-bred with the reference breed
/// Returns a Map<Position, Cell> with the translated cells
let translate (points: Cells) (reference: Cell) : Cells =
    Map.map (fun pos cell ->
        {
            Position = 
                { 
                    X = pos.X + reference.Position.X
                    Y = pos.Y + reference.Position.Y
                }
            Breed = cross_breed cell.Breed reference.Breed
        }
    ) points
```

**Note:** In v2, `translate` takes a `Cell` reference (not a `Position`), and cross-breeds each pattern cell's breed with the reference cell's breed.

### 2.8 Neighbor Memoization

Same as v1: `ConcurrentDictionary<Position, Set<Position>>` for thread-safe, observationally pure memoization.

### 2.9 Status Message

```fsharp
let statusMessage (cells: Cells) : string =
    let n = Map.count cells
    match n with
    | 0 -> "No cell is alive"
    | 1 -> "1 cell is alive"
    | _ -> sprintf "%d cells are alive" n
```

Same normative text as v1 (§2.6).

### 2.10 Program Type

**Decision:** Keep the **exact same Program type** as v1. The Program type is **unchanged** per §13.1.

```fsharp
type Program<'a> =
    | Pure of 'a
    | ShowCells of Cells * Program<'a>
    | DisplayMessage of string * Program<'a>
    | HideMessage of Program<'a>
    | GetPatternCells of string * (Cells -> Program<'a>)
```

**Important:** The instruction set does **not** change. `defineCurrentPatternProgram` only emits `Return`, not a new instruction.

### 2.10 Core Operations

All existing operations are **modified or extended** to handle v2 types:

```fsharp
let nextGenerationProgram (core: Core) : CoreProgram = program {
    let newCells = nextGenerationCells (cellsOf core)
    let core' = withCells core newCells

    if statusVisibleOf core then
        do! displayMessage (statusMessage newCells)

    do! showCells newCells
    return core'
}

let addPatternProgram (core: Core) (patternName: string) (reference: Cell) : CoreProgram = program {
    // v2: resolve "current" sentinel to the stored currentPattern
    let resolvedName =
        if patternName = "current" then
            currentPatternOf core
        else
            patternName
    
    let! patternCells = getPatternCells resolvedName

    let translated = translate patternCells reference
    // Union two Maps: later entries overwrite earlier ones (same position)
    let newCells = Map.fold (fun pos cell acc -> Map.add pos cell acc) translated (cellsOf core)
    let core' = withCells core newCells

    if statusVisibleOf core then
        do! displayMessage (statusMessage newCells)

    do! showCells newCells
    return core'
}

let switchStatusVisibilityProgram (core: Core) : CoreProgram = program {
    if statusVisibleOf core then
        do! hideMessage
        return withStatusVisible core false
    else
        do! displayMessage (statusMessage (cellsOf core))
        return withStatusVisible core true
}

/// New operation for v2: defines the current pattern name in core state
let defineCurrentPatternProgram (core: Core) (name: string) : CoreProgram = program {
    return withCurrentPattern core name
}
```

**Key changes from v1:**
1. `addPatternProgram` now takes `Cell` as reference (not `Position`)
2. `addPatternProgram` resolves the `"current"` sentinel to `currentPatternOf core`
3. New `defineCurrentPatternProgram` operation
4. All other operations unchanged

---

## 3. Project File Structure

### Core.fsproj

Copy from v1, changing only the namespace:

```xml
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

  <ItemGroup>
    <None Include="README.md" />
  </ItemGroup>

</Project>
```

---

## 4. Testing Framework

### 4.1 Test Project Structure

```
fsharp/multibreed/core/Tests/
├── CoreTests.fs         # Pure function tests (C, N, G, P, X)
├── ProgramTests.fs      # Program structure tests (I)
├── PropertyTests.fs     # Property-based tests (G12)
├── PersistentMapTests.fs # Persistent map tests (D)
└── Core.Tests.fsproj
```

### 4.2 Test Framework Choice

Same as v1: **Expecto** with **FsCheck** for property-based tests.

### 4.3 Test Strategy

**Core principle:** All v1 tests must **continue to pass** with v2 types. The v2 implementation is an **evolution**, not a rewrite.

#### Approach for v1→v2 Test Porting

1. **Copy all v1 tests** from `fsharp/classic/core/Tests/`
2. **Modify type signatures** to use `Cell` instead of `Position` where needed
3. **Add v2-specific tests** for new functionality
4. **Verify all v1 tests still pass** with v2 implementation

#### Test Groups

**Group C — Cell algebra (v2 adds C2–C4):**
- C1: Position construction/accessor round-trip (same as v1)
- **C2: Structural equality** — equal components ⇒ equal cells/hash; different breed ⇒ different cell
- **C3: positionOf projection** — `positionOf(cell(p, 0)) == positionOf(cell(p, 15))`; positions of distinct squares differ
- **C4: cross breed** — `(0,0)→0`, `(8,9)→1`, `(15,15)→14`, commutative, folding [3,5,9,12] in any order gives same result

**Group N — Neighbors and counting (v2 adds N4):**
- N1: neighbors returns exactly 8 distinct positions (same as v1)
- N2: memoized neighbors is observationally pure (same as v1)
- N3: count lookup works correctly (adapted for v2 map structure)
- **N4: bump function** — increment count and cross-breed correctly; immutability
- N5: bump immutability (same concept, v2 implementation)

**Group G — Life rules (v2 adds G8):**
- G1: should-live truth table (same as v1)
- G2: oscillator periods (same as v1)
- G3: still lifes (same as v1)
- G4: pentadecathlon period 15 (same as v1)
- G5: glider translation (same as v1)
- G6: two-generation chain (same as v1)
- G7: extinction (same as v1)
- G9: purity (same as v1)
- **G8: newborn breed inheritance** — verify breed of newborn cells is sum mod 16 of all contributing neighbors
- G10: nextGeneration preserves position set (same as v1, must pass with both strategies)
- G11: unboundedness (same as v1)

**Group P — Patterns, translation, status (v2 adds P3, P5, P8):**
- P1: statusMessage grammar (same as v1)
- P2: translate offsets correctly (adapted for v2)
- **P3: translate cross-breeds** — each pattern cell's breed is cross-bred with reference breed
- P4: translate is a bijection on shapes (same as v1, cardinality preserved)
- **P5: save→load round-trip** — pattern save/load preserves breeds (requires PatternStore fake in tests)
- **P8: breed→color mapping** — verify palette mapping is correct (shell test, not core)

**Group I — Programs and protocol (v2 adds I5):**
- I1: instruction identity (same as v1)
- I2: nextGenerationProgram shape (same as v1)
- I3: switchStatusVisibilityProgram shape (same as v1)
- I4: addPatternProgram shape (adapted for v2)
- **I5: "current" sentinel** — verify `addPatternProgram` with `"current"` resolves to `currentPatternOf core`
- I8: program well-formedness (same as v1)
- I9: unknown pattern (same as v1)

**Group X — Cross-cutting (v2 adds X1b):**
- X1a: initialCore defaults (extended for v2: currentPattern = "")
- **X1b: startup status visible** — v2 starts with status visible (shell configuration, verified in tests)
- X2: field updates are non-destructive (same as v1, extended for currentPattern)
- X3: catalog cardinalities (same as v1)
- X4: startup demo scene (same as v1, breeds applied in v2)

**Group D — Persistent map (v2 only):**
- D1: map basics
- D2: persistence
- D3: absent key
- D4: update existing key
- D5: insertion-order independence
- D6: map at scale

**Group G12 — Property-based tests:**
Same as v1, extended with breed-related properties where applicable.

### 4.4 Core Testing Philosophy

Same as v1: **The core is pure. Therefore core tests need NO test doubles.**

- **Pure function tests:** Directly test functions with inputs and check outputs
- **Program structure tests:** Use pattern matching to inspect `Program<Core>` structure
- **For `GetPatternCells`:** Extract continuation and call it with test data
- **No PatternStore fake needed:** Directly call continuations with test data

---

## 5. Public API

### Core.fs

```fsharp
namespace GameOfLife.Multibreed.Core

// Domain types
type Position = { X: int; Y: int }
type Breed = private Breed of int

module Breed =
    val create: int -> Breed
    val value: Breed -> int
    val ofInt: int -> Breed
    val toInt: Breed -> int

type Cell = { Position: Position; Breed: Breed }
type Cells = Map<Position, Cell>
type Core = { Cells: Cells; StatusVisible: bool; CurrentPattern: string }

// Type alias for neighbor count map
type NeighborCounts = Map<Position, int * Breed>

// Constructors and observers
val initialCore: Core
val withCells: Core -> Cells -> Core
val withStatusVisible: Core -> bool -> Core
val withCurrentPattern: Core -> string -> Core
val cellsOf: Core -> Cells
val statusVisibleOf: Core -> bool
val currentPatternOf: Core -> string

// Domain functions
val neighbors: Position -> Set<Position>
val shouldLive: bool -> int -> bool
val nextGenerationCells: Cells -> Cells
val translate: Cells -> Cell -> Cells
val statusMessage: Cells -> string
val cross_breed: Breed -> Breed -> Breed
val positionOf: Cell -> Position
val bump: NeighborCounts -> Position -> Breed -> NeighborCounts
```

### Program.fs

```fsharp
namespace GameOfLife.Multibreed.Core

// Program type - IDENTICAL to v1, unchanged
type Program<'a> =
    | Pure of 'a
    | ShowCells of Cells * Program<'a>
    | DisplayMessage of string * Program<'a>
    | HideMessage of Program<'a>
    | GetPatternCells of string * (Cells -> Program<'a>)

type CoreProgram = Program<Core>

// Computation expression builder - IDENTICAL to v1
type ProgramBuilder() = ...
val program: ProgramBuilder

// Core operations - evolved from v1
val nextGenerationProgram: Core -> CoreProgram
val addPatternProgram: Core -> string -> Cell -> CoreProgram  // Changed: Cell reference
val switchStatusVisibilityProgram: Core -> CoreProgram
val defineCurrentPatternProgram: Core -> string -> CoreProgram  // NEW for v2
```

**Note:** The Program type, computation expression builder, and interpreter are **completely unchanged** from v1. The only changes are in the core operations and domain types.

---

## 6. Evolution Checklist (v1 → v2)

Per §13.2, these are the **only** changes allowed. This plan **must not** touch anything in §13.1.

### Changes to Make (in Core.fs)

1. [ ] Add `Breed` type with smart constructor
2. [ ] Change `Cell` from `Position` to `{ Position: Position; Breed: Breed }`
3. [ ] Add `positionOf` projection function
4. [ ] Add `cross_breed` function
5. [ ] Change `Cells` from `Set<Position>` to `Map<Position, Cell>` (enforces at most one cell per position)
6. [ ] Add `CurrentPattern` field to `Core` type
7. [ ] Add `withCurrentPattern` and `currentPatternOf` functions
8. [ ] Update `initialCore` to use `Map.empty` instead of `Set.empty`
9. [ ] Change `neighbors` memoization key to still be `Position` (not `Cell`)
10. [ ] Add `NeighborCounts` type alias (`Map<Position, int * Breed>`)
11. [ ] Add `bump` function
12. [ ] Replace `nextGenerationCells` implementation with persistent-map fold strategy (using Map operations)
13. [ ] Change `translate` to accept `Cell` reference and cross-breed breeds, returning `Map<Position, Cell>`
14. [ ] Update `statusMessage` to use `Map.count` instead of `Set.count`
15. [ ] Update `addPatternProgram` in Program.fs to use `Map.fold` for union instead of `Set.union`

### Changes to Make (in Program.fs)

1. [ ] Change namespace to `GameOfLife.Multibreed.Core`
2. [ ] Update `addPatternProgram` signature: `reference` parameter from `Position` to `Cell`
3. [ ] Add `"current"` sentinel resolution in `addPatternProgram`
4. [ ] Add `defineCurrentPatternProgram` operation

### Changes to Tests

1. [ ] Copy all v1 test files
2. [ ] Update type signatures to use `Cell` instead of `Position` where appropriate
3. [ ] Add v2-specific tests (C2–C4, N4, G8, P3, P5, P8, I5, X1b, D1–D6)
4. [ ] Verify all v1 tests still pass

### What MUST NOT Change

1. [x] Instruction set (no new opcodes)
2. [x] Program shape/type definition
3. [x] Interpreter (belongs in shell)
4. [x] Shell wiring
5. [x] Life rules (`shouldLive`)
6. [x] Status message grammar
7. [x] Pattern catalog data
8. [x] Demo scene (same 13 stamps)

---

## 7. Deviations from Spec and F#-Specific Choices

### 7.1 Choice: Breed Type

**Spec:** §2.1 — `Breed = integer in 0..15`

**F# Implementation:** Private discriminated union `Breed = private Breed of int` with smart constructor.

**Rationale:** 
- Provides type safety (cannot confuse with other integers)
- Smart constructor ensures validity (masks to 0–15)
- Idiomatic F# approach for bounded values
- Matches the approach used for Position in v1

### 7.2 Choice: Cell Equality

**Spec:** §2.1 — Structural equality and hashing mandatory

**F# Implementation:** Custom `Equals` and `GetHashCode` on Cell record.

**Rationale:** While F# records have structural equality, we explicitly ensure Position's custom equality is used and Breed is included in hashing.

### 7.3 Choice: Persistent Map

**Spec:** §2.5 — Use idiomatic immutable map

**F# Implementation:** F# `Map<Position, int * Breed>`

**Rationale:** F# Map is the idiomatic persistent map, provides all required operations, has ordered iteration.

### 7.4 Choice: Neighbor Counting Implementation

**Spec:** §2.4.2 — persistent-map fold with `(count, breed)` entries

**F# Implementation:** Two-level fold: outer over cells, inner over neighbors, calling `bump`

**Rationale:** Direct translation of spec algorithm to F#; efficient and clear.

### 7.5 Choice: No Packed Encoding

**Spec:** Appendix E — optional packed encoding

**F# Decision:** Use value-record model (Cell record, Set<Cell>).

**Rationale:** More idiomatic in F#, type-safe, maintains structural equality/hashing, simpler to understand and maintain.

---

## 8. Resolved Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| **Breed representation** | Private DU with smart constructor | Type safety, ensures 0–15 range, idiomatic F# |
| **Cell representation** | Record with Position and Breed | Structural equality, hashing, idiomatic F# |
| **Persistent map** | F# Map<Position, int * Breed> | Idiomatic, immutable, ordered, efficient |
| **Program type** | Same as v1, unchanged | Per §13.1, shell never changes |
| **Test framework** | Expecto + FsCheck | Same as v1, proven, pure F# |
| **Test approach** | Copy v1 tests, adapt, add v2 tests | Evolution, not rewrite |

---

## 9. Implementation Checklist

### Phase 2 (after approval)

**Step 1: Create directory structure**
- [ ] Create `fsharp/multibreed/core/` directory
- [ ] Create subdirectories: `Tests/`

**Step 2: Copy and adapt v1 files**
- [ ] Copy `fsharp/classic/core/Core.fsproj` → `fsharp/multibreed/core/Core.fsproj`
- [ ] Update namespace in project file metadata (if any)
- [ ] Copy `fsharp/classic/core/Core.fs` → `fsharp/multibreed/core/Core.fs`
- [ ] Copy `fsharp/classic/core/Program.fs` → `fsharp/multibreed/core/Program.fs`

**Step 3: Apply v2 changes to Core.fs**
- [ ] Update namespace to `GameOfLife.Multibreed.Core`
- [ ] Add `Breed` type and module
- [ ] Change `Cell` to include `Breed` field
- [ ] Add `positionOf` function
- [ ] Add `cross_breed` function
- [ ] Change `Cells` from `Set<Position>` to `Set<Cell>`
- [ ] Add `CurrentPattern` to `Core` record
- [ ] Add `withCurrentPattern` and `currentPatternOf` functions
- [ ] Add `NeighborCounts` type alias
- [ ] Add `bump` function
- [ ] Replace `nextGenerationCells` with persistent-map fold strategy
- [ ] Update `translate` to accept `Cell` reference and cross-breed
- [ ] Update `statusMessage` to work with `Set<Cell>`
- [ ] Keep memoized `neighbors` (unchanged)

**Step 4: Apply v2 changes to Program.fs**
- [ ] Update namespace to `GameOfLife.Multibreed.Core`
- [ ] Update `addPatternProgram` to accept `Cell` reference
- [ ] Add `"current"` sentinel resolution in `addPatternProgram`
- [ ] Add `defineCurrentPatternProgram` function

**Step 5: Create Tests**
- [ ] Copy all test files from v1
- [ ] Update namespaces and type signatures
- [ ] Add v2-specific tests (C2–C4, N4, G8, P3, P5, P8, I5, X1b)
- [ ] Create PersistentMapTests.fs with D1–D6
- [ ] Update Core.Tests.fsproj

**Step 6: Create Documentation**
- [ ] Create README.md with deviations documented
- [ ] Create plan.md (this file) — already done

**Step 7: Build and Test**
- [ ] Verify project compiles cleanly
- [ ] Run all tests, ensure green
- [ ] Verify all v1 tests still pass
- [ ] Verify all v2 tests pass

---

## 10. Acceptance Criteria

1. ✓ All v2 core tests pass (C1–C4, N1–N5, G1–G11, P1–P5/P8, I1–I9, X1a/X1b/X2–X4, D1–D6)
2. ✓ All v1 tests that apply to v2 still pass (demonstrating evolution, not rewrite)
3. ✓ Code compiles cleanly with no warnings
4. ✓ **Core has zero dependencies** (no NuGet packages)
5. ✓ **Core contains no test doubles** (fakes, mocks, spies)
6. ✓ **Core contains no interpreter** (belongs in shell)
7. ✓ **Core contains no port interfaces** (belong in shell)
8. ✓ Core is pure: no mutable state, no I/O, no exceptions
9. ✓ Program type correctly encodes the protocol (unchanged from v1)
10. ✓ All domain types have structural equality and hashing
11. ✓ README documents all deviations
12. ✓ Changes are confined to §13.2 checklist (no §13.1 violations)
13. ✓ Demonstrable evolution from v1: diff shows additive changes only

---

## 11. Resolved Design Decisions Summary

| Question | Resolution | Location in Plan |
|---|---|---|
| Cells representation | Use `Map<Position, Cell>` to enforce at most one cell per position | §2.2.1 |
| `allCellsAndNeighbors` | Remove — v2 uses persistent-map fold strategy | §6 Changes to Make, item 14 |
| `positionOf` | Module function `positionOf cell = cell.Position` | §2.4 |

---

**Status:** Plan written, all design decisions resolved.

**Approval needed:** Please review and respond with "Plan approved" or "Go ahead" before I proceed to Phase 2 — IMPLEMENT.
