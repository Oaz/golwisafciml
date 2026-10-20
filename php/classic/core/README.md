# Game of Life Classic Core (v1) - PHP Implementation

This directory contains the **pure functional core** for Conway's Game of Life, Version 1 (Monochrome) in PHP.

**Status:** Plan written, awaiting approval before implementation (see `plan.md`).

## Overview

This implementation follows the **Functional Core / Imperative Shell** pattern:
- The **core** (this directory) contains only pure types, pure functions, and the Program type
- The **shell** (separate directories) contains the interpreter, ports, and environment-specific code

## Architecture

### Core Components

| File | Purpose |
|------|---------|
| `src/Position.php` | Position value object with x, y coordinates |
| `src/Cells.php` | Cells collection (immutable set of Position objects) |
| `src/Core.php` | Core state (immutable, with cells and statusVisible) |
| `src/Life.php` | Life rules: neighbors, shouldLive, nextGenerationCells |
| `src/Pattern.php` | Translation and status message functions |
| `src/Program.php` | Abstract Program base class |
| `src/ReturnProgram.php` | Return instruction (terminator) |
| `src/ShowCellsProgram.php` | ShowCells instruction |
| `src/DisplayMessageProgram.php` | DisplayMessage instruction |
| `src/HideMessageProgram.php` | HideMessage instruction |
| `src/GetPatternCellsProgram.php` | GetPatternCells instruction with continuation |
| `src/ProgramVisitor.php` | Visitor interface for pattern matching |
| `src/CoreOperations.php` | Core operations producing Programs |
| `composer.json` | Composer package configuration (zero dependencies) |
| `tests/` | Complete test suite using PHPUnit |

## Design Decisions

### Language Requirements
- **PHP 8.1+** required (for `readonly` properties)

### Data Types
- **Position:** `final readonly class` with `x: int`, `y: int`
- **Cells:** Custom immutable collection class (wraps PHP array with set semantics)
- **Core:** `final readonly class` with `cells: Cells`, `statusVisible: bool`

### Program Type
- **Class hierarchy** with abstract `Program` base class and concrete instruction subclasses
- **Visitor pattern** for interpretation (implemented by shell)
- Each instruction class is `final readonly` for immutability

### Memoization
- **Static array cache** in `Life` class for `neighbors()` function
- Observationally pure: same input always returns same output

### Dependencies
- **Zero runtime dependencies** - pure PHP standard library
- **Development dependencies:** PHPUnit, PHPStan (via Composer)

## Implementation Plan

See `plan.md` for the complete implementation plan, including:
- Detailed file layout and responsibilities
- PHP-specific design decisions
- Test organization and coverage
- Deviations from spec and their rationales
- Evolution considerations for v2

## Building

The core is a **Composer library package** with zero dependencies:

```bash
# Install development dependencies
composer install

# Validate composer.json
composer validate
```

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Usage

This core is intended to be used by shell implementations. To reference it from a shell:

1. Add to your shell's `composer.json`:
   ```json
   {
     "require": {
       "golwisafciml/php-classic-core": "@dev"
     },
     "repositories": [
       {
         "type": "path",
         "url": "../core"
       }
     ]
   }
   ```

2. Use the core types in your shell code:
   ```php
   use GameOfLife\Classic\Core\Core;
   use GameOfLife\Classic\Core\CoreOperations;
   
   $core = Core::initial();
   $program = CoreOperations::nextGenerationProgram($core);
   ```

## Deviations from Spec

See `plan.md` for detailed deviations. Key PHP-specific choices:

1. **Immutability:** PHP lacks deep immutability; we use `readonly` properties and copy-on-write discipline
2. **Cells representation:** Custom `Cells` class with set semantics (PHP has no built-in immutable set)
3. **Program representation:** Class hierarchy with visitor pattern (most idiomatic PHP approach)
4. **Memoization:** Static array cache (observationally pure, suitable for PHP's single-threaded context)
5. **Coordinate type:** PHP `int` (platform-dependent range, typically ±2.1 billion or ±9.2 quintillion)

## Design for Evolution (v1 → v2)

The class hierarchy approach makes v2 evolution straightforward:
- New instructions add new `Program` subclasses
- Existing interpreter gains new visit methods
- Core gains `currentPattern` field and related operations
- Cells and Position evolve to include breed information

See `plan.md` §6 for detailed evolution considerations.
