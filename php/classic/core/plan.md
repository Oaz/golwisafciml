# Plan: PHP Classic Core (v1) — Functional Core Implementation

**Task:** Implement the functional core for Conway's Game of Life, Version 1 (Monochrome) in PHP.

**Location:** `php/classic/core/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

**Owner:** Olivier (Oaz)

---

## 0. Context and Scope

This plan covers the **functional core** for the **classic (v1) monochrome** Game of Life in PHP, following the repository's architecture and the language-agnostic specification in `specs.md`.

**Revised understanding:** The core is **pure** — it contains only types, pure functions, and the Program type. It has **no dependencies on ports, no interpreter, no test doubles**. Core tests verify Program structure via pattern matching (or equivalent PHP techniques), not via interpretation.

### What is IN scope

- Complete PHP implementation of the **v1 functional core** as defined in `specs.md` §2, §3, and §6 (v1 column)
- All pure domain types: `Position` (value object), `Cells` (set of positions)
- All pure core state: `Core` (value object with `cells` and `statusVisible`)
- All pure functions: neighbors, shouldLive, nextGenerationCells (candidate-set filtering strategy), translate, statusMessage
- All core operations producing programs: nextGenerationProgram, addPatternProgram, switchStatusVisibilityProgram
- The **Program type** (recursive data structure encoding the instruction protocol)
- Full test suite for v1: Groups C (C1), N (N1–N3, N5), G (G1–G7, G9–G11), P (P1–P2, P4), I (I1–I4, I8–I9), X (X1a, X2–X3, X4)

### What is OUT of scope (deliberately)

- Version 2 (multi-breed) — will be implemented later as an evolution of this v1 codebase (§13.2)
- Imperative shell (separate task: `php/classic/<environment>/` folders)
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
php/classic/core/
├── composer.json         # Composer package configuration (zero dependencies)
├── src/
│   ├── Position.php     # Position value object
│   ├── Cells.php        # Cells collection (as array of Position objects)
│   ├── Core.php         # Core state value object and field update functions
│   ├── Life.php         # Life rules: neighbors, shouldLive, nextGenerationCells
│   ├── Pattern.php      # Translation and status message functions
│   ├── Program.php      # Program type and core operations
│   └── CoreFunctions.php # Public API entry point (autoloaded)
├── tests/
│   ├── phpunit.xml      # PHPUnit configuration
│   ├── bootstrap.php    # Test bootstrap (autoloading setup)
│   ├── CoreTest.php     # Pure function tests (C, N, G, P, X)
│   └── ProgramTest.php  # Program structure tests (I1–I4, I8–I9)
└── README.md            # Core-specific documentation
```

### File Responsibilities

| File | Responsibility | Key Types/Functions |
|------|---------------|---------------------|
| `Position.php` | Position domain type | `Position` class with x, y properties, equality, hash |
| `Cells.php` | Cells collection type | `Cells` class wrapping array of Position, set semantics |
| `Core.php` | Core state | `Core` class with cells, statusVisible; initialCore(), withCells(), withStatusVisible(), cellsOf(), statusVisibleOf() |
| `Life.php` | Life game logic | neighbors(), shouldLive(), nextGenerationCells() |
| `Pattern.php` | Pattern and status functions | translate(), statusMessage() |
| `Program.php` | Program type and operations | Program class (recursive), nextGenerationProgram(), addPatternProgram(), switchStatusVisibilityProgram() |
| `CoreFunctions.php` | Public API | Exports all public types and functions |
| `tests/*.php` | Test suite | All v1 core tests |
| `composer.json` | Package configuration | Library package, zero dependencies, PSR-4 autoloading |
| `README.md` | Documentation | PHP-specific notes, deviations from spec defaults |

---

## 2. Language-Specific Design Decisions (PHP Idioms)

### 2.1 Build Tooling and Project Structure

**Decision:** Use **Composer** with PSR-4 autoloading.

**Rationale:** Standard PHP dependency management and autoloading; matches repo README guidance.

**composer.json:**
```json
{
    "name": "golwisafciml/php-classic-core",
    "type": "library",
    "description": "Functional core for Conway's Game of Life (v1) - Classic Monochrome",
    "license": "AGPL-3.0",
    "autoload": {
        "psr-4": {
            "GameOfLife\\Classic\\Core\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "GameOfLife\\Classic\\Core\\Tests\\": "tests/"
        }
    },
    "require": {},
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.10"
    },
    "minimum-stability": "dev",
    "config": {
        "optimize-autoloader": true
    }
}
```

**Key decisions:**
- **Namespace:** `GameOfLife\Classic\Core\`
- **Package type:** `library` (not application)
- **Zero runtime dependencies:** Empty `require` section
- **Dev dependencies:** PHPUnit for testing, PHPStan for static analysis
- **PSR-4 autoloading:** Maps namespace to `src/` directory
- **Minimum stability:** `dev` allows local development

### 2.2 PHP Version Requirement

**Decision:** PHP 8.1+ required.

**Rationale:**
- PHP 8.1 introduced `readonly` properties for true immutability
- PHP 8.1+ has modern type system (union types, intersection types, etc.)
- PHP 8.0+ has named arguments, match expression (8.0), enums (8.1)
- Matches industry standard for modern PHP projects

### 2.3 Data Representations

#### Position

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Position is an immutable value object representing a grid coordinate.
 * Implements structural equality and can be used as array keys.
 */
final readonly class Position
{
    public function __construct(
        public int $x,
        public int $y
    ) {}

    /**
     * Create a new Position (factory method for readability).
     */
    public static function create(int $x, int $y): self
    {
        return new self($x, $y);
    }

    /**
     * Structural equality: two positions are equal if their coordinates match.
     */
    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    /**
     * Hash representation for use as array/map keys.
     * Returns a string key unique to this position.
     */
    public function hash(): string
    {
        return sprintf('%d,%d', $this->x, $this->y);
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        return sprintf('(%d, %d)', $this->x, $this->y);
    }
}
```

**Rationale:**
- `final readonly class` ensures immutability at runtime
- `public` properties with `readonly` are set once in constructor, never changed
- `equals()` method provides structural equality
- `hash()` method provides consistent hashing for use as keys
- `__toString()` for debugging
- Factory method `create()` for fluent construction

#### Cells

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Cells is an immutable set of Position objects.
 * Provides set semantics: uniqueness, order-insensitive equality.
 */
final readonly class Cells
{
    /** @var array<string, Position> Map from hash to Position */
    private array $positions;

    /**
     * Create Cells from an array of Positions.
     * Duplicates are automatically removed.
     *
     * @param Position[] $positions
     */
    public function __construct(Position ...$positions)
    {
        $this->positions = [];
        foreach ($positions as $position) {
            $this->positions[$position->hash()] = $position;
        }
    }

    /**
     * Create empty Cells.
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Create Cells from an iterable of Positions.
     *
     * @param iterable<Position> $iterable
     */
    public static function fromIterable(iterable $iterable): self
    {
        $positions = [];
        foreach ($iterable as $position) {
            if ($position instanceof Position) {
                $positions[$position->hash()] = $position;
            }
        }
        return new self(...$positions);
    }

    /**
     * Check if a position is in this set.
     */
    public function contains(Position $position): bool
    {
        return isset($this->positions[$position->hash()]);
    }

    /**
     * Get the number of cells.
     */
    public function count(): int
    {
        return count($this->positions);
    }

    /**
     * Check if empty.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Get all positions as an array.
     *
     * @return Position[]
     */
    public function all(): array
    {
        return array_values($this->positions);
    }

    /**
     * Check equality with another Cells (order-insensitive).
     */
    public function equals(self $other): bool
    {
        // If counts differ, not equal
        if ($this->count() !== $other->count()) {
            return false;
        }
        // Check all positions in $this are in $other
        foreach ($this->positions as $hash => $position) {
            if (!isset($other->positions[$hash])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Create a new Cells with an additional position.
     */
    public function with(Position $position): self
    {
        $new = clone $this;
        $new->positions[$position->hash()] = $position;
        // Workaround: readonly properties prevent modification after clone
        // This requires a different approach - see note below
    }

    /**
     * Get as array for iteration.
     *
     * @return Position[]
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        $items = array_map(fn($p) => (string)$p, $this->all());
        return 'Cells{' . implode(', ', $items) . '}';
    }
}
```

**Important note on immutability with PHP 8.1+:** PHP's `readonly` properties on classes create a **shallow immutability** — the object reference cannot be changed, but the internal array can still be modified. This is a limitation of PHP's value semantics.

**Revised approach for truly immutable Cells:**

Since PHP lacks deep immutability, we adopt a **copy-on-write** discipline:

```php
final class Cells
{
    /** @var array<string, Position> */
    private array $positions;

    public function __construct(Position ...$positions)
    {
        $this->positions = [];
        foreach ($positions as $position) {
            $this->positions[$position->hash()] = $position;
        }
    }

    // ... other methods ...

    /**
     * Create a new Cells with an additional position.
     * Immutability: returns a new instance rather than modifying $this.
     */
    public function add(Position $position): self
    {
        $new = new self(...$this->all());
        // Since __construct removes duplicates, we can just add
        return new self(...$this->all(), $position);
    }

    /**
     * Create a new Cells with positions removed.
     */
    public function remove(Position $position): self
    {
        $filtered = array_filter(
            $this->all(),
            fn($p) => !$p->equals($position)
        );
        return new self(...$filtered);
    }

    /**
     * Create a new Cells as the union with another Cells.
     */
    public function union(self $other): self
    {
        return new self(...array_merge($this->all(), $other->all()));
    }

    /**
     * Create a new Cells as the intersection with another Cells.
     */
    public function intersection(self $other): self
    {
        $filtered = array_filter(
            $this->all(),
            fn($p) => $other->contains($p)
        );
        return new self(...$filtered);
    }

    /**
     * Map over all positions and return a new Cells.
     *
     * @param callable(Position): Position $callback
     */
    public function map(callable $callback): self
    {
        $mapped = array_map($callback, $this->all());
        return new self(...$mapped);
    }

    /**
     * Filter positions and return a new Cells.
     *
     * @param callable(Position): bool $callback
     */
    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->all(), $callback);
        return new self(...$filtered);
    }
}
```

**Alternative approach using arrays directly:**

For simplicity and performance, we could use plain PHP arrays throughout the core, with a convention that arrays of Position are `Cells`. However, this loses type safety and makes the code less self-documenting.

**Decision:** Use a `Cells` class with value semantics and copy-on-write methods. While PHP doesn't provide deep immutability guarantees, the discipline of always returning new instances provides immutability in practice.

#### Core State

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Core is the immutable state of the Game of Life functional core.
 * Contains only the cells and status visibility flag.
 */
final readonly class Core
{
    public function __construct(
        public Cells $cells,
        public bool $statusVisible
    ) {}

    /**
     * Create the initial core state.
     */
    public static function initial(): self
    {
        return new self(Cells::empty(), false);
    }

    /**
     * Create a new Core with the given cells.
     * Immutability: returns new instance.
     */
    public function withCells(Cells $cells): self
    {
        return new self($cells, $this->statusVisible);
    }

    /**
     * Create a new Core with the given status visibility.
     */
    public function withStatusVisible(bool $statusVisible): self
    {
        return new self($this->cells, $statusVisible);
    }

    /**
     * Get the cells (observer).
     */
    public function cellsOf(): Cells
    {
        return $this->cells;
    }

    /**
     * Get the status visibility (observer).
     */
    public function statusVisibleOf(): bool
    {
        return $this->statusVisible;
    }

    /**
     * Check equality with another Core.
     */
    public function equals(self $other): bool
    {
        return $this->cells->equals($other->cells)
            && $this->statusVisible === $other->statusVisible;
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        return sprintf(
            'Core{cells=%d, statusVisible=%s}',
            $this->cells->count(),
            $this->statusVisible ? 'true' : 'false'
        );
    }
}
```

### 2.4 Neighbors Function

**Decision:** Direct computation without caching.

**Rationale:** For web-only usage with single generation per request:
- The function computes only 8 positions via simple arithmetic (x±1, y±1)
- Storing results would add overhead (string hash computation + array lookup) that exceeds the computation cost
- Reuse rate would be **0%** — each position appears only once per request
- In PHP-FPM, stored results would grow unboundedly (memory leak) with no benefit
- PHP idiom for trivial pure functions: compute directly each time

**Spec Note:** §2.3 recommends storing results "where an idiom exists." In PHP for this use case, the idiom is direct computation — the overhead exceeds the benefit for single-generation web requests.

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

final class Life
{
    /**
     * Get the 8 Moore neighbors of a position.
     * Direct computation (faster for single-generation web use).
     */
    public static function neighbors(Position $position): Cells
    {
        $neighbors = [];
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue; // Skip the position itself
                }
                $neighbors[] = Position::create(
                    $position->x + $dx,
                    $position->y + $dy
                );
            }
        }
        return new Cells(...$neighbors);
    }

    // ... other functions ...
}
```

**Note:** This is a **deliberate deviation** from a strict reading of §2.3. We prioritize PHP idioms (no unnecessary indirection for trivial functions) and actual performance characteristics (overhead > benefit for this use case). The function remains observationally pure.

### 2.5 Life Rules Implementation

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

final class Life
{
    // ... neighbors() ...

    /**
     * Life rule: should a cell live given its current state and neighbor count?
     */
    public static function shouldLive(bool $isAlive, int $liveNeighborCount): bool
    {
        if ($isAlive) {
            // Survival: 2 or 3 live neighbors
            return $liveNeighborCount === 2 || $liveNeighborCount === 3;
        } else {
            // Birth: exactly 3 live neighbors
            return $liveNeighborCount === 3;
        }
    }

    /**
     * Get all cells and their neighbors (candidate set).
     */
    public static function allCellsAndNeighbors(Cells $cells): Cells
    {
        $all = [];
        foreach ($cells->all() as $cell) {
            $all[] = $cell;
            foreach (self::neighbors($cell)->all() as $neighbor) {
                $all[] = $neighbor;
            }
        }
        return new Cells(...$all);
    }

    /**
     * Compute the next generation of cells using candidate-set filtering strategy.
     * This is the v1 strategy as specified in §2.4.1.
     */
    public static function nextGenerationCells(Cells $cells): Cells
    {
        $candidates = self::allCellsAndNeighbors($cells);
        
        $nextCells = [];
        foreach ($candidates->all() as $position) {
            $isAlive = $cells->contains($position);
            $neighbors = self::neighbors($position);
            
            // Count live neighbors
            $liveNeighborCount = 0;
            foreach ($neighbors->all() as $neighbor) {
                if ($cells->contains($neighbor)) {
                    $liveNeighborCount++;
                }
            }
            
            if (self::shouldLive($isAlive, $liveNeighborCount)) {
                $nextCells[] = $position;
            }
        }
        
        return new Cells(...$nextCells);
    }
}
```

### 2.6 Translation and Status Message

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

final class Pattern
{
    /**
     * Translate a set of positions by a reference position.
     * Adds the reference position's coordinates to each position.
     */
    public static function translate(Cells $points, Position $reference): Cells
    {
        return $points->map(function(Position $q) use ($reference): Position {
            return Position::create(
                $q->x + $reference->x,
                $q->y + $reference->y
            );
        });
    }

    /**
     * Get the status message for the current cell count.
     * Normative text from spec §2.6.
     */
    public static function statusMessage(Cells $cells): string
    {
        $n = $cells->count();
        
        if ($n === 0) {
            return 'No cell is alive';
        } elseif ($n === 1) {
            return '1 cell is alive';
        } else {
            return sprintf('%d cells are alive', $n);
        }
    }
}
```

### 2.7 Program Type

PHP doesn't have algebraic data types or pattern matching, so we need a different approach. We have two main options:

**Option A: Class hierarchy with inheritance**
- Abstract `Program` class
- Concrete subclasses: `ReturnProgram`, `ShowCellsProgram`, etc.
- Visitor pattern for interpretation

**Option B: Discriminated array/closure-based approach**
- Represent Program as a closure that takes ports and returns the next step
- More functional but harder to test structure

**Option C: Tagged array approach**
- Represent each instruction as an array with a type tag and payload
- Simple, easy to test, but less type-safe

**Decision:** Use **Option A (Class hierarchy)** as it's the most idiomatic PHP approach and provides the best type safety.

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Abstract base class for Program.
 * Programs are pure data structures representing sequences of instructions.
 */
abstract readonly class Program
{
    /**
     * Accept a visitor for pattern matching.
     * This enables the interpreter to dispatch on program type.
     */
    abstract public function accept(ProgramVisitor $visitor): mixed;

    /**
     * Check if this is a Return program (terminator).
     */
    abstract public function isReturn(): bool;

    /**
     * For ShowCells: get the cells.
     */
    public function getCells(): ?Cells
    {
        return null;
    }

    /**
     * For DisplayMessage: get the message.
     */
    public function getMessage(): ?string
    {
        return null;
    }

    /**
     * For GetPatternCells: get the pattern name.
     */
    public function getPatternName(): ?string
    {
        return null;
    }

    /**
     * For GetPatternCells: get the continuation.
     * @return callable(Cells): Program
     */
    public function getContinuation(): ?callable
    {
        return null;
    }

    /**
     * Get the next program (for chained instructions).
     */
    public function getNext(): ?self
    {
        return null;
    }

    /**
     * For Return: get the Core.
     */
    public function getCore(): ?Core
    {
        return null;
    }
}

/**
 * Return instruction - terminates the program and commits the core.
 */
final readonly class ReturnProgram extends Program
{
    public function __construct(public Core $core) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitReturn($this);
    }

    public function isReturn(): bool
    {
        return true;
    }

    public function getCore(): Core
    {
        return $this->core;
    }
}

/**
 * ShowCells instruction - render these cells.
 */
final readonly class ShowCellsProgram extends Program
{
    public function __construct(
        public Cells $cells,
        public Program $next
    ) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitShowCells($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getCells(): Cells
    {
        return $this->cells;
    }

    public function getNext(): Program
    {
        return $this->next;
    }
}

/**
 * DisplayMessage instruction - show this message.
 */
final readonly class DisplayMessageProgram extends Program
{
    public function __construct(
        public string $message,
        public Program $next
    ) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitDisplayMessage($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getNext(): Program
    {
        return $this->next;
    }
}

/**
 * HideMessage instruction - hide the status message.
 */
final readonly class HideMessageProgram extends Program
{
    public function __construct(public Program $next) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitHideMessage($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getNext(): Program
    {
        return $this->next;
    }
}

/**
 * GetPatternCells instruction - fetch pattern cells from store.
 * Has a continuation that receives the cells.
 */
final readonly class GetPatternCellsProgram extends Program
{
    public function __construct(
        public string $patternName,
        public callable $continuation
    ) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitGetPatternCells($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getPatternName(): string
    {
        return $this->patternName;
    }

    public function getContinuation(): callable
    {
        return $this->continuation;
    }
}

/**
 * Visitor interface for pattern matching on Program types.
 * This is the interpretation mechanism - the shell implements this.
 */
interface ProgramVisitor
{
    public function visitReturn(ReturnProgram $program): mixed;
    public function visitShowCells(ShowCellsProgram $program): mixed;
    public function visitDisplayMessage(DisplayMessageProgram $program): mixed;
    public function visitHideMessage(HideMessageProgram $program): mixed;
    public function visitGetPatternCells(GetPatternCellsProgram $program): mixed;
}
```

**Alternative simpler approach:**

Since PHP 8.0 has match expressions, we could use a simpler approach with a tagged array:

```php
// Simpler representation using arrays
$program = [
    'type' => 'ShowCells',
    'cells' => $cells,
    'next' => $nextProgram
];
```

However, this loses type safety and is harder to test. The class hierarchy approach is more robust and maintainable.

**Decision:** Use the **class hierarchy with visitor pattern** for Program representation.

### 2.8 Core Operations

```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

final class CoreOperations
{
    /**
     * Create a Return program.
     */
    public static function ret(Core $core): Program
    {
        return new ReturnProgram($core);
    }

    /**
     * Create a ShowCells program.
     */
    public static function showCells(Cells $cells, Program $next): Program
    {
        return new ShowCellsProgram($cells, $next);
    }

    /**
     * Create a DisplayMessage program.
     */
    public static function displayMessage(string $message, Program $next): Program
    {
        return new DisplayMessageProgram($message, $next);
    }

    /**
     * Create a HideMessage program.
     */
    public static function hideMessage(Program $next): Program
    {
        return new HideMessageProgram($next);
    }

    /**
     * Create a GetPatternCells program.
     * The continuation receives the cells and returns the next program.
     */
    public static function getPatternCells(
        string $name,
        callable $continuation
    ): Program {
        return new GetPatternCellsProgram($name, $continuation);
    }

    /**
     * Build the nextGeneration program.
     * Pure function: given a Core, returns a Program.
     */
    public static function nextGenerationProgram(Core $core): Program
    {
        $newCells = Life::nextGenerationCells($core->cellsOf());
        $newCore = $core->withCells($newCells);
        
        $program = self::showCells($newCells, self::ret($newCore));
        
        if ($core->statusVisibleOf()) {
            $status = Pattern::statusMessage($newCells);
            $program = self::displayMessage($status, $program);
        }
        
        return $program;
    }

    /**
     * Build the addPattern program.
     * Pure function: given Core, pattern name, and reference position, returns Program.
     */
    public static function addPatternProgram(
        Core $core,
        string $patternName,
        Position $reference
    ): Program {
        return self::getPatternCells(
            $patternName,
            function(Cells $patternCells) use ($core, $reference): Program {
                $translated = Pattern::translate($patternCells, $reference);
                $newCells = $core->cellsOf()->union($translated);
                $newCore = $core->withCells($newCells);
                
                $program = self::showCells($newCells, self::ret($newCore));
                
                if ($core->statusVisibleOf()) {
                    $status = Pattern::statusMessage($newCells);
                    $program = self::displayMessage($status, $program);
                }
                
                return $program;
            }
        );
    }

    /**
     * Build the switchStatusVisibility program.
     */
    public static function switchStatusVisibilityProgram(Core $core): Program
    {
        if ($core->statusVisibleOf()) {
            $newCore = $core->withStatusVisible(false);
            return self::hideMessage(self::ret($newCore));
        } else {
            $status = Pattern::statusMessage($core->cellsOf());
            $newCore = $core->withStatusVisible(true);
            return self::displayMessage($status, self::ret($newCore));
        }
    }
}
```

---

## 3. Public API

**CoreFunctions.php:**
```php
<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

// Re-export all public types and functions

// Domain types
require_once __DIR__ . '/Position.php';
require_once __DIR__ . '/Cells.php';
require_once __DIR__ . '/Core.php';

// Logic functions
require_once __DIR__ . '/Life.php';
require_once __DIR__ . '/Pattern.php';

// Program types and operations
require_once __DIR__ . '/Program.php';
require_once __DIR__ . '/ReturnProgram.php';
require_once __DIR__ . '/ShowCellsProgram.php';
require_once __DIR__ . '/DisplayMessageProgram.php';
require_once __DIR__ . '/HideMessageProgram.php';
require_once __DIR__ . '/GetPatternCellsProgram.php';
require_once __DIR__ . '/ProgramVisitor.php';
require_once __DIR__ . '/CoreOperations.php';

// Explicitly list public API
const PUBLIC_CLASSES = [
    Position::class,
    Cells::class,
    Core::class,
    Program::class,
    ReturnProgram::class,
    ShowCellsProgram::class,
    DisplayMessageProgram::class,
    HideMessageProgram::class,
    GetPatternCellsProgram::class,
    ProgramVisitor::class,
];

const PUBLIC_FUNCTIONS = [
    // Position
    'GameOfLife\Classic\Core\Position::create',
    
    // Cells
    'GameOfLife\Classic\Core\Cells::empty',
    'GameOfLife\Classic\Core\Cells::fromIterable',
    
    // Core
    'GameOfLife\Classic\Core\Core::initial',
    
    // Life
    'GameOfLife\Classic\Core\Life::neighbors',
    'GameOfLife\Classic\Core\Life::shouldLive',
    'GameOfLife\Classic\Core\Life::nextGenerationCells',
    
    // Pattern
    'GameOfLife\Classic\Core\Pattern::translate',
    'GameOfLife\Classic\Core\Pattern::statusMessage',
    
    // CoreOperations
    'GameOfLife\Classic\Core\CoreOperations::ret',
    'GameOfLife\Classic\Core\CoreOperations::showCells',
    'GameOfLife\Classic\Core\CoreOperations::displayMessage',
    'GameOfLife\Classic\Core\CoreOperations::hideMessage',
    'GameOfLife\Classic\Core\CoreOperations::getPatternCells',
    'GameOfLife\Classic\Core\CoreOperations::nextGenerationProgram',
    'GameOfLife\Classic\Core\CoreOperations::addPatternProgram',
    'GameOfLife\Classic\Core\CoreOperations::switchStatusVisibilityProgram',
];
```

**Note:** With Composer PSR-4 autoloading, we don't actually need a CoreFunctions.php file — Composer handles the autoloading automatically. We can remove this file and rely on Composer.

---

## 4. Testing Framework

### 4.1 Test Framework Choice

**Decision:** Use **PHPUnit** (the standard PHP testing framework).

**Rationale:**
- Industry standard for PHP
- Full-featured and well-documented
- Supports data providers for parameterized tests
- Integrates with CI/CD

**Alternative:** Pest (more modern, but PHPUnit is more standard).

### 4.2 Test Organization

```
php/classic/core/tests/
├── phpunit.xml      # PHPUnit configuration
├── bootstrap.php    # Test bootstrap (autoloading)
├── CoreTest.php     # Tests for domain types and pure functions
└── ProgramTest.php  # Tests for Program structure and core operations
```

**phpunit.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
         bootstrap="bootstrap.php"
         cacheDirectory=".phpunit.cache"
         executionOrder="defects-first"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Game of Life Classic Core">
            <directory>.</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">../src</directory>
        </include>
        <exclude>
            <file>../src/bootstrap.php</file>
        </exclude>
    </source>
</phpunit>
```

**bootstrap.php:**
```php
<?php

declare(strict_types=1);

// Require Composer autoloader
require __DIR__ . '/../../vendor/autoload.php';
```

### 4.3 Core Testing Philosophy

**The core is pure. Therefore core tests need NO test doubles (no fakes, no mocks, no spies).**

**Test categories:**

1. **Pure function tests** — Directly test functions like `Life::neighbors()`, `Life::nextGenerationCells()`, `Pattern::statusMessage()`
   - No setup needed, just call the static methods with inputs and check outputs
   - Example: `$this->assertCount(8, Life::neighbors(Position::create(0, 0)));`

2. **Value object tests** — Verify `Position`, `Cells`, `Core` construction, equality, and immutability
   - Example: `$this->assertTrue($pos1->equals($pos2));`

3. **Program structure tests** — Verify the shape of Programs produced by core operations
   - Use instanceof checks to inspect the Program type
   - For `GetPatternCellsProgram`: extract the continuation and call it with test data
   - Example for I9 (unknown pattern):
     ```php
     public function testUnknownPattern(): void
     {
         $core = Core::initial();
         $program = CoreOperations::addPatternProgram(
             $core,
             'nonexistent',
             Position::create(0, 0)
         );
         
         $this->assertInstanceOf(GetPatternCellsProgram::class, $program);
         
         // Get the continuation
         $continuation = $program->getContinuation();
         
         // Call continuation with empty cells
         $result = $continuation(Cells::empty());
         
         // Verify result shows unchanged cells and returns original core
         $this->assertInstanceOf(ShowCellsProgram::class, $result);
         $this->assertEquals($core->cellsOf(), $result->getCells());
         
         $next = $result->getNext();
         $this->assertInstanceOf(ReturnProgram::class, $next);
         $this->assertTrue($next->getCore()->equals($core));
     }
     ```

**No PatternStore fake needed** — we directly call continuations with test data, simulating what the interpreter would do.

### 4.4 Test Groups Coverage

#### Pure Function Tests (CoreTest.php)

**Group C — Cell algebra:**
- C1: Position construction/accessor round-trip with coordinates in {-500000, -1, 0, 1, 499999}

**Group N — Neighbors and counting:**
- N1: neighbors returns exactly 8 distinct positions
- N2: neighbors is observationally pure (same input → same output, no side effects)
- N5: neighbors doesn't mutate inputs (immutability - verified by returning new instances)

**Group G — Life rules:**
- G1: shouldLive truth table (18 cases: alive/die for counts 0-8, dead/born for counts 0-8)
- G2: oscillator periods (blinker period 2, toad period 2, beacon period 2)
- G3: still lifes are fixed points (block, beehive, loaf, boat, tub)
- G4: pentadecathlon period 15
- G5: glider translated by (1,1) after 4 generations
- G6: two-generation chain (Appendix D fixture)
- G7: extinction (single cell, 2-cell line)
- G9: purity (nextGenerationCells twice on same input yields equal results)
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

#### Program Structure Tests (ProgramTest.php)

**Group I — Programs and protocol:**
- I1: instruction identity — all 5 opcodes are distinguishable (instanceof checks)
- I2: nextGenerationProgram shape — verify emitted instructions in correct order
- I3: switchStatusVisibilityProgram shape
- I4: addPatternProgram shape
- I8: program well-formedness — every operation's program ends with Return
- I9: unknown pattern — continuation handles empty Cells correctly

**Tests NOT in core suite (shell responsibility):**
- I5: v2-only
- I6: program equivalence (v2-only)
- I7: determinism under interpretation (requires interpreter)
- I10: interpreter dispatch (requires interpreter)
- I11: full loop (requires interpreter)

These interpreter tests belong in the **shell test suite**, where the interpreter lives.

### 4.5 Running Tests

```bash
cd php/classic/core
composer install
./vendor/bin/phpunit
```

---

## 5. Deviations from Spec and PHP-Specific Choices

### 5.1 Deviation: Immutability Discipline

**Spec:** Core must be pure with no mutable state.

**PHP Implementation:** PHP lacks deep immutability guarantees. We use:
- `readonly` properties on classes (PHP 8.1+)
- Copy-on-write discipline (methods return new instances)
- No caching (avoids mutable state entirely)

**Consequence:** While PHP can't enforce deep immutability at the language level, the code follows immutability discipline. Tests verify that operations don't mutate inputs. The core has **zero mutable state** — no static variables, no global state.

### 5.2 Deviation: Cells Representation

**Spec:** `Cells` is a set with order-insensitive equality.

**PHP Implementation:** `Cells` class wrapping a PHP array (as associative array keyed by position hash).

**Rationale:** PHP doesn't have a built-in immutable set type. We implement set semantics manually.

**Consequence:**
- Uses associative array for O(1) lookups
- Implements `equals()` for order-insensitive comparison
- All operations return new `Cells` instances

### 5.3 Choice: No Packed Encoding

**Spec:** Appendix E — optional packed encoding.

**PHP Decision:** Use value-object model (`Position` class, `Cells` class).

**Rationale:** More idiomatic in PHP, type-safe, maintains structural equality.

### 5.4 Choice: Program Representation (Class Hierarchy)

**Spec:** §3.2 defines `Program ::= Finish | Step Instruction Rest`

**PHP Implementation:** Class hierarchy with `Program` abstract class and concrete subclasses for each instruction.

**Rationale:**
- Most idiomatic PHP approach
- Provides type safety through class types
- Visitor pattern enables interpretation
- Easy to test with instanceof checks

**Alternative:** Tagged array approach would be simpler but less type-safe.

### 5.5 Choice: Coordinate Type (int)

**Spec:** §2.1 — unbounded signed integers.

**PHP Implementation:** PHP `int` type.

**Rationale:**
- PHP int is platform-dependent (typically 32-bit or 64-bit)
- Range is typically ±2.1 billion (32-bit) or ±9.2 quintillion (64-bit)
- Comfortably passes G11 test (±1,000,000)
- Memory efficient

**Consequence:** Coordinates restricted to PHP int range; test G11 adapted to this bound as per spec's own guidance for packed encoding.

### 5.6 Choice: No Caching

**Spec:** §2.3 — recommends caching `neighbors` "where an idiom exists."

**PHP Implementation:** Direct computation without caching.

**Rationale:**
- Function is trivial (8 arithmetic operations)
- For web use with single generation per request, any stored results would have 0% reuse rate
- Storage overhead (hash computation + array lookup) exceeds computation cost
- In PHP-FPM, stored results would grow unboundedly (memory leak) with no benefit
- **Deliberate deviation:** PHP idiom for trivial pure functions is direct computation

**Consequence:** Function remains observationally pure; core has zero mutable state.

### 5.7 Choice: Test Framework

**Spec:** §8 — test framework requirements.

**PHP Implementation:** PHPUnit.

**Rationale:** Industry standard for PHP, full-featured, well-documented.

---

## 6. Evolution Considerations (v1 → v2)

### 6.1 What Must NOT Change (per §13.1)

- Instruction set (5 opcodes) — new classes can be added to Program hierarchy
- Program shape — class hierarchy extends naturally with new subclasses
- Life rules, neighbor function, status grammar — unchanged
- Pattern catalog — same 19 patterns
- Demo scene — same 13 stamps

### 6.2 What Will Change (per §13.2)

1. Add `Breed` type (int 0-15, validated)
2. Change `Cell` from `Position` to a class with `position: Position` and `breed: Breed`
3. Add `positionOf` method (projection)
4. Add `cross_breed` function
5. Change `Cells` to handle `Cell` objects with position-based equality
6. Add `currentPattern: string` field to Core
7. Add `withCurrentPattern`, `currentPatternOf` to Core
8. Add `defineCurrentPatternProgram` operation
9. Change `addPatternProgram` to accept `Cell` reference, add `"current"` sentinel
10. Change neighbor counting to persistent-map fold (requires implementing persistent map or using a library)

### 6.3 Design for Easy Evolution

The class hierarchy Program type makes v2 evolution straightforward:
- New instructions just add new `Program` subclasses
- Existing interpreter (in shell) gains new visit methods
- `defineCurrentPatternProgram` emits `ReturnProgram` with modified core
- No changes to existing Program classes needed

For persistent maps in v2, we could:
- Implement a simple persistent map class in PHP
- Use a functional programming library for PHP (like `php-fp`)
- Accept that PHP arrays are not persistent and use deep copying

---

## 7. Implementation Checklist

### Phase 2 (after approval)

- [ ] Create directory structure: `php/classic/core/`
- [ ] Create `composer.json`
- [ ] Create `src/Position.php`
- [ ] Create `src/Cells.php`
- [ ] Create `src/Core.php`
- [ ] Create `src/Life.php`
- [ ] Create `src/Pattern.php`
- [ ] Create `src/Program.php`
- [ ] Create `src/ReturnProgram.php`
- [ ] Create `src/ShowCellsProgram.php`
- [ ] Create `src/DisplayMessageProgram.php`
- [ ] Create `src/HideMessageProgram.php`
- [ ] Create `src/GetPatternCellsProgram.php`
- [ ] Create `src/ProgramVisitor.php`
- [ ] Create `src/CoreOperations.php`
- [ ] Create `tests/phpunit.xml`
- [ ] Create `tests/bootstrap.php`
- [ ] Create `tests/CoreTest.php`
- [ ] Create `tests/ProgramTest.php`
- [ ] Create `README.md`
- [ ] Run `composer install`
- [ ] Run `./vendor/bin/phpunit` and ensure all tests pass
- [ ] Verify compliance with spec
- [ ] Add `php/classic/core/` to git

---

## 8. Acceptance Criteria

1. All v1 core tests pass (C1, N1–N3/N5, G1–G7/G9–G11, P1–P2/P4, I1–I4/I8–I9, X1a/X2–X3/X4)
2. Code runs cleanly with no warnings (`php -l` on all files, `composer validate`)
3. **Core has zero runtime dependencies** (only Composer for development)
4. **Core contains no test doubles** (fakes, mocks, spies)
5. **Core contains no interpreter** (belongs in shell)
6. **Core contains no port interfaces** (belong in shell)
7. Core is pure: no mutable state in operations, no static storage, no global variables
8. Program type correctly encodes the protocol with visitor pattern
9. All domain types have structural equality (via `equals()` methods)
10. README documents all deviations
11. Package is properly configured as a Composer library package
12. All tests run with `./vendor/bin/phpunit`

---

## 9. Open Questions (to be resolved before Phase 2)

**None.** All prerequisite decisions have been made:
- Build tooling: Composer (decided in repo README)
- External dependencies: Zero runtime dependencies for v1
- PHP version: PHP 8.1+ required (for readonly properties)
- Caching: **None** — direct computation is faster for web use case
- Test framework: PHPUnit
- Cells representation: Custom `Cells` class with set semantics
- Program representation: Class hierarchy with visitor pattern

---

**Status:** Plan written, awaiting human review and approval.

**Approval needed:** Please review and respond with "Plan approved" or "Go ahead" before I proceed to Phase 2 — IMPLEMENT.

All design decisions have been resolved. The plan is ready for implementation.
