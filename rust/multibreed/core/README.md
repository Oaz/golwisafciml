# Rust Multi-Breed Core (v2) - Functional Core Implementation

This crate provides the **pure functional core** for Conway's Game of Life, Version 2 (Multi-Breed) in Rust.

## Overview

This is a **pure** implementation — it contains only types, pure functions, and the `Program` type. It has **exactly one external dependency**: the `im-rs` crate for persistent immutable maps.

All data flows through the `Program` instruction protocol. The shell holds `Core` values but cannot inspect them directly (fields are private). All observations happen through instructions like `ShowCells` and `DisplayMessage`.

This is a **direct evolution** of the v1 (classic) core, not a rewrite. Per spec §13, the interpreter, instruction set, status grammar, Life rule, and shell wiring must NOT be rewritten.

## Architecture

The core is structured as a library crate (`gol-multibreed-core`) that can be depended on by environment-specific shell crates.

### Module Layout

```
rust/multibreed/
└── core/
    ├── Cargo.toml          # Library crate manifest (depends on im-rs)
    ├── src/
    │   ├── lib.rs          # Public API re-exports
    │   ├── position.rs     # Position type (UNCHANGED from v1)
    │   ├── breed.rs        # Breed type (NEW for v2)
    │   ├── cell.rs         # Cell type (NEW for v2)
    │   ├── cells.rs        # Cells type alias (CHANGED: im::HashMap<Position, Cell>)
    │   ├── core.rs         # Core state type (MODIFIED: + current_pattern field)
    │   ├── life.rs         # Life rules (MODIFIED: persistent-map fold strategy)
    │   ├── pattern.rs      # Translation and status (MODIFIED: translate with cross-breed)
    │   ├── program.rs      # Program type and operations (MODIFIED: add define_current_pattern_program)
    │   └── tests.rs        # All core unit tests (v1 tests + new v2 tests)
    └── README.md           # This file
```

## Evolution from v1

This crate evolved from `rust/classic/core` by:
1. Adding new types: `Breed` and `Cell`
2. Changing `Cells` from `HashSet<Position>` to `im::HashMap<Position, Cell>`
3. Adding `current_pattern` field to `Core`
4. Changing `next_generation_cells` to use persistent-map fold strategy with cross-breeding
5. Changing `translate` to accept `Cell` reference and perform cross-breeding
6. Adding `define_current_pattern_program` core operation
7. Modifying `add_pattern_program` to accept `Cell` reference and support "current" sentinel
8. Adding v2-specific tests

**Nothing was deleted or rewritten** — all changes are additive or modifying per spec §13.2.

## Public API

The following items are exported from `lib.rs`:

- **Domain types:** `Position`, `Breed`, `Cell`, `Cells`, `Core`, `Program`
- **Life functions:** `neighbors`, `should_live`, `next_generation_cells` (internal use)
- **Pattern functions:** `translate`, `status_message` (internal use)
- **Core operations:** `next_generation_program`, `add_pattern_program`, `switch_status_visibility_program`, `define_current_pattern_program`

**Note:** The shell must NOT directly call pure functions like `neighbors`, `should_live`, `next_generation_cells`, `cross_breed`, `status_message`, or `translate` — these are internal to the core. The shell only calls core operations and interprets the resulting `Program` values.

## Changes from v1

### New Types
- **`Breed`**: A newtype wrapper around `u8` with range 0-15, with smart constructor
- **`Cell`**: A struct containing `position` and `breed`, implementing `Copy` trait

### Modified Types
- **`Cells`**: Changed from `HashSet<Position>` to `im::HashMap<Position, Cell>` to support breeds
- **`Core`**: Added `current_pattern: String` field to track the currently selected pattern

### New Functions
- **`cross_breed(a, b)`**: Computes `(a + b) mod 16` for breed values
- **`bump(counts, position, breed)`**: Updates neighbor count map with persistent insert
- **`define_current_pattern_program(core, name)`**: Sets the current pattern name in core state

### Modified Functions
- **`next_generation_cells(cells)`**: Now uses persistent-map fold strategy, producing newborn cells with cross-bred breeds
- **`translate(points, reference)`**: Now accepts `Cell` reference (not `Position`), performs cross-breeding on pattern cells
- **`add_pattern_program(core, pattern_name, reference)`**: Now accepts `Cell` reference, resolves "current" sentinel to `core.current_pattern`

### Unchanged Functions
- **`neighbors(position)`**: Returns `HashSet<Position>` (now returns std HashSet, not Cells)
- **`should_live(is_alive, count)`**: Life rules unchanged
- **`status_message(cells)`**: Status message format unchanged
- **`switch_status_visibility_program(core)`**: Unchanged
- **`Program` type**: Unchanged (no new opcodes)

## Deviations from Spec and Rust-Specific Choices

### 1. No Memoization for `neighbors`

**Spec requirement:** §2.3 requires memoization "where an idiom exists".

**Rust implementation:** Direct computation, no memoization.

**Rationale:** In Rust, computing 8 neighbors from simple arithmetic (±1 to x and y) is faster than any cache lookup. A cache would require: mutex lock + hash computation + heap allocation per call. Additionally, a global cache would introduce mutable state (violating acceptance criterion #7), serialize all threads on a single lock, and grow unboundedly. In Rust, the idiom for trivial pure functions is to **not** memoize them.

**Status:** Deliberate deviation - we prioritize Rust idioms and the core's purity over mechanical spec compliance.

### 2. Coordinate Type: `i32`

**Spec requirement:** §2.1 specifies unbounded signed integers.

**Rust implementation:** `i32` (range: ±2,147,483,647)

**Rationale:** The range comfortably handles all practical Game of Life use cases including the G11 test (±1,000,000). Using `i64` would double memory usage with no practical benefit.

### 3. Program Encoding: Flat Enum

**Spec requirement:** §3.2 defines `Program ::= Finish | Step Instruction Rest` with `Rest ::= Next | With`.

**Rust implementation:** Flat enum with variants for each instruction + continuation.

**Rationale:** More idiomatic Rust - simpler to implement and pattern match. Equivalent to spec's encoding per §10 ("the sealed-enum + loop is the free monad by hand").

### 4. Cells Representation: `im::HashMap<Position, Cell>`

**Spec requirement:** §2.2 defines `Cells` as a set with order-insensitive equality.

**Rust implementation:** `type Cells = im::HashMap<Position, Cell>`

**Rationale:**
- Enforces "at most one cell per position" at the type level
- Provides efficient O(log n) lookup by position
- Needed for neighbor counting strategy (fold over positions and their cells)
- Satisfies order-insensitive equality requirement (HashMap equality in im-rs is based on key-value pairs, not iteration order)
- This is the idiomatic way to represent "set of cells with at most one per position and breed data" in Rust

### 5. Breed as newtype u8

**Spec requirement:** §2.1 defines Breed as "integer in 0..15"

**Rust implementation:** `struct Breed(u8)` with smart constructor

**Rationale:**
- Type safety: prevents confusing Breed with other integers
- Smart constructor ensures valid range (0-15) via bitwise AND masking
- Efficient: 1 byte per breed value
- `Copy` trait works naturally
- `Hash` and `Eq` traits derived automatically

### 6. neighbors returns std::collections::HashSet<Position>

**Rust implementation:** Returns `std::collections::HashSet<Position>` instead of `Cells`

**Rationale:** The `neighbors` function is about positions only, not cells. It computes the 8 Moore neighbors of a position, which is independent of any breed information. Using `Cells` type would be semantically incorrect since neighbors don't have breeds until they're used in the context of live cells.

## Testing

All core tests are unit tests within the crate (in `src/tests.rs`):

```bash
cd rust/multibreed/core
cargo test
```

### Test Groups Covered

**v1 tests (still must pass):**
- **C (Cell algebra):** C1
- **N (Neighbors):** N1, N2, N5
- **G (Life rules):** G1, G2, G3, G4, G5, G6, G7, G9, G10, G11
- **P (Patterns):** P1, P2, P4
- **X (Cross-cutting):** X1a, X2, X3, X4
- **I (Programs):** I1, I2, I3, I4, I8, I9

**v2-specific tests:**
- **C (Cell algebra):** C2, C3, C4
- **N (Neighbors):** N4
- **G (Life rules):** G8
- **P (Patterns):** P3, P8
- **X (Cross-cutting):** X1b
- **I (Programs):** I5
- **D (Persistent map):** D1, D2, D3, D4, D5, D6

### Tests NOT in Core (Shell Responsibility)

- I6, I7, I10, I11 - require interpreter which lives in the shell

## Build

```bash
cd rust
cargo build
```

This builds the entire workspace. Note that this crate depends on `im-rs` v15.1.

## License

AGPL-3.0
