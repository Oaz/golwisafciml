# Plan: Rust Multi-Breed Core (v2) — Functional Core Implementation

**Task:** Implement the functional core for Conway's Game of Life, Version 2 (Multi-Breed) in Rust.

**Location:** `rust/multibreed/core/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

**Status:** Awaiting explicit approval before Phase 2 implementation

---

## 0. Context and Scope

This plan covers the **functional core** for the **v2 multi-breed** Game of Life in Rust, following the repository's architecture and the language-agnostic specification in `specs.md`.

This is a **direct evolution** of the existing `rust/classic/core` implementation. Per spec §5 and §13, **v2 must be produced by editing the v1 codebase** — the interpreter, instruction set, status grammar, Life rule, and shell wiring must NOT be rewritten.

### What is IN scope

- Complete Rust implementation of the **v2 functional core** as defined in `specs.md` §2, §3, §6 (v2 column), and §13.2
- All pure domain types: `Position`, `Breed` (0-15), `Cell` (Position + Breed), `Cells` (Map-based)
- All pure core state: `Core` with `cells`, `status_visible`, and **`current_pattern`**
- All pure functions: `neighbors`, `should_live`, `cross_breed`, `position_of`, `bump`, `next_generation_cells` (persistent-map fold strategy), `translate` (with cross-breed), `status_message`
- All core operations producing programs: `next_generation_program`, `add_pattern_program` (Cell reference, "current" sentinel), `switch_status_visibility_program`, **`define_current_pattern_program`**
- The **Program enum type** (unchanged from v1 - no new opcodes)
- Full test suite for v2: ALL v1 tests (Groups C, N, G, P, X, I) PLUS new v2 tests (C2-C4, N4, G8, P3, P4, P8, I5, X1b)

### What is OUT of scope (deliberately)

- Version 1 (classic) — already implemented in `rust/classic/core/`
- Imperative shell (separate task: `rust/multibreed/<environment>/` folders)
- **Interpreter** — belongs in the shell, NOT in the core (must remain unchanged from v1)
- **Ports and port interfaces** — belong in the shell
- **Demo scene seeding** — shell responsibility (same 13 stamps with breeds applied)
- **Pattern catalog (19 patterns)** — handled by the shell's PatternStore (with breed support)
- **Rendering** — shell responsibility (normative palette in Appendix B)
- Any change to the 5 instruction opcodes
- Any change to the interpreter structure
- Any change to the Life rules (`should_live`)
- Any change to the status-message grammar

---

## 1. Decisions (Phase 0 - All Prerequisites Met)

### 1.1 External Dependencies (Decided)

| Dependency | Version | Purpose | Status |
|------------|---------|---------|--------|
| **im-rs** | Latest stable | Idiomatic persistent immutable map for neighbor counting | **CHOSING THIS** |
| rpds | Alternative | Another persistent data structures crate | Alternative |

**Decision: Use `im-rs` crate**

**Rationale:**
- `im-rs` is explicitly mentioned in spec §2.5 as the idiomatic persistent map for Rust
- Provides `HashMap` and `OrdMap` types that are persistent (immutable)
- Well-maintained, widely used
- Implements all required operations: `insert`, `get`, `fold`, `keys`, `values`, `entries`
- Satisfies all D1-D6 test requirements

**Cargo.toml dependency:**
```toml
[dependencies]
im = "15.1"  # or latest stable version
```

**Note:** This is the ONLY external dependency for v2 core. v1 maintained zero dependencies; v2 adds exactly one for the persistent map.

### 1.2 Breed Representation (Decided)

**Decision: Use `u8` with validation in constructor**

```rust
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Breed(u8);

impl Breed {
    /// Smart constructor: ensures breed is in range 0-15
    pub fn new(value: u8) -> Self {
        // Mask to 4 bits (0-15)
        Self(value & 0b0000_1111)
    }
    
    pub fn value(&self) -> u8 {
        self.0
    }
}
```

**Rationale:**
- `u8` is efficient (1 byte)
- Smart constructor ensures valid range
- `Copy` trait for cheap copying
- `Hash` and `Eq` for use in maps and sets
- Bitwise AND for efficient mod 16 operation

**Alternative considered:** Enum with 16 variants. Rejected because it's verbose and doesn't provide significant type safety benefits over `u8`.

### 1.3 Cell Representation (Decided)

**Decision: Struct with `position` and `breed` fields**

```rust
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Cell {
    pub position: Position,
    pub breed: Breed,
}

impl Cell {
    pub fn new(position: Position, breed: Breed) -> Self {
        Self { position, breed }
    }
}
```

**Rationale:**
- Clean, idiomatic Rust
- Both fields are value types with structural equality and hashing
- `Copy` trait works because both Position (Copy) and Breed (Copy) are Copy
- Matches spec's definition: `Cell = { position : Position, breed : Breed }`

### 1.4 Cells Collection (Decided)

**Decision: Use `im::HashMap<Position, Cell>`**

```rust
use im::HashMap;

pub type Cells = HashMap<Position, Cell>;
```

**Rationale:**
- Idiomatic persistent map for Rust (per spec §2.5)
- Enforces **at most one cell per position** at the type level (Map key is Position)
- Efficient O(log n) operations (depending on hash quality)
- Persistent: all operations return new maps, old maps remain valid
- Provides `fold`, `insert`, `get`, `keys`, `values`, `entries` operations
- Order-insensitive equality based on key-value pairs (satisfies spec §2.2)

**Alternative considered:** `im::OrdMap<Position, Cell>` (B-tree based). Provides ordered iteration by key, but HashMap is faster for typical use cases and satisfies all spec requirements.

**Important:** Unlike v1's `HashSet<Position>`, v2's `Cells` is a map from Position to Cell. This allows:
- Efficient lookup by position (needed for neighbor counting)
- Enforcing "at most one cell per position" constraint
- Storing breed information per position

### 1.5 PositionOf Projection (Decided)

**Decision: Method on Cell struct**

```rust
impl Cell {
    pub fn position(&self) -> Position {
        self.position
    }
}
```

**Alternative:** Standalone function `position_of(cell: &Cell) -> Position`. Rejected in favor of method syntax which is more idiomatic Rust.

### 1.6 Core State Addition (Decided)

**Decision: Add `current_pattern` field to Core**

```rust
#[derive(Debug, Clone, PartialEq)]
pub struct Core {
    cells: Cells,
    status_visible: bool,
    current_pattern: String,  // Empty string when none selected
}

impl Core {
    pub fn initial() -> Self {
        Self {
            cells: Cells::new(),
            status_visible: false,
            current_pattern: String::new(),
        }
    }
    
    // PRIVATE field update methods - used only by core operations within the same crate
    // NOT exported to shell
    fn with_cells(self, cells: Cells) -> Self { ... }
    fn with_status_visible(self, status_visible: bool) -> Self { ... }
    
    // NEW for v2 - private field update
    fn with_current_pattern(self, current_pattern: String) -> Self {
        Self { current_pattern, ..self }
    }
}
```

**Rationale:**
- `pub fn initial()` is **public** because the shell needs it to initialize state at startup (spec §4.4)
- All field update methods (`with_*`) are **private** — only core operations (same crate) can use them
- No observer methods needed — core operations access fields directly within the same crate
- Field update method `with_current_pattern()` for `define_current_pattern_program`

---

## 2. File and Module Layout

```
rust/multibreed/
└── core/
    ├── Cargo.toml          # Package manifest (library crate, depends on im-rs)
    ├── src/
    │   ├── lib.rs          # Root module, re-exports public API
    │   ├── position.rs     # Position type (UNCHANGED from v1)
    │   ├── breed.rs        # Breed type (NEW for v2)
    │   ├── cell.rs         # Cell type (NEW for v2)
    │   ├── cells.rs        # Cells type alias (CHANGED from HashSet to im::HashMap)
    │   ├── core.rs         # Core state type (MODIFIED: + current_pattern field)
    │   ├── life.rs         # Life rules (MODIFIED: neighbor counting strategy)
    │   ├── pattern.rs      # Translation and status (MODIFIED: translate with cross-breed)
    │   ├── program.rs      # Program type and operations (MODIFIED: add define_current_pattern_program)
    │   └── tests.rs        # ALL core tests (v1 tests + new v2 tests)
    └── README.md           # Core-specific documentation
```

**Evolution strategy:** Copy `rust/classic/core/` to `rust/multibreed/core/` and edit files as needed. This demonstrates the "evolution, not rewrite" principle.

### File Responsibilities

| File | Responsibility | Changes from v1 |
|------|---------------|------------------|
| `position.rs` | Position domain type | **UNCHANGED** — reused as-is from v1 |
| `breed.rs` | Breed type (0-15) | **NEW** — v2 only |
| `cell.rs` | Cell type (Position + Breed) | **NEW** — v2 only |
| `cells.rs` | Cells collection type | **MODIFIED** — was `HashSet<Position>`, now `im::HashMap<Position, Cell>` |
| `core.rs` | Core state (opaque type) | **MODIFIED** — add `current_pattern` field and methods |
| `life.rs` | Life game logic | **MODIFIED** — change neighbor counting to persistent-map fold strategy |
| `pattern.rs` | Pattern and status functions | **MODIFIED** — change `translate` to accept Cell reference, add cross-breed in translation |
| `program.rs` | Program type and operations | **MODIFIED** — add `define_current_pattern_program`, modify `add_pattern_program` for Cell reference and "current" sentinel |
| `lib.rs` | Public API | **MODIFIED** — add new exports (Breed, Cell, cross_breed, etc.) |
| `tests.rs` | All core unit tests | **MODIFIED** — add v2-specific tests |
| `Cargo.toml` | Package manifest | **MODIFIED** — add `im = "15.1"` dependency |
| `README.md` | Documentation | **MODIFIED** — document v2 changes and deviations |

### What Does NOT Change (Verifying §13.1 Compliance)

✅ **Instruction set:** 5 opcodes unchanged (ShowCells, DisplayMessage, HideMessage, GetPatternCells, Return)
✅ **Program shape:** Same enum structure, same instruction semantics
✅ **Interpreter:** NOT in core — belongs in shell, unchanged from v1
✅ **Life truth table:** `should_live` function unchanged
✅ **Neighbor function:** `neighbors` function unchanged (still computes 8 Moore neighbors)
✅ **Status-message grammar:** `status_message` function unchanged
✅ **Pattern catalog:** Same 19 patterns (breed data added in shell/store, not core)
✅ **Startup demo scene:** Shell responsibility — same 13 stamps
✅ **Architecture:** Pure core, dumb shell, single mutation point, ports, test framework — all unchanged
✅ **Shell wiring:** NOT in core — belongs in shell, unchanged from v1

---

## 3. Language-Specific Design Decisions (Rust Idioms)

### 3.1 Build Tooling and Project Structure

- **Package type**: Library crate (`crate-type = ["lib"]`)
- **Cargo edition**: 2021
- **Crate name**: `gol-multibreed-core`
- **External dependencies**: `im = "15.1"` (only dependency)
- **Workspace integration**: This crate will be a member of the `rust/` Cargo workspace; environment crates will depend on it via `path = "../core"`

**Cargo.toml structure:**
```toml
[package]
name = "gol-multibreed-core"
version = "0.1.0"
edition = "2021"
description = "Functional core for Conway's Game of Life (v2) - Multi-Breed"
license = "AGPL-3.0"

[lib]
crate-type = ["lib"]

[dependencies]
im = "15.1"
```

### 3.2 Data Representations

#### Position (UNCHANGED from v1)

Same as v1 implementation:

```rust
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Position {
    pub x: i32,
    pub y: i32,
}

impl Position {
    pub const fn new(x: i32, y: i32) -> Self {
        Self { x, y }
    }
}
```

#### Breed (NEW for v2)

```rust
/// A breed value in range 0-15, rendered as a color.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Breed(u8);

impl Breed {
    /// Creates a new Breed from a u8 value, masking to 4 bits (0-15).
    pub const fn new(value: u8) -> Self {
        Self(value & 0b0000_1111)
    }
    
    /// Returns the underlying u8 value (0-15).
    pub const fn value(&self) -> u8 {
        self.0
    }
    
    /// All possible breed values.
    pub const fn all() -> [Self; 16] {
        [
            Self(0), Self(1), Self(2), Self(3),
            Self(4), Self(5), Self(6), Self(7),
            Self(8), Self(9), Self(10), Self(11),
            Self(12), Self(13), Self(14), Self(15),
        ]
    }
}
```

**Helper implementations:**
- `From<u8>` trait for `Breed::new(value)` via `Breed::from(value)`
- `Into<u8>` trait for `breed.value()` via `breed.into()`

#### Cell (NEW for v2)

```rust
/// A cell at a position with a breed.
/// Implements Copy because both Position and Breed are Copy.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Cell {
    pub position: Position,
    pub breed: Breed,
}

impl Cell {
    pub const fn new(position: Position, breed: Breed) -> Self {
        Self { position, breed }
    }
    
    /// Projects a Cell to its Position, dropping the breed.
    pub const fn position(&self) -> Position {
        self.position
    }
    
    pub const fn breed(&self) -> Breed {
        self.breed
    }
}
```

#### Cells (MODIFIED from v1)

```rust
use im::HashMap;
use crate::{cell::Cell, position::Position};

/// A collection of cells, mapping each position to at most one cell.
/// Uses im::HashMap for persistent (immutable) map operations.
pub type Cells = HashMap<Position, Cell>;

/// Extension trait for Cells operations (optional, for ergonomics)
pub trait CellsExt {
    /// Returns all positions in this Cells collection.
    fn positions(&self) -> std::collections::HashSet<Position>;
    
    /// Returns all cells as a Vec<Cell>.
    fn to_vec(&self) -> Vec<Cell>;
}
```

**Rationale for im::HashMap:**
- Persistent: all operations (insert, remove, update) return new HashMaps
- Efficient for fold operations (needed for neighbor counting)
- Enforces at most one cell per position
- Provides all required operations: `insert`, `get`, `fold`, `keys`, `values`, `entries`

#### Core State (MODIFIED from v1)

```rust
use crate::cells::Cells;

/// The core state of the Game of Life simulation.
/// Opaque type - fields are private to external crates but accessible within the same crate.
#[derive(Debug, Clone, PartialEq)]
pub struct Core {
    cells: Cells,
    status_visible: bool,
    current_pattern: String,  // NEW for v2
}

impl Core {
    /// Creates the initial core state.
    /// **Public**: Shell needs this to initialize the simulation at startup (spec §4.4).
    pub fn initial() -> Self {
        Self {
            cells: Cells::new(),
            status_visible: false,
            current_pattern: String::new(),
        }
    }

    // PRIVATE field update methods - used only by core operations within the same crate
    // NOT exported to shell
    fn with_cells(self, cells: Cells) -> Self {
        Self { cells, ..self }
    }

    fn with_status_visible(self, status_visible: bool) -> Self {
        Self {
            status_visible,
            ..self
        }
    }
    
    // NEW for v2 - private field update
    fn with_current_pattern(self, current_pattern: String) -> Self {
        Self { current_pattern, ..self }
    }
}
```

**Opaque Type Principle:**
- Core fields are private — accessible only within the core crate
- Core operations (in the same crate) can access private fields directly
- The shell (different crate) cannot access any Core fields or methods
- All data flows **out** through Program instructions (`ShowCells`, `DisplayMessage`)
- All state changes flow **in** through core operations returning Programs that end with `Return(new_core)`
- This enforces the Functional Core / Imperative Shell boundary at compile time

### 3.3 New Functions for v2

#### cross_breed

```rust
use crate::breed::Breed;

/// Computes the cross-breed of two breeds: (a + b) mod 16.
/// This is commutative and associative, making fold order-independent.
pub const fn cross_breed(a: Breed, b: Breed) -> Breed {
    // (a + b) mod 16 using bitwise AND
    Breed::new(a.value() + b.value())
}
```

**Note:** The masking in `Breed::new()` automatically handles mod 16, so we don't need explicit modulo operation.

#### position_of

**Decision:** Use the method on Cell instead of a standalone function.

```rust
impl Cell {
    pub const fn position(&self) -> Position {
        self.position
    }
}
```

This is already covered in the Cell implementation above.

#### bump

```rust
use im::HashMap;
use crate::{position::Position, breed::Breed, cell::Cell};

/// Entry type for neighbor counting: (count, breed)
/// Count of live neighbors that have this position as a neighbor.
/// Breed is the running cross-breed sum of all contributing cells.
pub type NeighborCountEntry = (usize, Breed);

/// Type alias for the neighbor count map.
pub type NeighborCounts = HashMap<Position, NeighborCountEntry>;

/// Updates the neighbor count map by adding one more contribution.
/// 
/// - If the position is not in the map: inserts (1, breed)
/// - If the position is in the map: increments count and cross-breeds the breed
/// 
/// This is pure: the input map is not mutated, a new map is returned.
pub fn bump(
    counts: &NeighborCounts,
    position: Position,
    breed: Breed,
) -> NeighborCounts {
    let entry = counts.get(&position);
    
    let new_entry = match entry {
        Some(&(count, existing_breed)) => {
            let new_count = count + 1;
            let new_breed = cross_breed(existing_breed, breed);
            (new_count, new_breed)
        }
        None => {
            (1, breed)
        }
    };
    
    counts.insert(position, new_entry)
}
```

**Note:** `im::HashMap` provides a persistent `insert` method that returns a new map.

### 3.4 Modified Functions for v2

#### neighbors (UNCHANGED)

Same as v1 implementation — no changes needed for v2:

```rust
pub fn neighbors(position: &Position) -> std::collections::HashSet<Position> {
    let mut result = std::collections::HashSet::with_capacity(8);
    for dx in [-1, 0, 1] {
        for dy in [-1, 0, 1] {
            if dx == 0 && dy == 0 {
                continue;
            }
            result.insert(Position::new(position.x + dx, position.y + dy));
        }
    }
    result
}
```

**Note:** We return `std::collections::HashSet<Position>` here, not `Cells`. This is because `neighbors` is a pure function about positions, not cells. The result is used in neighbor counting where we only care about positions.

#### should_live (UNCHANGED)

Same as v1 implementation — Life rules don't change for v2:

```rust
pub fn should_live(is_alive: bool, live_neighbor_count: usize) -> bool {
    if is_alive {
        live_neighbor_count == 2 || live_neighbor_count == 3
    } else {
        live_neighbor_count == 3
    }
}
```

#### next_generation_cells (CHANGED to persistent-map fold strategy)

**Complete replacement** of the v1 candidate-set filtering strategy with the v2 persistent-map fold strategy:

```rust
use im::HashMap;
use crate::{cells::Cells, cell::Cell, position::Position, life::{should_live, neighbors}, bump::bump};

/// Computes the next generation of cells using the persistent-map fold strategy.
/// 
/// Strategy per spec §2.4.2:
/// 1. Build a map `position -> (count, breed)` by folding over all cells and their neighbors
/// 2. For each live cell at position p, fold over its 8 neighbors
/// 3. For each neighbor position n, bump the count and cross-breed with the cell's breed
/// 4. Filter the map: positions where shouldLive(p is in livingPositions, count) is true
/// 5. For surviving/born positions, create a Cell with the folded breed
/// 
/// This produces identical position sets as v1 (test G10).
pub fn next_generation_cells(cells: &Cells) -> Cells {
    // Build the set of living positions
    let living_positions: std::collections::HashSet<Position> = 
        cells.keys().cloned().collect();
    
    // Build neighbor counts map: position -> (count, breed)
    let counts = cells.iter().fold(
        HashMap::new(),
        |acc, (position, cell)| {
            // For each neighbor of this live cell, bump the count
            neighbors(position).iter().fold(
                acc,
                |inner_acc, &neighbor_pos| {
                    bump(inner_acc, neighbor_pos, cell.breed)
                },
            )
        },
    );
    
    // Filter: positions that survive or are born
    counts.iter().fold(
        Cells::new(),
        |acc, (&position, &(count, breed))| {
            let is_alive = living_positions.contains(&position);
            if should_live(is_alive, count) {
                // Create cell at this position with the cross-bred breed
                let new_cell = Cell::new(position, breed);
                acc.insert(position, new_cell)
            } else {
                acc
            }
        },
    )
}
```

**Key points:**
- Uses `im::HashMap` fold operations
- `bump` function handles both first insertion (count=1, breed=contributing) and updates (count++, cross-breed)
- Newborn breed = sum of all contributing live neighbors' breeds mod 16 (via repeated cross_breed)
- Order-independent by construction (cross_breed is commutative and associative)
- Produces identical position sets as v1's `next_generation_cells` (test G10)

#### translate (MODIFIED for cross-breed)

**v1 version:**
```rust
pub fn translate(points: &Cells, reference: &Position) -> Cells
```

**v2 version:**
```rust
use im::HashMap;
use crate::{cells::Cells, cell::Cell, position::Position};

/// Translates all cells in a pattern by:
/// 1. Re-centering on the reference cell's position
/// 2. Cross-breeding each pattern cell's breed with the reference cell's breed
/// 
/// This is a bijection on shapes: relative positions preserved, cardinality preserved (test P4).
pub fn translate(points: &Cells, reference: &Cell) -> Cells {
    points.iter().fold(
        Cells::new(),
        |acc, (&position, &cell)| {
            // New position: offset by reference cell's position
            let new_position = Position::new(
                position.x + reference.position.x,
                position.y + reference.position.y,
            );
            
            // New breed: cross-breed pattern cell's breed with reference cell's breed
            let new_breed = cross_breed(cell.breed, reference.breed);
            
            // Create new cell at new position with new breed
            let new_cell = Cell::new(new_position, new_breed);
            
            acc.insert(new_position, new_cell)
        },
    )
}
```

**Note:** Unlike v1 where `reference` was a `Position`, v2 uses a `Cell` (carrying both position and breed). This enables:
- Position translation (same as v1)
- Breed cross-breeding (new for v2)

#### status_message (UNCHANGED)

Same as v1 implementation — status message doesn't change for v2:

```rust
pub fn status_message(cells: &Cells) -> String {
    let n = cells.len();
    match n {
        0 => "No cell is alive".to_string(),
        1 => "1 cell is alive".to_string(),
        _ => format!("{} cells are alive", n),
    }
}
```

**Note:** `Cells` is now `im::HashMap<Position, Cell>`, but it still has a `len()` method.

### 3.5 Program Operations

#### define_current_pattern_program (NEW for v2)

```rust
use crate::core::Core;

/// Creates a program that sets the current pattern name in core state.
/// This is the v2 evolution: pattern selection is promoted from shell config to core state.
/// 
/// Emits only Return instruction (no effectful instructions).
pub fn define_current_pattern_program(core: &Core, name: &str) -> Program {
    let new_core = core.clone().with_current_pattern(name.to_string());
    Program::ret(new_core)
}
```

**Note:** This is the simplest core operation — it only modifies core state and returns immediately.

#### add_pattern_program (MODIFIED for Cell reference and "current" sentinel)

**v1 version:**
```rust
pub fn add_pattern_program(core: &Core, pattern_name: &str, reference: &Position) -> Program
```

**v2 version:**
```rust
use crate::{core::Core, cell::Cell, cells::Cells, pattern::translate};

/// Creates a program that adds a pattern to the current cells.
/// 
/// v2 changes:
/// - reference is now a Cell (carrying position and breed)
/// - pattern_name can be the sentinel "current" which resolves to core.current_pattern
/// 
pub fn add_pattern_program(core: &Core, pattern_name: &str, reference: &Cell) -> Program {
    let core_ref = core.clone();
    let reference_clone = *reference;
    
    // Resolve "current" sentinel to the stored current_pattern name
    // Core operations are in the same crate, so can access private fields directly
    let resolved_name = if pattern_name == "current" {
        core_ref.current_pattern.to_string()
    } else {
        pattern_name.to_string()
    };
    
    Program::get_pattern_cells(
        resolved_name,
        move |pattern_cells: Cells| {
            let translated = translate(&pattern_cells, &reference_clone);
            
            // Merge: union of existing cells and translated pattern cells
            // If a position exists in both, the translated cell overwrites (same as v1)
            let new_cells = core_ref.cells.clone().union(translated);
            let core_with_new = core_ref.with_cells(new_cells);
            
            if core_ref.status_visible {
                Program::display_message(
                    status_message(&core_with_new.cells),
                    Program::show_cells(core_with_new.cells.clone(), Program::ret(core_with_new)),
                )
            } else {
                Program::show_cells(core_with_new.cells.clone(), Program::ret(core_with_new))
            }
        },
    )
}
```

**Key changes from v1:**
1. `reference` parameter changed from `&Position` to `&Cell`
2. Added "current" sentinel resolution
3. Core operations access private fields directly (same crate) - no observer methods needed

#### next_generation_program (MODIFIED for v2 Cells)

Minimal changes needed — the logic is the same, but we use v2 types:

```rust
use crate::{core::Core, life::next_generation_cells, pattern::status_message};

pub fn next_generation_program(core: &Core) -> Program {
    // Core operations access private fields directly (same crate)
    let new_cells = next_generation_cells(&core.cells);
    let core_with_new = core.clone().with_cells(new_cells.clone());

    if core.status_visible {
        Program::display_message(
            status_message(&new_cells),
            Program::show_cells(new_cells, Program::ret(core_with_new)),
        )
    } else {
        Program::show_cells(new_cells, Program::ret(core_with_new))
    }
}
```

**Note:** Core operations access private fields directly since they're in the same crate as Core. No observer methods are exported to the shell.

#### switch_status_visibility_program (UNCHANGED)

Same as v1 implementation — no changes needed:

```rust
pub fn switch_status_visibility_program(core: &Core) -> Program {
    if core.status_visible {
        Program::hide_message(Program::ret(core.clone().with_status_visible(false)))
    } else {
        Program::display_message(
            status_message(&core.cells),
            Program::ret(core.clone().with_status_visible(true)),
        )
    }
}
```

### 3.6 Program Type (UNCHANGED)

The `Program` enum and its implementations are **completely unchanged** from v1. This is a critical point for spec compliance (§13.1).

No new opcodes are added — `define_current_pattern_program` only uses the existing `Return` instruction.

```rust
pub enum Program {
    Return(Core),
    ShowCells(Cells, Box<Program>),
    DisplayMessage(String, Box<Program>),
    HideMessage(Box<Program>),
    GetPatternCells(String, Box<dyn FnOnce(Cells) -> Program + Send + Sync>),
}
```

All helper methods (`ret`, `show_cells`, `display_message`, `hide_message`, `get_pattern_cells`) remain unchanged.

---

## 4. Public API

Following the Functional Core / Imperative Shell principle, the core exports **only what the shell needs** to:
1. Hold the core state (`Core`)
2. Construct arguments for core operations (`Position`, `Breed`, `Cell`, `Cells`)
3. Call core operations to produce `Program` values
4. Interpret `Program` values (`Program` type and its variants)

The shell must **NOT** directly call pure functions like `neighbors`, `should_live`, `next_generation_cells`, `cross_breed`, `status_message`, or `translate` — these are internal to the core.

**Minimal public API from `lib.rs`:**

```rust
// Domain types needed for shell to construct arguments and handle instructions
pub use position::Position;
pub use breed::Breed;
pub use cell::Cell;
pub use cells::Cells;

// Core state type (opaque to shell)
pub use core::Core;

// Program type and core operations (shell calls these, interprets the result)
pub use program::{
    Program,
    next_generation_program,
    add_pattern_program,
    switch_status_visibility_program,
    define_current_pattern_program,  // NEW for v2
};
```

**What's NOT exported (internal to core):**
- `life::{neighbors, should_live, next_generation_cells, cross_breed}` — shell never calls these
- `pattern::{status_message, translate}` — shell never calls these
- `bump` function and `NeighborCounts` type — internal implementation details
- Field update methods (`with_cells`, `with_status_visible`, `with_current_pattern`) — internal to core
- Observer methods (`cells()`, `status_visible()`, `current_pattern()`) — internal to core

**Shell's relationship with these types:**
- **`Position`**: Shell constructs from user input (mouse clicks) to pass as reference in v1, or to build `Cell` in v2
- **`Breed`**: Shell constructs from UI breed selector to build reference `Cell` in v2
- **`Cell`**: Shell constructs from `Position` + `Breed` to pass as reference in v2's `add_pattern_program`
- **`Cells`**: Shell receives in `ShowCells` instruction (for rendering) and returns in `GetPatternCells` response (from PatternStore)
- **`Core`**: Shell holds as mutable state, passes to core operations, receives new `Core` from `Return` instruction
- **`Program`**: Shell interprets by pattern matching on variants
- **Core operations**: Shell calls to produce `Program` values

---

## 5. Testing Framework

### 5.1 Test Organization

All core tests are **unit tests within the core crate** (in `src/tests.rs`) to access private fields. This is the same approach as v1.

**Structure:**
- `src/tests.rs`: Module containing ALL core tests (v1 tests + v2-specific tests)

### 5.2 Test Coverage

#### v1 Tests (Still Must Pass)

All tests from `rust/classic/core/src/tests.rs` must continue to pass with v2 core:

**Group C:** C1
**Group N:** N1-N3, N5
**Group G:** G1-G7, G9-G11
**Group P:** P1-P2, P4
**Group X:** X1a, X2-X3, X4
**Group I:** I1-I4, I8-I9

#### New v2 Tests

**Group C — Cell algebra:**
- C2: Structural equality — equal components => equal cells/hash; different breed => different cell
- C3: positionOf projection — `cell1.position() == cell2.position()` when same position, different breeds
- C4: cross_breed — `(0,0)→0`, `(8,9)→1`, `(15,15)→14`; commutative; folding [3,5,9,12] in any order gives same result

**Group N — Neighbors and counting:**
- N4: bump — bumping an existing entry increments count and cross-breeds; bumping a fresh position creates (1, breed); immutability verified

**Group G — Life rules:**
- G8: newborn breeds — verify that newborn cells get the sum-mod-16 of all contributing neighbors' breeds

**Group P — Patterns:**
- P3: translate with breed — verify cross-breeding in translation
- P4: translate is a bijection on shapes (preserves cardinality) — **already in v1, but must still pass**
- P8: breed round-trip — save/load preserves breed; breeds render with Appendix B colors (shell test, but core must support breed persistence)

**Group I — Programs and protocol:**
- I5: "current" sentinel — verify that `add_pattern_program` with pattern_name="current" resolves to core.current_pattern

**Group X — Cross-cutting:**
- X1b: initialCore for v2 — verify `current_pattern` is empty string
- X2: field updates are non-destructive — extended to include `with_current_pattern`
- X4: startup demo scene — **shell responsibility, but core must support breed in translation**

**Group D — Persistent map:**
- D1: map basics — build, get, keys ascending, values and entries round-trip
- D2: persistence — after any subsequent set, original map unchanged
- D3: absent key — lookup of missing key returns None (absent representation)
- D4: update existing key — overwriting changes value, leaves other keys and original map untouched
- D5: insertion-order independence — keys inserted in different orders yield same key set
- D6: map at scale — 1000 sequential keys all retrievable

**Note on D1-D6:** These test the `im::HashMap` operations, not the core logic directly. We need to verify that our use of `im::HashMap` satisfies these requirements.

### 5.3 Test Implementation Strategy

For tests that require `Cells` (now `im::HashMap<Position, Cell>`), we'll need helper functions:

```rust
// Test helper: create Cells from a list of (x, y, breed) tuples
fn cells_from_tuples(data: &[(i32, i32, u8)]) -> Cells {
    data.iter()
        .map(|&(x, y, b)| (Position::new(x, y), Cell::new(Position::new(x, y), Breed::new(b))))
        .collect()
}

// Test helper: create Cells from a list of (x, y) with breed 0 (for v1-compatible tests)
fn cells_from_coords(coords: &[(i32, i32)]) -> Cells {
    cells_from_tuples(&coords.iter().map(|&(x, y)| (x, y, 0)).collect::<Vec<_>>())
}
```

**Important:** Many v1 tests used `HashSet<Position>`. For v2, we need to adapt these to work with `HashMap<Position, Cell>`. The key insight is that for monochrome (breed=0) patterns, the behavior should be identical to v1.

### 5.4 Running Tests

```bash
cd rust/multibreed/core
cargo test
```

---

## 6. Deviations from Spec and Rust-Specific Choices

### 6.1 Choice: Cells as HashMap instead of Set

**Spec:** §2.2 defines `Cells` as a set with order-insensitive equality

**Rust v2 Implementation:** `im::HashMap<Position, Cell>`

**Rationale:**
- Enforces **at most one cell per position** at the type level
- Provides efficient O(log n) lookup by position
- Needed for neighbor counting strategy (fold over positions and their cells)
- Satisfies order-insensitive equality requirement (HashMap equality in im-rs is based on key-value pairs, not iteration order)
- This is the idiomatic way to represent "set of cells with at most one per position" in Rust

**Spec compliance:** The spec allows any internal ordering provided all equality assertions are order-insensitive and the per-implementation order is stable between equal inputs (G9, I7). `im::HashMap` satisfies this.

### 6.2 Choice: Breed as newtype u8

**Spec:** §2.1 defines Breed as "integer in 0..15"

**Rust Implementation:** `struct Breed(u8)` with smart constructor

**Rationale:**
- Type safety: prevents confusing Breed with other integers
- Smart constructor ensures valid range (0-15)
- Efficient: 1 byte per breed value
- `Copy` trait works naturally
- `Hash` and `Eq` traits derived automatically

### 6.3 Choice: No Memoization for neighbors

**Same as v1:** Direct computation without memoization.

**Rationale:** As documented in v1 plan — faster than cache lookup, maintains purity, idiomatic Rust.

### 6.4 Choice: Program Type Unchanged

**Spec:** §3.2 defines Program structure

**Rust Implementation:** Same flat enum as v1 — no changes

**Rationale:** Demonstrates "shell never changes" principle. `define_current_pattern_program` only uses `Return`, so no new opcodes needed.

### 6.5 Choice: Opaque Core Type with Minimal Observers

**Rust Implementation:** Core struct with private fields, observer methods for fields accessed by core operations

**Rationale:**
- Maintains Functional Core / Imperative Shell boundary
- Shell cannot access Core fields directly
- Core operations (same crate) can access private fields directly OR use observer methods
- Minimal observer methods only for fields needed by public API

**Difference from v1:** v1 had no observer methods at all (core operations accessed private fields directly). For v2, we need to decide: continue direct field access OR add observer methods. I'll use direct field access for core operations since they're in the same crate.

### 6.6 Choice: im-rs over other persistent map crates

**Decision:** Use `im-rs` crate

**Rationale:**
- Explicitly mentioned in spec §2.5 as idiomatic for Rust
- Well-maintained, actively developed
- Provides all required operations
- Good performance characteristics

**Alternative considered:** `rpds` crate. Also a good choice, but `im-rs` is more widely used and has better documentation.

---

## 7. Evolution from v1 (Verification Checklist)

This section verifies that v2 is a proper evolution of v1 per spec §13.2.

### 7.1 What Changed (per §13.2)

| Item | Spec Requirement | Rust Implementation | Status |
|------|-----------------|---------------------|--------|
| 1 | Cell gains a breed | `Cell` struct with `position` and `breed` fields | ✅ |
| 2 | Cross-breeding | `cross_breed(a, b) = (a+b) mod 16` via bitwise AND | ✅ |
| 2 | positionOf projection | `Cell::position()` method | ✅ |
| 3 | Neighbor counting strategy | persistent-map fold with `(count, breed)` entries | ✅ |
| 4 | Core state | + `current_pattern` field | ✅ |
| 5 | New core operation | `define_current_pattern_program` | ✅ |
| 6 | addPattern | reference is Cell, "current" sentinel | ✅ |
| 7 | Store | Shell responsibility — not in core | ✅ |
| 8 | UI and rendering | Shell responsibility — not in core | ✅ |
| 9 | Tests | Add C2-C4, N4, G8, P3, P4, P8, I5, X1b | ✅ (planned) |

### 7.2 What Did NOT Change (per §13.1)

| Item | Spec Requirement | Rust Implementation | Status |
|------|-----------------|---------------------|--------|
| Instruction set | 5 opcodes unchanged | Program enum unchanged | ✅ |
| Program shape | Step/Rest structure | Flat enum equivalent | ✅ |
| Interpreter | Belongs in shell | NOT in core | ✅ |
| Shell wiring | Belongs in shell | NOT in core | ✅ |
| Life truth table | should_live unchanged | Same function | ✅ |
| Neighbor function | Computes 8 Moore neighbors | Same function | ✅ |
| Status grammar | status_message unchanged | Same function | ✅ |
| Pattern catalog | 19 patterns | Same data (in shell) | ✅ |
| Demo scene | 13 stamps | Same data (in shell) | ✅ |
| Architecture | Pure core, dumb shell, etc. | Same architecture | ✅ |

### 7.3 Implementation Strategy

**Evolution approach:** Copy `rust/classic/core/` to `rust/multibreed/core/` and make minimal edits.

**Files to copy and modify:**
1. `Cargo.toml` — add `im = "15.1"` dependency
2. `src/position.rs` — NO CHANGES
3. `src/cells.rs` — CHANGE: HashSet<Position> → im::HashMap<Position, Cell>
4. `src/core.rs` — MODIFY: add current_pattern field
5. `src/life.rs` — MODIFY: replace next_generation_cells with persistent-map fold
6. `src/pattern.rs` — MODIFY: change translate to accept Cell reference, add cross-breed
7. `src/program.rs` — MODIFY: add define_current_pattern_program, modify add_pattern_program
8. `src/lib.rs` — MODIFY: add new exports
9. `src/tests.rs` — MODIFY: add v2 tests, adapt v1 tests to use new Cells type
10. Add NEW files: `src/breed.rs`, `src/cell.rs`

**Git diff should show:**
- New files: `breed.rs`, `cell.rs`
- Modified files: all others (except position.rs which is unchanged)
- No deleted files

---

## 8. Implementation Checklist (Phase 2)

### After explicit approval, implement in this order:

#### Step 1: Setup
- [ ] Create directory structure: `rust/multibreed/core/`
- [ ] Create parent directories: `rust/multibreed/`
- [ ] Update `rust/Cargo.toml` to include multibreed workspace member
- [ ] Create `rust/multibreed/Cargo.toml` (workspace with core member)

#### Step 2: Copy v1 core
- [ ] Copy `rust/classic/core/` to `rust/multibreed/core/`
- [ ] Rename crate from `gol-classic-core` to `gol-multibreed-core`

#### Step 3: Add v2-specific files
- [ ] Create `src/breed.rs` (Breed type)
- [ ] Create `src/cell.rs` (Cell type)

#### Step 4: Modify existing files
- [ ] Update `Cargo.toml` (add im-rs dependency)
- [ ] Update `src/cells.rs` (change to im::HashMap<Position, Cell>)
- [ ] Update `src/core.rs` (add current_pattern field and methods)
- [ ] Update `src/life.rs` (add cross_breed, bump, change next_generation_cells to persistent-map fold)
- [ ] Update `src/pattern.rs` (change translate to accept Cell reference and cross-breed)
- [ ] Update `src/program.rs` (add define_current_pattern_program, modify add_pattern_program)
- [ ] Update `src/lib.rs` (add new exports)
- [ ] Update `src/tests.rs` (add v2 tests, adapt v1 tests to new Cells type)

#### Step 5: Verify
- [ ] Run `cargo build` — must compile cleanly with no warnings
- [ ] Run `cargo test` — all tests must pass
- [ ] Run `cargo clippy` — no linting warnings
- [ ] Run `cargo fmt` — code formatted

#### Step 6: Document
- [ ] Create/update `README.md` with v2-specific documentation
- [ ] Document all deviations from spec

#### Step 7: Commit
- [ ] Commit with message following repo conventions

---

## 9. Acceptance Criteria

1. **All tests pass**: v1 tests + new v2 tests (C1-C4, N1-N5, G1-G11, P1-P4/P8, I1-I5/I8-I9, X1a/X1b/X2-X4, D1-D6)
2. **Code compiles cleanly**: No warnings with `cargo build` and `cargo test`
3. **Core is pure**: No mutable state, no I/O, no side effects
4. **Correct evolution**: v2 is produced by editing v1 codebase (verified by git diff)
5. **Nothing in §13.1 changed**: Instruction set, interpreter, Life rules, neighbor function, status grammar all unchanged
6. **Spec compliance**: All normative requirements from `specs.md` satisfied
7. **Documentation**: README documents all deviations and design decisions
8. **Package properly configured**: Library crate with correct dependencies
9. **All tests run with `cargo test`**: No test doubles, pure core testing
10. **Compliance with §13.2**: All items in the evolution checklist are implemented

---

## 10. Open Questions (to be resolved before Phase 2)

**None.** All prerequisite decisions have been made:

- Build tooling: Cargo (decided in repo README)
- External dependencies: `im = "15.1"` for persistent map
- Breed representation: `struct Breed(u8)` with smart constructor
- Cell representation: `struct Cell { position: Position, breed: Breed }`
- Cells representation: `im::HashMap<Position, Cell>`
- Neighbor counting: persistent-map fold strategy
- Program type: unchanged from v1
- Test framework: built-in `cargo test` with `#[test]`
- Implementation strategy: copy and edit v1 core

---

## 11. Summary

This plan describes the implementation of the Rust v2 (multi-breed) functional core as a **direct evolution** of the existing v1 core. The key changes are:

1. Add Breed and Cell types
2. Change Cells from HashSet<Position> to im::HashMap<Position, Cell>
3. Add current_pattern field to Core
4. Add cross_breed function and bump function
5. Change next_generation_cells to persistent-map fold strategy
6. Change translate to accept Cell reference and perform cross-breed
7. Add define_current_pattern_program
8. Modify add_pattern_program for Cell reference and "current" sentinel
9. Add v2-specific tests

All changes are **additive or modifying** — nothing from v1 is deleted or rewritten. The instruction set, interpreter, Life rules, neighbor function, and status grammar all remain unchanged, satisfying spec §13.1.

**The plan is ready for review. Please respond with "Plan approved", "Go ahead", or "Implement phase 2" before I proceed.**

---

*Last updated: 2026-10-17*
