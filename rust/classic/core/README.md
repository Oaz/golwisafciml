# Rust Classic Core (v1) - Functional Core Implementation

This crate provides the **pure functional core** for Conway's Game of Life, Version 1 (Monochrome) in Rust.

## Overview

This is a **pure** implementation — it contains only types, pure functions, and the `Program` type. It has **no dependencies** on ports, no interpreter, no test doubles, and no mutable state.

All data flows through the `Program` instruction protocol. The shell holds `Core` values but cannot inspect them directly (fields are private). All observations happen through instructions like `ShowCells` and `DisplayMessage`.

## Architecture

The core is structured as a library crate (`gol-classic-core`) that can be depended on by environment-specific shell crates.

### Module Layout

```
rust/classic/
└── core/
    ├── Cargo.toml          # Library crate manifest
    ├── src/
    │   ├── lib.rs          # Public API re-exports
    │   ├── position.rs     # Position type
    │   ├── cells.rs        # Cells type alias
    │   ├── core.rs         # Core state type
    │   ├── life.rs         # Life rules
    │   ├── pattern.rs      # Translation and status
    │   ├── program.rs      # Program type and operations
    │   └── tests.rs        # All core unit tests
    └── README.md           # This file
```

## Public API

The following items are exported from `lib.rs`:

- **Types:** `Position`, `Cells`, `Core`, `Program`
- **Life functions:** `neighbors`, `should_live`, `next_generation_cells`
- **Pattern functions:** `translate`, `status_message`
- **Core operations:** `next_generation_program`, `add_pattern_program`, `switch_status_visibility_program`

## Deviations from Spec

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

### 4. Cells Representation: `HashSet<Position>`

**Spec requirement:** §2.2 defines `Cells` as a set with order-insensitive equality.

**Rust implementation:** `type Cells = HashSet<Position>`

**Rationale:** Rust's idiomatic set type with O(1) membership testing, automatic uniqueness, and order-insensitive equality via hashing.

### 5. No Packed Encoding

**Spec requirement:** Appendix E describes optional packed encoding.

**Rust implementation:** Value-struct model (`Position` struct, `HashSet<Position>`)

**Rationale:** More idiomatic in Rust, type-safe, maintains structural equality and hashing. No practical benefit from packed encoding for v1.

### 6. Opaque Core Type

**Spec principle:** Shell never changes core state directly.

**Rust implementation:** Core struct with private fields, no public getters.

**Rationale:** Enforces Functional Core / Imperative Shell boundary at compile time. Shell cannot access `cells` or `status_visible` directly. All data flows out through Program instructions.

**Consequence:** All core tests must be unit tests within the core crate (in `src/tests.rs`) rather than integration tests (in `tests/`), since integration tests are compiled as a separate crate and cannot access private fields.

## Testing

All core tests are unit tests within the crate (in `src/tests.rs`):

```bash
cd rust/classic/core
cargo test
```

### Test Groups Covered

- **C (Cell algebra):** C1
- **N (Neighbors):** N1, N2, N5
- **G (Life rules):** G1, G2, G3, G4, G5, G6, G7, G9, G11
- **P (Patterns):** P1, P2, P4
- **X (Cross-cutting):** X1a, X2, X3, X4
- **I (Programs):** I1, I2, I3, I4, I8, I9

### Tests NOT in Core (Shell Responsibility)

- I5, I6, I7, I10, I11 - require interpreter which lives in the shell

## Evolution to v2

The core is designed for easy evolution to v2 (multi-breed):

- The `Program` enum can be extended with new variants for v2 operations
- New types (`Breed`, `Cell`) can be added
- The neighbor counting strategy can be changed to persistent-map fold
- External dependencies (like `im-rs` for persistent maps) can be added to v2

v1 maintains **zero external dependencies** (std only).

## Build

```bash
cd rust
cargo build
```

This builds the entire workspace including the core crate.

## License

AGPL-3.0
