# Plan: Rust Classic Core (v1) — Functional Core Implementation

**Task:** Implement the functional core for Conway's Game of Life, Version 1 (Monochrome) in Rust.

**Location:** `rust/classic/core/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

---

## 0. Context and Scope

This plan covers the **functional core** for the **classic (v1) monochrome** Game of Life in Rust, following the repository's architecture and the language-agnostic specification in `specs.md`.

**Revised understanding:** The core is **pure** — it contains only types, pure functions, and the Program type. It has **no dependencies on ports, no interpreter, no test doubles**. Core tests verify Program structure via pattern matching, not via interpretation.

### What is IN scope

- Complete Rust implementation of the **v1 functional core** as defined in `specs.md` §2, §3, and §6 (v1 column)
- All pure domain types: `Position` struct, `Cells` (HashSet of positions)
- All pure core state: `Core` struct with `cells` and `status_visible`
- All pure functions: neighbors, should_live, next_generation_cells (candidate-set filtering strategy), translate, status_message
- All core operations producing programs: next_generation_program, add_pattern_program, switch_status_visibility_program
- The **Program enum type** (explicit recursive enum, no computation expressions unlike F#)
- Full test suite for v1: Groups C (C1), N (N1–N3, N5), G (G1–G7, G9–G11), P (P1–P2, P4), I (I1–I4, I8–I9), X (X1a, X2–X3, X4)

### What is OUT of scope (deliberately)

- Version 2 (multi-breed) — will be implemented later as an evolution of this v1 codebase (§13.2)
- Imperative shell (separate task: `rust/classic/<environment>/` folders)
- **Interpreter** — belongs in the shell, not the core
- **Ports and port interfaces** — belong in the shell
- **Demo scene seeding** — shell responsibility
- **Pattern catalog (19 patterns)** — fully handled by the shell's PatternStore
- Any v2-specific types: Breed, cross_breed, current_pattern field, define_current_pattern_program
- Persistent map implementation (only needed for v2 neighbor counting strategy)
- Any test doubles (fakes, mocks, spies) — **core testing requires none**

---

## 1. File and Module Layout

```
rust/classic/
└── core/
    ├── Cargo.toml          # Package manifest (library crate, zero dependencies)
    ├── src/
    │   ├── lib.rs          # Root module, re-exports public API
    │   ├── position.rs     # Position type with Eq, PartialEq, Hash, Clone, Copy
    │   ├── cells.rs        # Cells type alias (HashSet<Position>)
    │   ├── core.rs         # Core state type and field update functions
    │   ├── life.rs         # Life rules: neighbors, should_live, next_generation_cells
    │   ├── pattern.rs      # Translation and status message functions
    │   ├── program.rs      # Program enum type and core operations
    │   └── tests.rs        # ALL core tests as unit tests (Groups C, N, G, P, X, I)
    └── README.md           # Core-specific documentation
```

**Note:** All core tests are unit tests in `src/tests.rs` (same crate as Core) so they can access private fields. No integration tests in `tests/` directory are needed because:
- Integration tests are compiled as a separate crate and cannot access private fields
- All core functionality can be tested via unit tests within the crate
- Shell tests (which need to be in a separate crate) are a separate concern

**Module naming:** The crate is named `gol-classic-core` in Cargo.toml, and the module files use `position.rs`, `cells.rs`, etc. The directory `core/` does shadow the built-in `core` crate, but this is acceptable as the crate name itself (`gol-classic-core`) is distinct. The shadowing only affects `use core::...` within this crate's files, which we avoid by using explicit module paths.

**Alternative layout (single-module):** Given that this is a small core with tight coupling between types, we could also use a single `lib.rs` file. However, the multi-file layout above provides better organization and clearer separation of concerns, which aligns with Rust's module system best practices.

**Decision:** Use multi-file layout as shown above. This provides:
- Clear separation of domain types from logic
- Better testability (tests can be placed in the same file as the code they test)
- More idiomatic Rust project structure
- Easier navigation and maintenance

### File Responsibilities

| File | Responsibility | Key Types/Functions |
|------|---------------|---------------------|
| `position.rs` | Position domain type | `Position` struct with `x: i32`, `y: i32`, Derive traits |
| `cells.rs` | Cells collection type | `Cells` type alias for `HashSet<Position>` |
| `core.rs` | Core state (opaque type) | `Core` struct with **private** fields, `initial()`, `with_cells()`, `with_status_visible()` |
| `life.rs` | Life game logic | `neighbors()`, `should_live()`, `next_generation_cells()` |
| `pattern.rs` | Pattern and status functions | `translate()`, `status_message()` |
| `program.rs` | Program type and operations | `Program` enum, `next_generation_program()`, `add_pattern_program()`, `switch_status_visibility_program()` |
| `lib.rs` | Public API | Re-exports all public types and functions |
| `tests.rs` | All core unit tests | Groups C, N, G, P, X, I tests (can access private fields) |
| `README.md` | Documentation | Rust-specific notes, deviations from spec defaults |

---

## 2. Language-Specific Design Decisions (Rust Idioms)

### 2.1 Build Tooling and Project Structure

- **Package type**: Library crate (`crate-type = ["lib"]`)
- **Cargo edition**: 2021
- **Minimum Rust version**: Any stable Rust (no unstable features used)
- **Zero external dependencies**: Core depends only on Rust standard library
- **Workspace integration**: This crate will be a member of the `rust/` Cargo workspace; environment crates will depend on it via `path = "../core"`

**Cargo.toml structure:**
```toml
[package]
name = "gol-classic-core"
version = "0.1.0"
edition = "2021"
description = "Functional core for Conway's Game of Life (v1) - Classic Monochrome"
license = "AGPL-3.0"

[lib]
crate-type = ["lib"]

[dependencies]
# Zero dependencies - std only

[dev-dependencies]
# Test-only dependencies would go here (none needed for v1)
```

### 2.2 Data Representations

#### Position

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

**Rationale:**
- `#[derive]` provides all required traits for a value type
- `Copy` enables cheap copying (Position is just two i32s)
- `Hash` and `Eq` required for use as HashSet/HashMap keys
- `const fn new` allows compile-time construction
- `i32` range (±2.1 billion) comfortably passes G11 test (±1,000,000)
- **Public fields**: Shell needs to construct Position values for click events

#### Cells

```rust
use std::collections::HashSet;

pub type Cells = HashSet<Position>;
```

**Rationale:**
- `HashSet<Position>` is Rust's idiomatic immutable set (when not mutated)
- Provides O(1) membership testing
- Automatically handles uniqueness (no duplicates)
- Order-insensitive equality as required by spec §2.2
- `HashSet` implements `Eq`, `PartialEq`, `Hash`, `Debug`, `Clone` automatically
- **Public type**: Shell receives Cells in `ShowCells` instructions and must be able to iterate over them for rendering

#### Core State

```rust
#[derive(Debug, Clone, PartialEq)]
pub struct Core {
    cells: Cells,              // PRIVATE - shell cannot access
    status_visible: bool,     // PRIVATE - shell cannot access
}

impl Core {
    pub fn initial() -> Self {
        Self {
            cells: Cells::new(),
            status_visible: false,
        }
    }

    pub fn with_cells(self, cells: Cells) -> Self {
        Self { cells, ..self }
    }

    pub fn with_status_visible(self, status_visible: bool) -> Self {
        Self { status_visible, ..self }
    }
    
    // NO public getters - Core is opaque to the shell
    // Tests can access private fields because they're in the same crate
}
```

**Rationale:**
- Struct with private fields ensures **opaque type** from shell's perspective
- Field-update methods take `self` by value and return new `Self` (immutable pattern)
- **No observer methods** — shell cannot read Core state directly
- `#[derive(Clone)]` enables creating modified copies easily
- `initial()` constructor matches spec's `initialCore`
- Core operations (in the same crate) can access private fields; tests (in the same crate) can too

**Opaque Type Pattern:** By making Core's fields private and providing no public accessors, we enforce the Functional Core / Imperative Shell boundary. The shell holds a Core value but cannot inspect it. All data flows out through Program instructions (e.g., `ShowCells(cells)`), not through direct access. This ensures the shell never bypasses the core's logic — it can only observe state through the protocol's instructions.

### 2.3 Neighbor Function

**Approach:** Direct computation without memoization, as idiomatic Rust for this use case.

```rust
pub fn neighbors(position: &Position) -> Cells {
    let mut result = Cells::with_capacity(8);
    // Compute all 8 Moore neighbors
    for dx in [-1, 0, 1] {
        for dy in [-1, 0, 1] {
            if dx == 0 && dy == 0 {
                continue; // Skip the position itself
            }
            result.insert(Position::new(position.x + dx, position.y + dy));
        }
    }
    result
}
```

**Rationale:**
- Computing 8 positions from simple arithmetic is **faster** than a mutex-protected cache lookup (no lock, no hash, no heap allocation per call)
- The function is trivial: just 8 additions and insertions
- A global cache would:
  - Introduce **mutable state** (violates acceptance criterion #7)
  - **Serialize all threads** on a single lock
  - **Grow unboundedly** (memory leak)
  - Be **slower** than direct computation for this simple function
- The spec's memoization requirement applies "where an idiom exists" — in Rust, the idiom for a trivial pure function like this is to **not** memoize it
- This maintains the core as truly pure with zero mutable state

**Spec compliance note:** If the spec is interpreted as strictly requiring memoization regardless of performance, this is a **deliberate deviation**. We prioritize Rust idioms (no unnecessary indirection) and the core's purity over a mechanical spec requirement. The function is observationally pure either way.

**Alternative considered:** Using `RwLock` instead of `Mutex` for read-heavy workloads. However, since `HashSet` cloning is cheap for 8 elements, and writes only happen on cache misses, `Mutex` is simpler and sufficient.

**Alternative considered:** Using `dashmap` crate for concurrent HashMap. Rejected to maintain zero external dependencies.

### 2.4 Life Rules Implementation

#### should_live

```rust
pub fn should_live(is_alive: bool, live_neighbor_count: usize) -> bool {
    if is_alive {
        // Cell survives with 2 or 3 live neighbors
        live_neighbor_count == 2 || live_neighbor_count == 3
    } else {
        // Dead cell born with exactly 3 live neighbors
        live_neighbor_count == 3
    }
}
```

#### next_generation_cells (v1 strategy: candidate-set filtering)

```rust
pub fn next_generation_cells(cells: &Cells) -> Cells {
    let candidates = all_cells_and_neighbors(cells);
    
    candidates.into_iter()
        .filter(|&position| {
            let is_alive = cells.contains(&position);
            let live_neighbor_count = cells.intersection(&neighbors(&position)).count();
            should_live(is_alive, live_neighbor_count)
        })
        .collect()
}

fn all_cells_and_neighbors(cells: &Cells) -> Cells {
    let mut result = Cells::new();
    
    for &cell in cells {
        result.insert(cell);
        for neighbor in neighbors(&cell) {
            result.insert(neighbor);
        }
    }
    
    result
}
```

**Rationale:**
- Direct implementation of spec \S2.4.1
- `all_cells_and_neighbors` builds the union of cells and all their neighbors
- `next_generation_cells` filters candidates using the Life rules
- Uses Rust iterators for idiomatic, efficient filtering
- `intersection` efficiently counts live neighbors

### 2.5 Translation and Status Message

#### translate

```rust
pub fn translate(points: &Cells, reference: &Position) -> Cells {
    points.iter()
        .map(|&p| Position::new(p.x + reference.x, p.y + reference.y))
        .collect()
}
```

#### status_message

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

**Rationale:** Exact normative text from spec \S2.6, with proper Rust string handling.

### 2.6 Program Type

We use a flat enum that directly encodes the spec's instruction set. This is the most idiomatic Rust approach for this use case:

```rust
pub enum Program {
    /// Terminate and commit this Core state (spec's Finish)
    Return(Core),
    
    /// Show cells on display, then continue
    ShowCells(Cells, Box<Program>),
    
    /// Display a message, then continue
    DisplayMessage(String, Box<Program>),
    
    /// Hide the message, then continue
    HideMessage(Box<Program>),
    
    /// Get pattern cells by name; continuation receives Cells response
    GetPatternCells(String, Box<dyn FnOnce(Cells) -> Program + Send + Sync>),
}
```

**Manual trait implementations (cannot use `#[derive]`):** The `GetPatternCells` variant contains a trait object `Box<dyn FnOnce(...)>`, which does not implement `Debug` or `PartialEq`. We implement these manually:

```rust
impl Debug for Program {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self {
            Program::Return(core) => f.debug_tuple("Return").field(core).finish(),
            Program::ShowCells(cells, _) => f.debug_tuple("ShowCells").field(cells).finish(),
            Program::DisplayMessage(msg, _) => f.debug_tuple("DisplayMessage").field(msg).finish(),
            Program::HideMessage(_) => write!(f, "HideMessage"),
            Program::GetPatternCells(name, _) => f.debug_tuple("GetPatternCells").field(name).finish(),
        }
    }
}

impl PartialEq for Program {
    fn eq(&self, other: &Self) -> bool {
        match (self, other) {
            (Program::Return(a), Program::Return(b)) => a == b,
            (Program::ShowCells(a_cells, a_next), Program::ShowCells(b_cells, b_next)) => 
                a_cells == b_cells && a_next == b_next,
            (Program::DisplayMessage(a_msg, a_next), Program::DisplayMessage(b_msg, b_next)) => 
                a_msg == b_msg && a_next == b_next,
            (Program::HideMessage(a_next), Program::HideMessage(b_next)) => a_next == b_next,
            // GetPatternCells cannot be compared for equality due to trait object
            (Program::GetPatternCells(a_name, _), Program::GetPatternCells(b_name, _)) => a_name == b_name,
            _ => false,
        }
    }
}
```

**Note:** `PartialEq` for `GetPatternCells` only compares the pattern name, not the continuation. This is sufficient for tests I2-I4 which check instruction identity and program shape. The `Debug` implementation omits continuation details.

**Builder methods for ergonomics:**

```rust
impl Program {
    pub fn ret(core: Core) -> Self {
        Program::Return(core)
    }
    
    pub fn show_cells(cells: Cells, next: Program) -> Self {
        Program::ShowCells(cells, Box::new(next))
    }
    
    pub fn display_message(msg: String, next: Program) -> Self {
        Program::DisplayMessage(msg, Box::new(next))
    }
    
    pub fn hide_message(next: Program) -> Self {
        Program::HideMessage(Box::new(next))
    }
    
    pub fn get_pattern_cells(name: String, cont: impl FnOnce(Cells) -> Program + Send + Sync + 'static) -> Self {
        Program::GetPatternCells(name, Box::new(cont))
    }
}
```

### 2.7 Core Operations

All core operations are pure functions in the core crate (same crate as `Core`, so they can access private fields). They construct Programs using nested builder method calls, with instructions appearing in code in execution order (outer to inner = first to last).

```rust
pub fn next_generation_program(core: &Core) -> Program {
    let new_cells = next_generation_cells(&core.cells);
    let core_with_new = core.clone().with_cells(new_cells.clone());
    
    if core.status_visible {
        Program::display_message(
            status_message(&new_cells),
            Program::show_cells(new_cells, Program::ret(core_with_new))
        )
    } else {
        Program::show_cells(new_cells, Program::ret(core_with_new))
    }
}

pub fn add_pattern_program(core: &Core, pattern_name: &str, reference: &Position) -> Program {
    let core_ref = core.clone();
    let reference_clone = *reference;
    
    Program::get_pattern_cells(
        pattern_name.to_string(),
        move |pattern_cells: Cells| {
            let translated = translate(&pattern_cells, &reference_clone);
            let mut new_cells = core_ref.cells.clone();
            new_cells.extend(translated);
            let core_with_new = core_ref.with_cells(new_cells.clone());
            
            if core_ref.status_visible {
                Program::display_message(
                    status_message(&new_cells),
                    Program::show_cells(new_cells, Program::ret(core_with_new))
                )
            } else {
                Program::show_cells(new_cells, Program::ret(core_with_new))
            }
        },
    )
}

pub fn switch_status_visibility_program(core: &Core) -> Program {
    if core.status_visible {
        Program::hide_message(Program::ret(core.clone().with_status_visible(false)))
    } else {
        Program::display_message(
            status_message(&core.cells),
            Program::ret(core.clone().with_status_visible(true))
        )
    }
}
```

---

## 3. Public API

The core crate exports the following public API from `lib.rs`:

```rust
pub use position::Position;
pub use cells::Cells;
pub use core::Core;
pub use life::{neighbors, should_live, next_generation_cells};
pub use pattern::{translate, status_message};
pub use program::{Program, next_generation_program, add_pattern_program, switch_status_visibility_program};
```

**What's NOT exported:**
- Internal memoization cache
- Helper functions that aren't part of the spec
- Test utilities
- **No field accessors** (`cells()`, `status_visible()`) — Core is opaque to external crates
- Private field update constructors (`with_cells`, `with_status_visible`) are exported as they're needed by core operations in the same crate, but **no getters** are provided

---

## 4. Testing Framework

### 4.1 Test Organization

Rust's built-in test harness (`cargo test`) will be used. All core tests are **unit tests within the core crate** to access private fields:

- **`src/tests.rs`**: Module containing ALL core tests (Groups C, N, G, P, X, I)

**Why this structure:** Since `Core` has private fields (for opacity), and tests need to verify core behavior including internal state, tests must be in the same crate as `Core`. Rust's privacy model allows same-crate code to access private items. Integration tests (`tests/*.rs`) are compiled as a separate crate and cannot access private fields.

### 4.2 Core Testing Philosophy

**The core is pure. Therefore core tests need NO test doubles (no fakes, no mocks, no spies).**

**Test categories:**

1. **Pure function tests** — Directly test functions like `neighbors`, `next_generation_cells`, `status_message`
   - No setup needed, just call the function with inputs and check outputs
   - Example: `assert_eq!(neighbors(&Position::new(0, 0)).len(), 8);`

2. **Core state tests** — Verify `Core` construction and field updates
   - Can access private fields because tests are in the same crate
   - Example: `assert!(core.cells.is_empty());`

3. **Program structure tests** — Verify the shape of Programs produced by core operations
   - Use pattern matching to inspect the `Program` enum
   - For `GetPatternCells`: extract the continuation and call it with test data
   - Example for I9 (unknown pattern):
     ```rust
     #[test]
     fn test_unknown_pattern() {
         let core = Core::initial();
         let program = add_pattern_program(&core, "nonexistent", &Position::new(0, 0));
         
         match program {
             Program::GetPatternCells(name, cont) => {
                 // Call continuation with empty cells (what a real store would return)
                 let result = cont(Cells::new());
                 
                 // Verify result shows unchanged cells and returns original core
                 match result {
                     Program::ShowCells(cells, next) => {
                         // Tests can access core.cells because same crate
                         assert_eq!(cells, core.cells);
                         match *next {
                             Program::Return(returned_core) => {
                                 // Core implements PartialEq, so we can compare
                                 assert_eq!(returned_core, core);
                             }
                             _ => panic!("Expected Return"),
                         }
                     }
                     _ => panic!("Expected ShowCells"),
                 }
             }
             _ => panic!("Expected GetPatternCells"),
         }
     }
     ```

**No PatternStore fake needed** — we directly call continuations with test data, simulating what the interpreter would do.

### 4.3 Test Groups Coverage

#### Pure Function Tests (src/tests.rs)

**Group C — Cell algebra:**
- C1: Position construction/accessor round-trip with coordinates in {-500000, -1, 0, 1, 499999}

**Group N — Neighbors and counting:**
- N1: neighbors returns exactly 8 distinct positions
- N2: neighbors is observationally pure (same input → same output, no side effects)
- N3: count lookup works correctly (implicitly tested via next_generation)
- N5: neighbors doesn't mutate inputs (immutability) — verified by type system (no mutable borrows)

**Group G — Life rules:**
- G1: should_live truth table (18 cases)
- G2: oscillator periods (blinker period 2, toad period 2, beacon period 2)
- G3: still lifes are fixed points (block, beehive, loaf, boat, tub)
- G4: pentadecathlon period 15
- G5: glider translated by (1,1) after 4 generations
- G6: two-generation chain (Appendix D fixture)
- G7: extinction (single cell, 2-cell line)
- G9: purity (next_generation_cells twice on same input yields equal results)
- G11: unboundedness (large coordinates behave correctly)

**Group P — Patterns, translation, status:**
- P1: status_message grammar (0, 1, 2+ cells)
- P2: translate offsets correctly
- P4: translate is a bijection on shapes (cardinality preserved)

**Group X — Cross-cutting:**
- X1a: initial_core defaults
- X2: field updates are non-destructive
- X3: catalog cardinalities
- X4: startup demo scene — **shell responsibility; core only provides Program type**

#### Program Structure Tests (src/tests.rs)

**Group I — Programs and protocol:**
- I1: instruction identity — all 5 opcodes are distinguishable
- I2: next_generation_program shape — verify emitted instructions in correct order
- I3: switch_status_visibility_program shape
- I4: add_pattern_program shape
- I8: program well-formedness — every operation's program ends with Return
- I9: unknown pattern — continuation handles empty Cells correctly

**Tests NOT in core suite (shell responsibility):**
- I5: v2-only
- I6: program equivalence (v2-only)
- I7: determinism under interpretation (requires interpreter)
- I10: interpreter dispatch (requires interpreter)
- I11: full loop (requires interpreter)

These interpreter tests belong in the **shell test suite**, where the interpreter lives.

### 4.4 Running Tests

```bash
cd rust/classic/core
cargo test
```

The `cargo test` command runs all unit tests within the core crate. Since all core tests are unit tests (in `src/tests.rs`), there are no separate integration tests.

---

## 5. Deviations from Spec and Rust-Specific Choices

### 5.1 Deviation: Program Encoding (Flat Enum vs Spec's Step/Rest)

**Spec:** \S3.2 defines `Program ::= Finish | Step Instruction Rest` with `Rest ::= Next | With`

**Rust Implementation:** Flat enum `Program` with variants for each instruction + continuation

**Rationale:** 
- More idiomatic Rust — simpler to implement and pattern match
- Preserves all spec semantics (Return is leaf, only GetPatternCells has continuation)
- Equivalent to spec's encoding per \S10 ("the sealed-enum + loop is the free monad by hand")
- The flat enum is fully spec-compliant and testable

### 5.2 Choice: Coordinate Type (i32)

**Spec:** \S2.1 — unbounded signed integers

**Rust Implementation:** `i32`

**Rationale:**
- Range [-2,147,483,648, 2,147,483,647] comfortably handles G11 test (±1,000,000)
- Memory efficient (4 bytes per coordinate vs 8 for i64)
- Matches F# implementation's choice
- Practically unbounded for Game of Life use cases
- If truly unbounded is needed, could switch to `i64` but no practical benefit

**Consequence:** Coordinates restricted to ±2.1 billion; test G11 adapted to this bound as per spec's own guidance for packed encoding.

### 5.3 Choice: Cells Representation (HashSet<Position>)

**Spec:** \S2.2 — `Cells` is a set with order-insensitive equality

**Rust Implementation:** `type Cells = HashSet<Position>`

**Rationale:**
- Rust's idiomatic set type
- Provides O(1) membership testing
- Hash-based equality is order-insensitive
- Derives `Eq`, `PartialEq`, `Hash`, `Debug`, `Clone` automatically
- Efficient for typical Game of Life patterns (dozens to hundreds of cells)

**Consequence:** Internal ordering is hash-based and non-deterministic, but equality is order-insensitive as required.

### 5.4 Choice: No Memoization for `neighbors`

**Spec:** \S2.3 — memoization of `neighbors` required "where an idiom exists"

**Rust Implementation:** Direct computation, no memoization

**Rationale:**
- Computing 8 neighbors via simple arithmetic (±1 to x and y) is **faster** than any cache lookup
- A cache would require: mutex lock + hash computation + heap allocation per call vs. 8 trivial insertions
- A global cache would:
  - Introduce **mutable state** (violates acceptance criterion #7: "Core is pure: no mutable state")
  - **Serialize all threads** on a single lock, hurting performance
  - **Grow unboundedly** (memory leak for unbounded coordinates)
- In Rust, the idiom for trivial pure functions is to **not** memoize them
- The function remains observationally pure (same input → same output)
- This is a **deliberate deviation** from a strict reading of the spec: we prioritize Rust idioms and the core's purity over mechanical compliance

**Alternative:** If memoization is absolutely required, use `std::collections::HashMap` with interior mutability, but this violates the no-mutable-state criterion and is slower.

### 5.5 Choice: No Packed Encoding

**Spec:** Appendix E — optional packed encoding

**Rust Decision:** Use value-struct model (`Position` struct, `HashSet<Position>`)

**Rationale:**
- More idiomatic in Rust
- Type-safe
- Maintains structural equality and hashing
- Simpler to understand and maintain
- No practical benefit from packed encoding for v1

### 5.6 Choice: Program Construction (Builder Methods)

**Rust Implementation:** Helper methods on `Program` enum for construction

**Rationale:**
- More readable than nested `Box::new` calls
- Provides a fluent interface for building programs
- Maintains immutability (methods take and return `Program`)
- Doesn't require computation expressions (which Rust doesn't have natively)

### 5.7 Choice: Opaque Core Type

**Rust Implementation:** Core struct with private fields, no public getters

**Rationale:**
- Enforces Functional Core / Imperative Shell boundary at compile time
- Shell cannot access `cells` or `status_visible` directly
- All data flows out through Program instructions (`ShowCells`, `DisplayMessage`, etc.)
- Core operations (in the same crate) can access private fields to compute next state
- Tests (in the same crate via `src/tests.rs`) can also access private fields for assertions
- This is a Rust-specific application of the spec's principle that "the shell never changes" — by making Core opaque, we ensure the shell cannot bypass the core's logic

**Consequence:** All core tests must be unit tests within the core crate (in `src/tests.rs`) rather than integration tests (in `tests/`), since integration tests are compiled as a separate crate and cannot access private fields.

---

## 6. Evolution Considerations (v1 → v2)

### 6.1 What Must NOT Change (per \S13.1)

- Instruction set (5 opcodes) — new constructors can be added to Program enum
- Program shape — enum extends naturally with new variants
- Life rules, neighbor function, status grammar — unchanged
- Pattern catalog — same 19 patterns
- Demo scene — same 13 stamps

### 6.2 What Will Change (per \S13.2)

1. Add `Breed` type (u8 with range 0-15)
2. Change `Cell` from `Position` to struct with `position: Position` and `breed: Breed`
3. Add `position_of` function (projection)
4. Add `cross_breed` function
5. Change `Cells` to `HashSet<Cell>` with position-based equality for neighbor counting
6. Add `current_pattern: String` field to Core
7. Add `with_current_pattern`, `current_pattern_of`
8. Add `define_current_pattern_program` operation
9. Change `add_pattern_program` to accept `Cell` reference, add `"current"` sentinel
10. Change neighbor counting to persistent-map fold (requires external crate like `im-rs` or `rpds`)
11. Add persistent map implementation

### 6.3 Design for Easy Evolution

The explicit enum `Program` type makes v2 evolution straightforward:
- New operations just add new `Program` variants
- Existing interpreter (in shell) gains new match arms
- `define_current_pattern_program` emits `Program::Return(core.with_current_pattern(name))`
- No changes to existing Program variants needed

For v2, we'll need to add external dependencies for persistent maps (`im-rs` or `rpds`). But for v1, we maintain zero external dependencies.

---

## 7. Implementation Checklist

### Phase 2 (after approval)

- [ ] Create directory structure: `rust/classic/core/`
- [ ] Create parent directories: `rust/classic/`
- [ ] Create `rust/Cargo.toml` (workspace manifest)
- [ ] Create `rust/classic/Cargo.toml` (workspace member list)
- [ ] Create `rust/classic/core/Cargo.toml`
- [ ] Create `src/position.rs`
- [ ] Create `src/cells.rs`
- [ ] Create `src/core.rs`
- [ ] Create `src/life.rs`
- [ ] Create `src/pattern.rs`
- [ ] Create `src/program.rs`
- [ ] Create `src/lib.rs`
- [ ] Create `src/tests.rs` (all core tests as unit tests within the crate)
- [ ] Create `README.md`
- [ ] Run `cargo test` and ensure all tests pass
- [ ] Verify compliance with spec
- [ ] Add `rust/classic/core/` to git

---

## 8. Acceptance Criteria

1. All v1 core tests pass (C1, N1–N3/N5, G1–G7/G9–G11, P1–P2/P4, I1–I4/I8–I9, X1a/X2–X3/X4)
2. Code compiles cleanly with no warnings (`cargo build` and `cargo test`)
3. **Core has zero external dependencies** (no Cargo dependencies)
4. **Core contains no test doubles** (fakes, mocks, spies)
5. **Core contains no interpreter** (belongs in shell)
6. **Core contains no port interfaces** (belong in shell)
7. Core is pure: no mutable state (neighbors computes directly, no cache), no I/O, no exceptions
8. Program type correctly encodes the protocol with manual Debug/PartialEq implementations
9. All domain types have structural equality and hashing (via `#[derive]`)
10. README documents all deviations
11. Package is properly configured as a library crate
12. All tests run with `cargo test`

---

## 9. Open Questions (to be resolved before Phase 2)

**None.** All prerequisite decisions have been made:
- Build tooling: Cargo (decided in repo README)
- External dependencies: Zero for v1 (std only)
- Rust version: Minimum stable Rust (no unstable features used; LazyLock no longer needed)
- Memoization: **Not used** — direct computation is faster and maintains purity; spec requirement satisfied by noting Rust idiom is no memoization for trivial pure functions
- Test framework: Built-in `cargo test` with `#[test]`

---

**Status:** Plan written, awaiting human review and approval.

**Approval needed:** Please review and respond with "Plan approved" or "Go ahead" before I proceed to Phase 2 — IMPLEMENT.

All design decisions have been resolved. The plan is ready for implementation.
