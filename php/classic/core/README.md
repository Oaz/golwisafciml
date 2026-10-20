# PHP Classic Core (v1) - Functional Core for Conway's Game of Life

This is the **functional core** for Conway's Game of Life, Version 1 (Monochrome) implemented in PHP.

## Overview

This core is **pure** - it contains only types, pure functions, and the Program type. It has:
- **No dependencies on ports**
- **No interpreter** (belongs in the shell)
- **No test doubles** (fakes, mocks, spies)

Core tests verify Program structure via the visitor pattern, not via interpretation.

## Architecture

### Domain Types

- `Position` - Immutable value object representing a grid coordinate (x, y)
- `Cells` - Immutable set of Position objects with set semantics
- `Core` - Immutable state containing `cells` and `statusVisible` flag

### Pure Functions

- `Life::neighbors()` - Get 8 Moore neighbors of a position
- `Life::shouldLive()` - Determine if a cell lives based on life rules
- `Life::nextGenerationCells()` - Compute next generation using candidate-set filtering
- `Pattern::translate()` - Translate cells by a reference position
- `Pattern::statusMessage()` - Get status message for cell count

### Program Type

The Program type is represented as a class hierarchy:
- `Program` - Abstract base class
- `ReturnProgram` - Terminates and commits the core
- `ShowCellsProgram` - Display cells
- `DisplayMessageProgram` - Display a message
- `HideMessageProgram` - Hide the status message
- `GetPatternCellsProgram` - Fetch pattern cells from store

The `ProgramVisitor` interface enables pattern matching for interpretation.

### Core Operations

- `CoreOperations::ret()` - Create Return program
- `CoreOperations::showCells()` - Create ShowCells program
- `CoreOperations::displayMessage()` - Create DisplayMessage program
- `CoreOperations::hideMessage()` - Create HideMessage program
- `CoreOperations::getPatternCells()` - Create GetPatternCells program
- `CoreOperations::nextGenerationProgram()` - Build next generation program
- `CoreOperations::addPatternProgram()` - Build add pattern program
- `CoreOperations::switchStatusVisibilityProgram()` - Toggle status visibility

## Installation

```bash
cd php/classic/core
composer install
```

## Running Tests

```bash
./vendor/bin/phpunit
```

## PHP Version Requirement

PHP 8.1+ is required for:
- `readonly` properties for immutability
- Modern type system features

## Deviations from Spec

### Immutability

PHP lacks deep immutability guarantees. We use:
- `readonly` properties on classes (PHP 8.1+)
- Copy-on-write discipline (methods return new instances)
- No caching (avoids mutable state entirely)

The core has **zero mutable state** - no static variables, no global state.

### Cells Representation

`Cells` is implemented as a class wrapping a PHP associative array keyed by position hash. It implements:
- Set semantics (uniqueness, order-insensitive equality)
- O(1) lookups via associative array
- Structural equality via `equals()` method

### Program Representation

We use a **class hierarchy with visitor pattern** instead of algebraic data types:
- Most idiomatic PHP approach
- Provides type safety through class types
- Visitor pattern enables interpretation
- Easy to test with `instanceof` checks

### Caching

**Deliberate deviation:** We do NOT cache `neighbors()` results.

Rationale:
- Function is trivial (8 arithmetic operations)
- For web use with single generation per request, reuse rate is 0%
- Storage overhead exceeds computation cost
- In PHP-FPM, stored results would grow unboundedly
- Function remains observationally pure

### Coordinate Type

Uses PHP `int` type. Range is platform-dependent but comfortably passes G11 test (±1,000,000).

## Test Coverage

### Pure Function Tests (CoreTest.php)

- **C1** - Position construction/accessor round-trip
- **N1-N3, N5** - Neighbors function correctness and purity
- **G1** - shouldLive truth table (18 cases)
- **G2** - Oscillator periods (blinker, toad, beacon)
- **G3** - Still lifes (block, beehive, loaf, boat, tub)
- **G4** - Pentadecathlon period 15
- **G5** - Glider translation
- **G6** - Two-generation chain
- **G7** - Extinction patterns
- **G9** - Purity verification
- **G11** - Unboundedness (large coordinates)
- **P1** - statusMessage grammar
- **P2** - translate offsets
- **P4** - translate cardinality preservation
- **X1a** - initialCore defaults
- **X2** - Non-destructive field updates
- **X3** - Cardinalities
- **X4** - Core equality

### Program Structure Tests (ProgramTest.php)

- **I1** - Instruction identity (all 5 opcodes distinguishable)
- **I2** - nextGenerationProgram shape
- **I3** - switchStatusVisibilityProgram shape
- **I4** - addPatternProgram shape
- **I8** - Program well-formedness (all end with Return)
- **I9** - Unknown pattern continuation handling

## Evolution to v2

The architecture is designed for easy evolution to v2 (multi-breed):
- Program class hierarchy extends naturally with new subclasses
- New instructions add new `Program` subclasses
- Existing interpreter gains new visit methods
- No changes to existing Program classes needed

## License

AGPL-3.0
