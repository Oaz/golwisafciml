# Plan: F# Classic Core (v1) — Functional Core Implementation

**Task:** Implement the functional core for Conway's Game of Life, Version 1 (Monochrome) in F#.

**Location:** `fsharp/classic/core/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

**Owner:** Olivier (Oaz)

---

## 0. Context and Scope

This plan covers the **functional core** for the **classic (v1) monochrome** Game of Life in F#, following the repository's architecture and the language-agnostic specification in `specs.md`.

**Revised understanding:** The core is **pure** — it contains only types, pure functions, and the Program type. It has **no dependencies on ports, no interpreter, no test doubles**. Core tests verify Program structure via pattern matching, not via interpretation.

### What is IN scope

- Complete F# implementation of the **v1 functional core** as defined in `specs.md` §2, §3, and §6 (v1 column)
- All pure domain types: `Position`, `Cells` (set of positions)
- All pure core state: `Core` record with `cells` and `statusVisible`
- All pure functions: neighbors, shouldLive, nextGenerationCells (candidate-set filtering strategy), translate, statusMessage
- All core operations producing programs: nextGenerationProgram, addPatternProgram, switchStatusVisibilityProgram
- The **free-monad-inspired Program type** (recursive DU with computation expressions)
- Full test suite for v1: Groups C (C1), N (N1–N3, N5), G (G1–G7, G9–G11), P (P1–P2, P4), I (I1–I4, I8–I9), X (X1a, X2–X3, X4)

### What is OUT of scope (deliberately)

- Version 2 (multi-breed) — will be implemented later as an evolution of this v1 codebase (§13.2)
- Imperative shell (separate task: `fsharp/classic/<environment>/` folders)
- **Interpreter** — belongs in the shell, not the core
- **Ports and port interfaces** — belong in the shell
- **Demo scene seeding** — shell responsibility
- **Pattern catalog (19 patterns)** — fully handled by the shell's PatternStore
- Any v2-specific types: Breed, cross_breed, currentPattern field, defineCurrentPatternProgram
- Persistent map implementation (only needed for v2 neighbor counting strategy)
- Any test doubles (fakes, mocks, spies) — **core testing requires none**

---

## 1. File and Module Layout

```
fsharp/classic/core/
├── Core.fs              # Domain types, core state, pure functions
├── Program.fs           # Program type, core operations
├── Core.fsproj          # F# project file for the core library
├── Tests/
│   ├── CoreTests.fs     # Tests for domain types and pure functions
│   ├── ProgramTests.fs  # Tests for Program structure and core operations
│   └── PropertyTests.fs  # Property-based tests (where applicable)
└── README.md            # Core-specific documentation
```

### File Responsibilities

| File | Responsibility | Key Types/Functions |
|------|---------------|---------------------|
| `Core.fs` | Domain model and pure logic | Position, Cells, Core, neighbors, shouldLive, nextGenerationCells, translate, statusMessage, withCells, withStatusVisible, cellsOf, statusVisibleOf |
| `Program.fs` | Program type and core operations | Program<'a>, CoreProgram, ProgramBuilder, nextGenerationProgram, addPatternProgram, switchStatusVisibilityProgram |
| `Core.fsproj` | Build configuration | Library project with **zero dependencies** |
| `Tests/*.fs` | Test suite | Pure function tests + Program structure verification |
| `README.md` | Documentation | F#-specific notes, deviations from spec defaults |

---

## 2. Language-Specific Design Decisions (F# Idioms)

### 2.1 Build Tooling

- **.NET SDK** project file (`Core.fsproj`) targeting .NET 10.0 (latest LTS)
- **Multi-file compilation**: files ordered by dependency (Core.fs, Program.fs)
- **Project type**: Class library (DLL) — environments will reference this via `ProjectReference`
- **Target framework**: `net10.0`
- **LangVersion**: `latest`
- **Zero external dependencies** — core depends only on .NET BCL

### 2.2 Data Representations

#### Position
```fsharp
type Position = { X: int; Y: int }
    with
    static member create x y = { X = x; Y = y }
    override this.Equals(other) = 
        match other with
        | :? Position as p -> this.X = p.X && this.Y = p.Y
        | _ -> false
    override this.GetHashCode() = 
        System.HashCode.Combine(this.X, this.Y)
```
- Record type with structural equality and proper hashing
- Int32 coordinates (see §7.2 for rationale)
- Immutability: records are immutable by default in F#

#### Cells
```fsharp
type Cells = Set<Position>
```
- F# `Set<'T>` is the idiomatic immutable set
- Provides structural equality, proper hashing, efficient membership
- Automatically handles uniqueness (no duplicates)
- Order-insensitive equality as required by spec §2.2

#### Core State
```fsharp
type Core = {
    Cells: Cells
    StatusVisible: bool
}

let initialCore = {
    Cells = Set.empty
    StatusVisible = false
}

let withCells core cells = { core with Cells = cells }
let withStatusVisible core visible = { core with StatusVisible = visible }

let cellsOf core = core.Cells
let statusVisibleOf core = core.StatusVisible
```
- Record type for core state
- Field-update functions create new records (immutable)
- Observer functions are simple accessors

### 2.3 Neighbor Memoization

```fsharp
let private neighborCache = System.Collections.Concurrent.ConcurrentDictionary<Position, Set<Position>>()

let neighbors (position: Position) : Set<Position> =
    neighborCache.GetOrAdd(position, fun p ->
        Set.ofList [
            { X = p.X - 1; Y = p.Y - 1 }; { X = p.X; Y = p.Y - 1 }; { X = p.X + 1; Y = p.Y - 1 }
            { X = p.X - 1; Y = p.Y     };                          { X = p.X + 1; Y = p.Y     }
            { X = p.X - 1; Y = p.Y + 1 }; { X = p.X; Y = p.Y + 1 }; { X = p.X + 1; Y = p.Y + 1 }
        ])
```
- Uses `ConcurrentDictionary` for thread-safe memoization
- Observationally pure: same input → same output
- Thread-safety for library use from multiple environments

### 2.4 Program Type (Free-Monad-Inspired with Computation Expressions)

To showcase the Functional Core / Imperative Shell pattern and prepare for future elaborations, we use a **monadic Program type** with **computation expressions** rather than explicit DU construction. While explicit DUs would be sufficient and idiomatic for the current small instruction set (5 opcodes, short programs), the CE approach:

- Demonstrates F#'s ability to create domain-specific effect systems
- Scales better as the instruction set grows in future versions
- Avoids a potential refactor from DU to CE later
- Still maintains full testability via the underlying Program type

```fsharp
/// The program type: a generic free-monad-inspired encoding.
/// The 'a type parameter represents the return value (Core for our operations).
type Program<'a> =
    | Pure of 'a
    | ShowCells of Cells * Program<'a>
    | DisplayMessage of string * Program<'a>
    | HideMessage of Program<'a>
    | GetPatternCells of string * (Cells -> Program<'a>)

/// Specialized type alias for core operations (return Core)
type CoreProgram = Program<Core>

/// Computation expression builder for Program
/// Enables imperative-looking syntax while building pure Program values
type ProgramBuilder() =
    member _.Bind(m: Program<'a>, f: 'a -> Program<'b>) : Program<'b> =
        match m with
        | Pure x -> f x
        | ShowCells (cells, next) -> ShowCells (cells, this.Bind(next, f))
        | DisplayMessage (msg, next) -> DisplayMessage (msg, this.Bind(next, f))
        | HideMessage next -> HideMessage (this.Bind(next, f))
        | GetPatternCells (name, cont) -> GetPatternCells (name, fun cells -> this.Bind(cont cells, f))
    
    member _.Return(x: 'a) : Program<'a> = Pure x

/// Create a program builder instance
let program = ProgramBuilder()
```

**Key properties:**
- `Pure` / `Return` is a leaf — no instructions can follow it (type-enforced)
- Only `GetPatternCells` has a response-carrying continuation
- Pure data structure — no behavior, no side effects
- The shell's interpreter will pattern match on this type
- Computation expression syntax (`program { ... }`) provides a clean imperative-like surface

**Why this design:** While explicit DU construction (e.g., `ShowCells(cells, DisplayMessage(msg, Return core))`) would be perfectly adequate for v1's small scale, the CE approach better showcases F#'s capabilities and provides a more maintainable foundation as the instruction set potentially grows in future versions or more elaborate variants of the game.

### 2.5 Core Operations (Using Computation Expressions)

All core operations use the `program { ... }` computation expression to build Programs in an imperative-looking style while remaining pure:

```fsharp
let nextGenerationProgram (core: Core) : CoreProgram = program {
    let newCells = nextGenerationCells (cellsOf core)
    let core' = withCells core newCells
    
    do! ShowCells newCells
    
    if statusVisibleOf core then
        do! DisplayMessage (statusMessage newCells)
    
    return core'
}

let addPatternProgram (core: Core) (patternName: string) (reference: Position) : CoreProgram = program {
    let! patternCells = GetPatternCells patternName
    let translated = translate patternCells reference
    let newCells = Set.union (cellsOf core) translated
    let core' = withCells core newCells
    
    do! ShowCells newCells
    
    if statusVisibleOf core then
        do! DisplayMessage (statusMessage newCells)
    
    return core'
}

let switchStatusVisibilityProgram (core: Core) : CoreProgram = program {
    if statusVisibleOf core then
        do! HideMessage
        return withStatusVisible core false
    else
        do! DisplayMessage (statusMessage (cellsOf core))
        return withStatusVisible core true
}
```

**Note:** These are **pure functions**. The `do!` and `let!` syntax constructs pure Program values — no side effects occur until the shell interprets them.

### 2.6 Neighbor Counting Strategy (v1)

Per spec §2.4.1, v1 uses candidate-set filtering:

```fsharp
let allCellsAndNeighbors (cells: Cells) : Cells =
    let allPositions =
        seq {
            for cell in cells do
                yield cell
                for neighbor in (neighbors cell) do
                    yield neighbor
        }
    Set.ofSeq allPositions

let nextGenerationCells (cells: Cells) : Cells =
    let candidates = allCellsAndNeighbors cells
    Set.filter (fun position ->
        let isAlive = Set.contains position cells
        let liveNeighborCount = 
            (neighbors position) |> Set.filter (fun n -> Set.contains n cells) |> Set.count
        shouldLive isAlive liveNeighborCount
    ) candidates
```

### 2.7 Status Message

```fsharp
let statusMessage (cells: Cells) : string =
    let n = Set.count cells
    match n with
    | 0 -> "No cell is alive"
    | 1 -> "1 cell is alive"
    | _ -> sprintf "%d cells are alive" n
```

Exact normative text from spec §2.6.

### 2.8 Translation

```fsharp
let translate (points: Cells) (p: Position) : Cells =
    Set.map (fun q -> { X = q.X + p.X; Y = q.Y + p.Y }) points
```

### 2.9 Life Rules

```fsharp
let shouldLive (isAlive: bool) (liveNeighborCount: int) : bool =
    if isAlive then
        liveNeighborCount = 2 || liveNeighborCount = 3
    else
        liveNeighborCount = 3
```

---

## 3. Project File Structure

### Core.fsproj

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

- **Zero dependencies** — only .NET BCL
- Files ordered by dependency
- `GenerateProgramFile>false` for cleaner output

---

## 4. Testing Framework

### 4.1 Test Project Structure

```
fsharp/classic/core/Tests/
├── CoreTests.fs     # Pure function tests (C, N, G, P, X)
├── ProgramTests.fs  # Program structure tests (I1–I4, I8–I9)
├── PropertyTests.fs # Property-based tests (G12)
└── Core.Tests.fsproj
```

### 4.2 Test Framework Choice

**Decision:** Use **Expecto** (pure F#, lightweight, functional style).

**Rationale:**
- Pure F#, no C# dependencies
- Functional style matches our domain
- FsCheck integration for property-based tests
- Lightweight, single-file inclusion possible

**Alternative:** xUnit would work but adds C#-style attributes and ceremony.

### 4.3 Test Project File (Core.Tests.fsproj)

```xml
<Project Sdk="Microsoft.NET.Sdk">

  <PropertyGroup>
    <TargetFramework>net10.0</TargetFramework>
    <ImplicitUsings>enable</ImplicitUsings>
  </PropertyGroup>

  <ItemGroup>
    <PackageReference Include="Expecto" Version="12.0.0" />
    <PackageReference Include="FsCheck" Version="4.0.0" />
    <PackageReference Include="FsCheck.Expecto" Version="4.0.0" />
  </ItemGroup>

  <ItemGroup>
    <ProjectReference Include="..\Core.fsproj" />
    <Compile Include="CoreTests.fs" />
    <Compile Include="ProgramTests.fs" />
    <Compile Include="PropertyTests.fs" />
  </ItemGroup>

</Project>
```

### 4.4 Core Testing Philosophy

**The core is pure. Therefore core tests need NO test doubles (no fakes, no mocks, no spies).**

**Two test categories:**

1. **Pure function tests** — Directly test functions like `neighbors`, `nextGenerationCells`, `statusMessage`
   - No setup needed, just call the function with inputs and check outputs
   - Example: `Expect.equal (neighbors origin) (Set.ofList [all 8 neighbors])`

2. **Program structure tests** — Verify the shape of Programs produced by core operations
   - Use pattern matching to inspect the underlying `Program<Core>` structure
   - For `GetPatternCells`: extract the continuation and call it with test data
   - Example for I9 (unknown pattern):
     ```fsharp
     let program = addPatternProgram core "nonexistent" (Position.create 0 0)
     match program with
     | GetPatternCells (name, cont) ->
         // Call continuation with empty cells (what a real store would return)
         let resultProgram = cont Set.empty
         // Verify resultProgram shows unchanged cells and returns original core
         match resultProgram with
         | ShowCells (cells, next) ->
             Expect.equal cells (cellsOf core) "Cells should be unchanged"
             match next with
             | Pure returnedCore -> Expect.equal returnedCore core "Core should be unchanged"
             | _ -> Tests.fail "Expected Pure"
         | _ -> Tests.fail "Expected ShowCells"
     | _ -> Tests.fail "Expected GetPatternCells"
     ```

**No PatternStore fake needed** — we directly call continuations with test data, simulating what the interpreter would do.

### 4.5 Test Organization by Spec Group

#### Pure Function Tests (CoreTests.fs)

**Group C — Cell algebra:**
- C1: Position construction/accessor round-trip with various coordinates

**Group N — Neighbors and counting:**
- N1: neighbors returns exactly 8 distinct positions
- N2: memoized neighbors is observationally pure
- N3: count lookup works correctly
- N5: neighborCache doesn't mutate inputs (immutability)

**Group G — Life rules:**
- G1: shouldLive truth table (18 cases: alive/die for counts 0-8, dead/born for counts 0-8)
- G2: oscillator periods (blinker period 2, toad period 2, beacon period 2)
- G3: still lifes are fixed points (block, beehive, loaf, boat, tub)
- G4: pentadecathlon period 15
- G5: glider translated by (1,1) after 4 generations
- G6: two-generation chain (Appendix D fixture)
- G7: extinction (single cell, 2-cell line)
- G9: purity (nextGeneration twice on same input yields equal results)
- G11: unboundedness (large coordinates behave correctly)

**Group P — Patterns, translation, status:**
- P1: statusMessage grammar (0, 1, 2+ cells)
- P2: translate offsets correctly
- P4: translate is a bijection on shapes (cardinality preserved)

**Group X — Cross-cutting:**
- X1a: initialCore defaults
- X2: field updates are non-destructive
- X3: catalog cardinalities
- X4: startup demo scene — **shell responsibility; core only provides Program type**

#### Program Structure Tests (ProgramTests.fs)

**Group I — Programs and protocol:**
- I1: instruction identity — all 5 opcodes are distinguishable (pattern match on each)
- I2: nextGenerationProgram shape — verify emitted instructions in correct order
- I3: switchStatusVisibilityProgram shape
- I4: addPatternProgram shape
- I8: program well-formedness — every operation's program ends with Return
- I9: unknown pattern — continuation handles empty Cells correctly

**Tests NOT in core suite (shell responsibility):**
- I5: v2-only
- I6: program equivalence (v2-only, requires both encodings)
- I7: determinism under interpretation (requires interpreter)
- I10: interpreter dispatch (requires interpreter)
- I11: full loop (requires interpreter)

These interpreter tests belong in the **shell test suite**, where the interpreter lives.

#### Property-Based Tests (PropertyTests.fs)

**Group G12:** Property-based tests where supported
- For arbitrary finite cell sets: candidates ⊇ cells; neighbor symmetry; output ⊆ candidates; status count == |cells|

---

## 5. Public API

### Core.fs

```fsharp
namespace GameOfLife.Classic.Core

// Domain types
type Position = { X: int; Y: int }
type Cells = Set<Position>
type Core = { Cells: Cells; StatusVisible: bool }

// Constructors and observers
val initialCore : Core
val withCells : Core -> Cells -> Core
val withStatusVisible : Core -> bool -> Core
val cellsOf : Core -> Cells
val statusVisibleOf : Core -> bool

// Domain functions
val neighbors : Position -> Set<Position>
val shouldLive : bool -> int -> bool
val nextGenerationCells : Cells -> Cells
val translate : Cells -> Position -> Cells
val statusMessage : Cells -> string
```

### Program.fs

```fsharp
namespace GameOfLife.Classic.Core

// Program type — generic free-monad-inspired effect system
type Program<'a> =
    | Pure of 'a
    | ShowCells of Cells * Program<'a>
    | DisplayMessage of string * Program<'a>
    | HideMessage of Program<'a>
    | GetPatternCells of string * (Cells -> Program<'a>)

// Specialized type for core operations (always return Core)
type CoreProgram = Program<Core>

// Computation expression builder
type ProgramBuilder() =
    member Bind : Program<'a> * ('a -> Program<'b>) -> Program<'b>
    member Return : 'a -> Program<'a>

val program : ProgramBuilder

// Core operations (pure: Core + args -> CoreProgram)
val nextGenerationProgram : Core -> CoreProgram
val addPatternProgram : Core -> string -> Position -> CoreProgram
val switchStatusVisibilityProgram : Core -> CoreProgram
```

**Note:** No interpreter. No port interfaces. Pure Program construction only. The computation expression builder enables clean syntax while maintaining purity.

---

## 6. Deviations from Spec and F#-Specific Choices

### 6.1 Deviation: Program Encoding (Free-Monad with Computation Expressions)

**Spec:** §3.2 defines `Program ::= Finish | Step Instruction Rest` with `Rest ::= Next | With`.

**F# Implementation:** Generic `Program<'a>` type with computation expression builder (`program { ... }`). While the spec's hand-rolled encoding would be sufficient for v1, we use a more elaborate free-monad-style with CEs to showcase F# capabilities and prepare for future growth.

**Rationale:** 
- **Showcases F# and FC/IS pattern** — Demonstrates how F# can create elegant domain-specific effect systems for functional cores
- **Future-proof** — Scales gracefully if instruction set grows; avoids refactor from DU to CE later
- **Readability** — CE syntax provides imperative-looking surface while remaining pure
- **Spec-compliant** — Underlying type remains a free-monad by hand (§10), fully conforming
- **Testable** — Pattern matching on `Program<'a>` works naturally for tests

### 6.2 Choice: Set Ordering Policy

**Spec:** §2.2 — `Cells` is a set with order-insensitive equality.

**F# Implementation:** `Set<Position>` — equality is order-insensitive (structural). Internal ordering is tree-based but irrelevant for spec compliance.

### 6.3 Choice: Memoization Mechanism

**Spec:** §2.3 — memoization of `neighbors` required where idiom exists.

**F# Implementation:** `ConcurrentDictionary<Position, Set<Position>>` — thread-safe, observationally pure.

### 6.4 Choice: Coordinate Type

**Spec:** §2.1 — unbounded signed integers.

**F# Implementation:** `int` (System.Int32) — range [-2^31, 2^31-1].

**Rationale:** Practically unbounded; passes G11 (±10^6) comfortably; memory/performance efficient.

### 6.5 Choice: No Packed Encoding

**Spec:** Appendix E — optional packed encoding.

**F# Decision:** Use value-record model (Position record, Set<Position>). More idiomatic, type-safe, maintains structural equality/hashing.

---

## 7. Evolution Considerations (v1 → v2)

### 7.1 What Must NOT Change (per §13.1)

- Instruction set (5 opcodes) — new constructors can be added to Program type
- Program shape — recursive DU extends naturally
- Life rules, neighbor function, status grammar — unchanged
- Pattern catalog — same 19 patterns
- Demo scene — same 13 stamps

### 7.2 What Will Change (per §13.2)

1. Add `Breed` type (0-15)
2. Change `Cell` from `Position` to `{ Position: Position; Breed: Breed }`
3. Add `positionOf` projection
4. Add `cross_breed` function
5. Change `Cells` to `Set<Cell>` with position-based equality
6. Add `CurrentPattern` field to Core
7. Add `withCurrentPattern`, `currentPatternOf`
8. Add `defineCurrentPatternProgram` operation
9. Change `addPatternProgram` reference to `Cell`, add `"current"` sentinel
10. Change neighbor counting to persistent-map fold

### 7.3 Design for Easy Evolution

The free-monad Program type makes v2 evolution straightforward:
- New operations just add new Program constructors or new core operations
- Existing interpreter (in shell) gains new match cases
- `defineCurrentPatternProgram` just emits `Return (withCurrentPattern core name)`
- No changes to existing Program constructors needed

---

## 8. Resolved Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| **Test framework** | Expecto | Pure F#, lightweight, functional style, FsCheck integration |
| **Coordinate type** | Int32 | Sufficient range (±2 billion), passes G11 (±10^6), memory/performance efficient |
| **Program encoding** | Computation Expressions | Showcases F# capabilities and Functional Core / Imperative Shell pattern; prepares for future elaborations |

**Note on Program encoding:** While explicit discriminated union construction would be perfectly adequate and idiomatic for v1's current scale (5 instructions, short programs), we choose computation expressions to:

1. **Showcase F#** — Demonstrates F#'s ability to create elegant domain-specific effect systems that embody the Functional Core / Imperative Shell architecture
2. **Future-proof** — The CE approach scales gracefully if the instruction set grows in future versions or more elaborate game variants; we avoid a potential refactor from DU construction to CE later
3. **Educational value** — Makes the core code more approachable and demonstrates how pure functional cores can still have readable, imperative-looking syntax for program construction

The underlying `Program<'a>` type remains fully inspectable for testing via pattern matching, so we retain all testability benefits while gaining the syntactic benefits of CEs.

---

## 9. Implementation Checklist

### Phase 2 (after approval)

- [ ] Create directory structure
- [ ] Create `Core.fsproj` (zero dependencies)
- [ ] Implement `Core.fs` (types and pure functions)
- [ ] Implement `Program.fs` (Program type, computation expression builder, and operations)
- [ ] Create test project `Core.Tests.fsproj`
- [ ] Implement `CoreTests.fs` (C, N, G, P, X)
- [ ] Implement `ProgramTests.fs` (I1–I4, I8–I9)
- [ ] Implement `PropertyTests.fs` (G12)
- [ ] Create `README.md` (deviations documented)
- [ ] Run all tests, ensure green
- [ ] Verify compliance with spec

---

## 10. Acceptance Criteria

1. All v1 core tests pass (C1, N1–N3/N5, G1–G7/G9–G11, P1–P2/P4, I1–I4/I8–I9, X1a/X2–X3/X4)
2. Code compiles cleanly with no warnings
3. **Core has zero dependencies** (no NuGet packages)
4. **Core contains no test doubles** (fakes, mocks, spies)
5. **Core contains no interpreter** (belongs in shell)
6. **Core contains no port interfaces** (belong in shell)
7. Core is pure: no mutable state, no I/O, no exceptions
8. Program type correctly encodes the protocol
9. All domain types have structural equality and hashing
10. README documents all deviations

---

**Status:** Plan written (revised: computation expressions for Program, patterns in shell, no interpreter, no port interfaces, no test doubles), awaiting human review and approval.

**Approval needed:** Please review and respond with "Plan approved" or "Go ahead" before I proceed to Phase 2 — IMPLEMENT.

All design decisions have been resolved (see §8).
