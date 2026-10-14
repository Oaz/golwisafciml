# Game of Life — Functional Core / Imperative Shell
## Language-Agnostistic Specification — Version 1 (Monochrome) and Version 2 (Multi-Breed)

**Audience:** implementation teams porting the program to any programming language and runtime (browser, cloud, desktop, embedded, terminal, …). Each team implements **both versions**, with Version 2 visibly an **evolution** of Version 1 — not a rewrite.
**Constraint:** implementations must be *idiomatic* in the target language. The spec supports both dynamic and statically typed targets (§9).
**Self-containment:** this document contains everything needed to implement and test the program. Appendix F (history of the original prototypes) is **not required reading** and is referenced nowhere normatively.

---

## 0. How to read this spec

The document defines a single program family with two versions:

- **Version 1 (v1) — "Monochrome Life"**: Conway's Game of Life on an unbounded grid, a status message, a full pattern catalog stampable by click, a clock-driven (or step-driven) simulation loop.
- **Version 2 (v2) — "Multi-Breed Life"**: the same program in which every live cell additionally carries a **breed** (an integer 0–15, rendered as a color per the normative palette of Appendix B). Breeds never affect survival rules; they are inherited by newborn cells through a deterministic **cross-breeding** function, and by stamped patterns through the clicked reference cell.

**The architecture is identical in both versions.** The Functional Core / Imperative Shell split, the instruction protocol, the interpreter, the status-message grammar, the Life truth table, and the shell's event wiring are **invariant**. Version 2 is Version 1 plus:

1. a `breed` dimension on cells (data, not control flow);
2. a `cross_breed` fold threaded through neighbor counting and translation;
3. one new `Core` field (`currentPattern`) and one new core operation (`defineCurrentPattern`);
4. a change of neighbor-counting *strategy* (from candidate-set filtering to a persistent-map fold) — an internal refactor with identical observable Life semantics;
5. pattern authoring (save/load/remove/clear) in the store, and breed-aware persistence.

**Mandatory implementation posture:** implement v1 first, then produce v2 by *editing* the v1 codebase. The interpreter, the instruction set, the status grammar, the Life rule, and the shell wiring must not be rewritten between versions — if they are, the implementation has failed the spec's central quality (§5, "the shell never changes"). §13.2 is the exact v1→v2 evolution checklist.

### 0.1 Normative vs reference

- **Normative:** observable, testable contracts — the Life rules, the status-message text, the instruction semantics and emission order, the pattern catalog data (Appendix A), the breed color palette (Appendix B, v2), the startup demo scene (Appendix C, both versions), the test fixtures (Appendix D), and the test suite (§11). Every implementation must satisfy these identically.
- **Reference / configuration:** data representations (value records vs packed integers), serialization formats, display geometry, tick cadence, demo scenes. Implementations choose per the guidance below and document the choice.

---

## 1. Architecture (invariant across versions)

```
            ┌────────────────────────────────────────────┐
            │  Imperative Shell (mutable, effectful)      │
            │  ports: Display, PatternStore, State,        │
            │         EventSource                          │
            │  interpret(program)                          │
            └───────────────▲────────────────────────────┘
                            │ programs (pure data)
            ┌───────────────┴────────────────────────────┐
            │  Functional Core (pure, deterministic)      │
            │  cell algebra · Life rules · patterns        │
            │  status message                              │
            │  nextGenerationProgram, addPatternProgram,   │
            │  switchStatusVisibilityProgram,              │
            │  (v2) defineCurrentPatternProgram            │
            └─────────────────────────────────────────────┘
```

**Dependency rule:** the core imports nothing from the shell; the shell imports the core. Tests target the core directly (pure, no mocks) and the interpreter with fake ports.

**The defining property:**

> **The shell never changes. The core's data and decisions grow.**

The core performs no effects. Every core operation that would need an effect *returns a program*: a data structure of effect **instructions** which the shell interprets. The shell is a fixed, generic, domain-ignorant interpreter; all domain decisions (Life rule, status visibility, pattern resolution, breed inheritance — in v2) live in the core.

Contract between the halves:

1. The shell calls a **core operation** with the current `Core` (and arguments).
2. The core returns a **program**: the next instruction to perform, plus the rest of the program, which may depend on the **response** of the previous instruction (only `GetPatternCells` produces a response).
3. The shell executes the effect through a **port**; feeds the response (if any) into the rest of the program; repeats.
4. The program always ends with `Return(core)`; executing it rebinds the shell's single mutable state reference. The core itself never mutates anything.

This shape is a free-monad / continuation-passing protocol; see §10 for an optional explicit encoding. **No threads, callbacks, or promises inside the core** — the protocol is synchronous and deterministic.

---

## 2. Domain model

### 2.1 Cells

**v1 (monochrome).** A cell is a **position**:

```
Position = (x : Int, y : Int)      -- column, row; unbounded signed integers
Cells    = a set of Position        -- no duplicates; the grid is infinite
```

**v2 (multi-breed).** A cell is a position **plus a breed**:

```
Breed = integer in 0..15            -- rendered as a color by the shell
Cell  = { position : Position, breed : Breed }
Cells = a set of Cell                -- at most one cell per position
```

Normative value-type requirements (both versions):

- `Cell`/`Position` are **immutable value types with structural equality and hashing** (record/struct/dataclass/frozen tuple), per the language's canonical idiom. Structural equality and hashability are mandatory because positions/cells are used as map keys (§2.4).
- Coordinates are **unbounded** signed integers (bounded only by the target's integer type). No wrapping, no bounds errors in the core.
- v2 defines the **breed-free projection**:

```
positionOf(cell) = cell.position      -- the "square" the cell occupies
```

  Two cells at the same position with different breeds are different cells but share a position. The neighbor-count map is keyed by *position*, never by full cell (§2.4.2). The `positionOf` concept must survive in every representation, including the optional packed encoding (Appendix E).

An optional packed-integer encoding is specified in Appendix E for constrained targets; it is never required for conformance and narrows the coordinate domain.

### 2.2 Core state

```
Core (v1) = {
  cells          : Cells,       -- live cells
  statusVisible : Bool          -- whether the status message is shown
}

Core (v2) = {
  cells          : Cells,
  statusVisible  : Bool,
  currentPattern : Text         -- name of the currently selected pattern,
}                                -- "" when none selected
```

- `initialCore` (both versions): `{ cells = [], statusVisible = false, (v2: currentPattern = "") }`. The initial visibility is `false` in both versions; whether the v2 **shell** shows the status bar at startup is shell configuration (§4.4 recommends visible in v2, hidden in v1 — a deliberate, visible evolution).
- Pure field-update constructors returning **new** immutable cores: `withCells`, `withStatusVisible`, and (v2) `withCurrentPattern`.
- Pure observers: `cellsOf`, `statusVisibleOf`, and (v2) `currentPatternOf`.

**Ordering policy (normative).** `Cells` is treated as a **set with order-insensitive equality** everywhere in this spec and its test suite. Implementations may use any internal ordering provided all equality assertions are order-insensitive and the per-implementation order is stable between equal inputs (G9, I7).

### 2.3 Life rules (invariant across versions)

- `neighbors(position) -> set of Position`: the 8 adjacent squares (Moore neighborhood): `(x±1, y±1), (x±1, y), (x, y±1)` — exactly 8, all distinct.
- `shouldLive(isAlive, liveNeighborCount) -> Bool`:
  - a **living** cell survives iff `1 < n < 4` (n = 2 or 3);
  - a **dead** cell becomes alive iff `n = 3`.

  Standard Conway rules. In v2, breeds play **no role** in survival.

**Memoization (normative where an idiom exists).** If the target language has a memoization idiom (memoized functions, `functools.lru_cache`, `Memoize` combinator, purity-preserving caches), it **must** be used for `neighbors` — the function is pure and called repeatedly with the same positions, so memoization is both safe and expected. Whatever mechanism is chosen must remain observationally pure (same inputs → same outputs, no visible state). The memo key is the **position**; the memoized value is the set of 8 neighbor positions.

### 2.4 Neighbor counting — two strategies, one semantics

This is the main internal refactor between versions. Both strategies compute, for a set of live cells, the set of positions surviving or being born; they must produce **identical position sets** on identical inputs (test G10).

#### 2.4.1 v1 — candidate-set filtering

```
allCellsAndNeighbors(cells)   = union of cells and flatMap neighbors(cell)   -- unique
nextGenerationCells(cells)    = filter allCellsAndNeighbors(cells) by
                                shouldLive(c ∈ cells, |{n ∈ cells : n ∈ neighbors(c)}|)
```

`nextGenerationCells` is literally *filter over union with a predicate* — implement it that way with map/filter/fold.

#### 2.4.2 v2 — persistent-map fold with breed threading

Build an immutable map `position -> (count, breed)` where `count` is the number of live cells having that position as a neighbor, and `breed` is the running cross-breed of the contributing cells' breeds:

```
bump(counts, position, breed):
    entry = lookup counts[position]                 -- absent if missing
    counts[position] = entry.absent ? (1, breed)
                                 : (entry.count + 1, cross_breed(entry.breed, breed))

nextGeneration(cells):
    livingPositions = { positionOf(c) : c ∈ cells }        -- set
    counts          = fold over cells of fold over memoized neighbors(positionOf(c)) of bump
    survivors       = [ (pos, entry) ∈ counts
                        where shouldLive(pos ∈ livingPositions, entry.count) ]
    result          = [ cell(pos, entry.breed) : (pos, entry) ∈ survivors ]
```

Guarantees (normative):

- **Birth breed is order-independent and total:** the cross-breed of a newborn cell is `(sum of all contributing live neighbors' breeds) mod 16`, because addition mod 16 is commutative and associative and the fold starts from the first contributor's breed (first `bump` stores `(1, breed)`). Implementations may fold in any order; the result must be the sum-mod-16 of **all** contributors (tests C4, G8). A newborn is never "unset": even with total 0 it is breed 0.
- Positions occupied by a live cell may also be counted (as neighbors of other cells); they stay in the map and are filtered by the survival rule.
- `cells` is a set: duplicate live cells at one position must not occur; behavior with duplicates is unspecified but deterministic.
- The persistent map must be the target language's **idiomatic immutable map** (§2.5).

**Why the strategy changes (for implementers):** v1's filtering recomputes neighbor membership per candidate; v2's fold accumulates counts *and* breed sums in one pass, which is what makes breed inheritance natural. Teams implement v1 with the v1 strategy and refactor in v2 — that refactor is a welcome, visible part of the evolution story, provided the position-set outputs remain identical (test G10).

### 2.5 Persistent map (v2 core; available to v1 if used by the counting strategy)

The core requires a key→value map that is **persistent (immutable)**, because neighbor counting folds over it. Use the target language's idiomatic persistent map (Haskell `Data.Map`, Scala/Clojure built-ins, F# `Map`, Rust `im`/`rpds`/sorted-map crate, OCaml `Map`, Python `pyrsistent` or documented copy-on-write, JS `Immutable.js` or equivalent, …). Do not hand-roll a tree.

Contract:

```
empty()  set(key, value, d)  get(key, d)  keys(d)  values(d)  entries(d)
```

- Immutability: inserts/updates never mutate the input map; previous versions remain valid (tested: D2, D5).
- Lookup on a missing key returns the language's idiomatic **absent representation** (`Option`/`Maybe`/nullable) — **not** a magic `0` sentinel.
- If the chosen map does not guarantee ascending iteration order, adapt the ordered assertions (D1) by comparing *sorted* key sets and document the choice.

### 2.6 Status message (invariant)

```
statusMessage(cells):
    n = |cells|
    n = 0 → "No cell is alive"
    n = 1 → "1 cell is alive"
    n > 1 → "<n> cells are alive"
```

The exact three-way text (including singular/plural) is **normative observable behavior**. Casing beyond this sentence is shell detail.

### 2.7 Cross-breeding (v2 only)

```
cross_breed(a, b) = (a + b) mod 16
```

Used in two places, both normative:

1. **Newborn cells** — folded over contributing live neighbors (§2.4.2); order-independent (§2.4.2 guarantee).
2. **Translation** — each pattern point's breed is crossed with the reference cell's breed (§2.8).

### 2.8 Pattern geometry and translation

**v1:**

```
translate(points : [Position], p : Position) -> [Position]
    = [ (x(q) + x(p), y(q) + y(p)) : q ∈ points ]
```

**v2:** translation re-centers the pattern on a **reference cell** (a clicked cell carrying the user's selected breed), and cross-breeds each point's breed with the reference breed:

```
translate(points : [Cell], reference : Cell) -> [Cell]
    = [ cell( position(q) + positionOf(reference),
              cross_breed(breed(q), breedOf(reference)) ) : q ∈ points ]
```

In both versions: translation is a **bijection on shapes** — relative positions preserved, cardinality preserved (test P4). In v1, the click position is the translation anchor; in v2 the clicked *reference cell* provides both the anchor and the breed seed. This pairing (UI breed selector → reference cell → cross-breed in translate → colored stamped pattern) is the concrete motivation for the v2 change; implementers should connect these pieces deliberately.

### 2.9 Pattern catalog (normative for both versions)

Both versions ship the **full catalog** of 19 named patterns (Appendix A lists the exact normative coordinates). Summary:

| Name | # cells | Name | # cells | Name | # cells | Name | # cells |
|---|---|---|---|---|---|---|---|
| blinker | 3 | toad | 6 | beacon | 8 | pentadecathlon | 12 |
| block | 4 | beehive | 6 | loaf | 7 | boat | 5 |
| tub | 4 | glider | 5 | lwss | 9 | mwss | 11 |
| hwss | 13 | rpentomino | 5 | diehard | 7 | acorn | 7 |
| pulsar | 48 | gosperGun | 36 | simkinGun | 36 | | |

- Patterns are lists of `(x, y)` positions (Appendix A); breed 0 in v1 (implicit) and as stored in v2.
- Exact coordinates for **pulsar**, **gosperGun** and **simkinGun** are normative fixture data (Appendix A) and must be copied verbatim into every implementation's data files / test data.
- The library must be **extensible**: patterns are data behind the PatternStore port (§4.2); adding patterns must not require touching the core.
- In v2, stored patterns may carry per-cell breeds (default 0).

---

## 3. Instruction protocol (core → shell) — invariant

### 3.1 Instruction set (closed, five opcodes)

The core communicates exclusively through this **closed** instruction set:

| Instruction | Payload | Effect for the shell |
|---|---|---|
| `ShowCells(cells)` | `Cells` | Render exactly this set of live cells (full repaint). |
| `DisplayMessage(text)` | `Text` | Show the status bar with this text. |
| `HideMessage` | — | Hide the status bar. |
| `GetPatternCells(name)` | `Text` | Read pattern `name` from the store; the resulting `Cells` (empty if unknown) is the **response** threaded to the rest of the program. |
| `Return(core)` | `Core` | Terminate; commit `core` as the new state. |

Represent instructions as a closed set — sum type, sealed interface, enum-with-payload, tagged tuple — per the language idiom. The interpreter must dispatch on **instruction identity**, never on booleans/integers "for speed"; instruction identity must remain a meaningful testable property.

### 3.2 Program shape (idiomatic statement)

A program is a **pure value**:

```
Program ::= Finish (core : Core)
          | Step (instruction : Instruction, rest : Rest)

Rest ::= Next (program : Program)                        -- independent of any response
       | With (continuation : Response -> Program)       -- consumes the previous
                                                        -- instruction's response
```

- `Finish` is the encoding of "ends with `Return`"; it is the only terminator, and no instruction may follow it.
- Only `GetPatternCells` has a response (`Cells`); only its `Rest` is a `With` continuation. `ShowCells`, `DisplayMessage`, `HideMessage` have no response.
- In a lazy language this is simply a lazy list with `GetPatternCells` carrying its continuation (or a free/operational monad, §10). In a strict language use an explicit recursive type, a pair-with-optional-continuation, or a generator — **no threads, callbacks, or promises in the core**.
- Well-formedness: every operation's program terminates in `Finish`/`Return` (test I8). In dynamic languages, the shell may fail fast on a program ending without `Return` in debug/test builds; in static languages the type should make the violation unrepresentable (§9.2).
- Continuations must be **pure closures over core data** — they may capture core values, never shell state; invoking a continuation twice with the same response yields equal programs (test I8).

### 3.3 Core operations producing programs (all pure)

All take the current `Core` (plus arguments) and return a `Program`. Emission order is **normative**.

**`nextGenerationProgram(core)`** (both versions)

1. `newCells = nextGeneration(cellsOf(core))`
2. emit `ShowCells(newCells)`
3. if `statusVisibleOf(core)` then emit `DisplayMessage(statusMessage(newCells))`
4. emit `Return(withCells(core, newCells))`

**`addPatternProgram(core, patternName, reference)`**

- v1: `reference` is a `Position`.
- v2: `reference` is a `Cell` (the clicked reference cell, carrying the user's selected breed).

1. `resolvedName = (v2, patternName == "current") ? currentPatternOf(core) : patternName`
2. emit `GetPatternCells(resolvedName)`, with a continuation receiving `patternCells`
3. `newCells = cellsOf(core) ∪ translate(patternCells, reference)`
4. emit `ShowCells(newCells)`
5. if `statusVisibleOf(core)` then emit `DisplayMessage(statusMessage(newCells))`
6. emit `Return(withCells(core, newCells))`

Notes:

- **v2 sentinel `"current"`**: the core resolves it to the stored **name** of the currently selected pattern *at program-construction time*, then emits `GetPatternCells` with that name — the store **is** consulted (test I5). If `currentPatternOf(core)` is `""`, the store reports an empty pattern and the world is unchanged.
- The resolution of `"current"` is a **core decision**; the shell never resolves names.
- Unknown pattern name → the store reports empty `Cells`; `addPattern` then simply re-shows the unchanged cells and returns an unchanged-content core. **This must not crash** (test I9). The core is total; the shell decides what a missing pattern means to the user (ignore, log, message).

**`switchStatusVisibilityProgram(core)`** (both versions)

- if visible: emit `HideMessage`, then `Return(withStatusVisible(core, false))`
- if hidden: emit `DisplayMessage(statusMessage(cellsOf(core)))`, then `Return(withStatusVisible(core, true))`

**`defineCurrentPatternProgram(core, name)`** (v2 only)

- emit `Return(withCurrentPattern(core, name))`

Observations:

- The Show/Display logic is shared-shaped across `nextGeneration` and `addPattern`; a shared pure helper ("emit view for new cells given core") is allowed and encouraged, as long as observable output is identical.
- The conditional `DisplayMessage` emission driven by `statusVisible` is a **core decision**, not a shell decision. The shell blindly shows whatever `DisplayMessage` says.
- Do not introduce additional instructions beyond the five, in either version.

---

## 4. Imperative Shell — invariant structure, per-version configuration

The shell is generic and domain-ignorant: it interprets programs and owns the mutable state.

### 4.1 State

- `currentCore : Core` — a single mutable reference, initialized to `initialCore` at startup (then optionally adjusted by startup configuration, §4.4). The **only mutation point of the whole program** is `Return`.

### 4.2 Ports (injected, not global)

| Port | v1 operations | v2 additions |
|---|---|---|
| `Display` | `render(cells)` (full repaint), `show(text)`, `hide()` | same (renders each cell's breed as the color of Appendix B) |
| `PatternStore` | `get(name) -> Cells` (empty when unknown); optional `list()` | `save(name, cells)`, `remove(name)`, `clear()`, `names()` (sorted) — authoring |
| `EventSource` | tick (clock or manual step), click at (x,y), toggle-status key, select-pattern | plus select-breed |
| `State` | the mutable `currentCore` reference | same |

Typical adapters: DOM/canvas, terminal cells, ASCII, headless no-op for Display; DOM element / terminal line / log for the status bar; localStorage, file, database, in-memory map, flash/EEPROM record for the store; timers, GUI events, buttons, test harness for the event source.

### 4.3 Interpreter

```
interpret(program):
    case program of
      Finish(core)      -> currentCore := core; done
      Step(instr, rest) ->
        case instr of
          ShowCells(cs)    -> display.render(cs);            interpret(rest.next())
          DisplayMessage(t)-> display.show(t);               interpret(rest.next())
          HideMessage      -> display.hide();                interpret(rest.next())
          GetPatternCells(n)-> resp = patternStore.get(n)    -- Cells, [] when unknown
                               interpret(rest.with(resp))
```

- Must be tail-recursive, looped, or trampolined — no stack growth.
- The interpreter must not inspect payloads beyond dispatch and forwarding.
- **Batching:** render **once per program** (or observe only the final display state), not per instruction — the display refreshes atomically at the end of each interpreted program.
- The interpreter, this structure, and this code are **identical in v1 and v2**. Version 2 must not touch it.

### 4.4 Event loop wiring

On startup:

1. `currentCore = initialCore`, then apply startup visibility configuration: v1 leaves the status bar hidden; **v2 applies `withStatusVisible(core, true)`** — the status bar starts visible. This is deliberate shell configuration that makes the v2 evolution visible (test X1b).
2. Initialize the pattern repository with the §2.9 catalog. **The seeding policy (overwrite always vs seed only missing names) is implementation-defined** — pick one, protect or refresh user saves accordingly, and document it.
3. Seed the normative demo scene (Appendix C), then render the initial cells.

User intents → core calls (**this mapping is the shell contract**):

| User intent | v1 | v2 |
|---|---|---|
| **Select pattern** in the UI pattern selector | shell configuration (used at click time) | **core state**: `interpret(defineCurrentPatternProgram(core, name))` |
| **Select breed/color** | n/a — monochrome | shell configuration; the selected breed is used at click time to build the reference cell |
| **Click/tap grid** at (x, y) | `interpret(addPatternProgram(core, selectedPatternName, (x,y)))` | `interpret(addPatternProgram(core, "current", cell(x, y, selectedBreed)))` |
| **Tick** — autonomous clock (forever loop with configurable wait) or manual step input | `interpret(nextGenerationProgram(core))` | same |
| **Toggle status bar** | `interpret(switchStatusVisibilityProgram(core))` | same |
| Save/load/remove pattern | n/a (read-only store) | store authoring ports, then refresh the pattern list |

Two points to implement deliberately (this is where the real v2 evolution lives in the UI):

- **The pattern selector exists from v1.** In v1 it is pure shell configuration consulted at click time. In v2, the *pattern selection* is **promoted to core state** (`currentPattern` field + `defineCurrentPattern` operation), and the click handler passes the `"current"` sentinel — the shell no longer consults its own configuration at click time; the core resolves the stored name. Meanwhile the *breed selection stays shell-side*: the selected breed only matters at click time when the reference cell is constructed. This asymmetry is deliberate; do **not** "symmetrize" by moving breed selection into the core.
- **The breed selector is the v2 UI addition.** Its only contract with the core is the reference cell: click → `cell(x, y, selectedBreed)` → `translate` cross-breeds each pattern point with it (§2.8). Connect selector, reference cell, cross-breed and rendering color as one explained pipeline.

**Clock vs step (invariant, from v1):** the shell supports **two interchangeable tick configurations from v1 onward**: (a) an autonomous clock — a forever loop `repeat { interpret(nextGeneration); wait(interval) }` — and (b) a manual step input (button/key) advancing one generation per activation. Both map to the same intent, *advance one generation*, and **nothing about this changes in v2**. The interval and the choice of configuration are shell configuration (reference values in Appendix F: 0.1 s for v1, 0.2 s for v2).

**Demo scene (normative, both versions):** every implementation pre-seeds the **same demo world** at startup so the user immediately sees life — this is no longer optional. The exact 13 stamps (pattern, reference position, reference breed) are normative data in Appendix C and must be applied verbatim. In v2 each stamp's cells carry the listed breed (pattern cells are breed 0, cross-bred with the reference breed, so the stamped cells carry the reference breed — §2.8). In v1 the same 13 stamps are placed at the same positions and the breed column is simply ignored (monochrome). Startup ordering: apply the demo stamps after `initialCore` and the §4.4 visibility configuration, before the initial render; use the store-backed catalog patterns. A port may not change, drop, or reorder the demo scene (test X4).

### 4.5 Display geometry, palette, persistence format

Display geometry (grid size, cell size, screen mapping) and the tick interval are **shell configuration**; reference values from the original prototypes are recorded in Appendix F purely as examples. **The breed color palette is normative (v2):** every v2 implementation must render each breed with the exact RGB color of Appendix B; only incidental rendering details (antialiasing, dead-cell background, cell borders) are free. The core does not know about bounds or colors; only the renderer does. Ports may crop or clip for display without affecting core state. Serialization format is likewise free — see §7.

---

## 5. Required qualities (both versions)

1. **Purity of the core.** No core function reads the clock, environment, store, or any global. Same inputs → same outputs, always. (Memoization of `neighbors` must be observationally pure.)
2. **Determinism.** Given the same event sequence and pattern store content, the sequence of instructions is fully determined — including, in v2, newborn breeds (sum mod 16 over all contributors is order-independent by construction).
3. **Single mutation point.** Only `Return` writes state, and only in the shell.
4. **Replaceable adapters.** All effects behind the ports; the core + interpreter run unchanged on any platform, including a headless test harness whose ports are recorders.
5. **Totality of core functions.** Core functions are defined for all inputs (unbounded coordinates, empty cell sets, unknown pattern names handled by the protocol, not by exceptions in the core).
6. **Extensibility.** New patterns are data; new instructions and new core operations are additive changes that a static type system should surface exhaustively (§9.2).
7. **Evolution discipline.** v2 is obtained by editing v1: the instruction set, interpreter, status grammar, Life rule and shell wiring are untouched; the diff is confined to §13.2's checklist.

---

## 6. Functional Core summary per version

| Concept | v1 | v2 |
|---|---|---|
| Cell | `Position (x, y)` | `{position, breed}`, `positionOf` projection |
| Breed | n/a | `0..15`, `cross_breed(a,b) = (a+b) mod 16` |
| Core | `{cells, statusVisible}` | `{cells, statusVisible, currentPattern}` |
| Neighbors | 8 Moore neighbors, memoized by position | same |
| Neighbor counting | candidate-set filtering (§2.4.1) | persistent-map fold with `(count, breed)` entries (§2.4.2) |
| nextGeneration | pure, standard rules | pure, standard rules + breed inheritance (sum mod 16, all contributors) |
| translate | offset addition | re-center + cross-breed with reference cell |
| Status message | three-way grammar | same |
| Programs | nextGeneration, addPattern, switchStatusVisibility | + defineCurrentPattern; addPattern accepts `"current"` |
| Pattern catalog | 19 patterns (Appendix A), positions | same + per-cell breeds (default 0) |
| Store | read-only `get` | + authoring (save/remove/clear/names) |
| Persistence format | positions | positions + breed |
| Cell rendering | monochrome | breed → color per Appendix B |
| Startup demo scene | 13 stamps, Appendix C (breeds ignored) | same 13 stamps, Appendix C (with breeds) |
| Startup status bar | hidden | visible |

---

## 7. Persistence format (store contract — format-free)

The store persists named patterns. The **format is the implementation's choice** (JSON, language-native serialization, compact binary, fixed-width records, etc.) — pick the encoding that suits the target environment (e.g. embedded targets need not use JSON). The normative contract:

- save→load round-trips losslessly (v2: breed included; test P5);
- pattern names are enumerable in **ascending order** (test P5);
- a corrupt or missing entry loads as an **empty pattern** (absent key → empty cells list; test P5);
- the format is documented in the port's README.

---

## 8. Test framework requirements

Ship the test suite in the language's native xUnit runner if one exists, **provided** the same tests exist with equivalent semantics. If building a minimal custom runner, retain this shape:

```
Test(title, given: setup, assertions: [assertion-results])
assertThat(actual).isEqualTo(x) | isGreaterThan(x) | isLowerThan(x) | is(bool)
Run(tests) → per test: title, total, passed, failed,
              failed assertions carry [actual, expected]
A suite passes when failed = 0 for every test.
```

Keep two structural ideas: the **Given/When/Then compositional style** (parameterized contexts combined with shared assertions — e.g. one `Given` feeding several `Test` cases), and assertions that compare **whole programs and whole Cores**, not incidental fragments. Test execution is deterministic and isolated; each test gets fresh state.

---

## 9. Static vs dynamic typing

Both styles are first-class targets; each changes *where* correctness is enforced, not the architecture.

### 9.1 Dynamically typed languages (Python, JS, Clojure, Ruby, Smalltalk, Elixir, Lua…)

- Instructions are tagged data: keyword maps (`{:show-cells cells}`), tagged tuples (`{:display_message, text}`) — one canonical tag per opcode, kept in one place (a module of constants) so typos cannot fork the protocol. A defensive `else` in the interpreter fails loudly on unknown tags.
- The Core record is a dictionary/object; tests assert structural equality directly.
- **Immutability is convention, not enforcement** (except where the language offers it): the immutability tests (D2, N5, G9, X2) are not optional — they are the primary guard against accidental shared-structure bugs.
- Contracts at boundaries: validate/normalize at the PatternStore edge (unknown name → empty set, malformed stored data → empty set + log); assert instruction payload shapes in debug builds.
- Equality discipline: this spec is uniformly **order-insensitive set equality** (§2.2); be explicit where a test compares whole programs (order of instructions matters — that's a sequence, not a set).
- Numeric pitfalls: integer arithmetic; if you adopt the optional packed encoding (Appendix E), JS **must** use BigInt or re-encode with smaller ranges (the reference encoding exceeds `Number.MAX_SAFE_INTEGER`).

### 9.2 Statically typed languages (Haskell, OCaml, Rust, F#, Scala, TypeScript, Swift, Kotlin…)

- Model the domain as **ADTs**. `Core` as a record; `Program` as a recursive type whose only terminator is `Finish`/`Return` (a type-level guarantee that no instruction follows `Return` — an entire protocol-error class disappears).
- `Position` and `Cell` as distinct value types (not raw tuples of ints) with structural equality/hashing, to prevent column/row mix-ups; v2's `Breed` as a bounded newtype with a smart constructor (mod 16).
- The interpreter becomes **exhaustively checkable**: adding a new instruction breaks every interpreter at compile time — the mechanical version of "the shell never changes".
- Response threading typed: only `GetPatternCells`'s continuation receives `Cells`. If the closure-typing bothers you, use the free monad encoding (§10).
- Option/Result at the **port** boundary (store misses, absent map keys); never exceptions in the core.
- The v1→v2 refactor should show up as additive type changes (`Cell` gains a field; `Core` gains a field; one new constructor/operation), not as rewrites of the program or interpreter types.

---

## 10. Optional refinement: the free monad encoding (static typing only)

The §3.2 program shape is a hand-rolled free monad. Languages with higher-kinded types may make it explicit:

```
data CoreOp next
  = ShowCells      Cells next
  | DisplayMessage Text  next
  | HideMessage          next
  | GetPatternCells Name (Cells -> next)
  | ReturnCore     Core  next

type Program a = Free CoreOp a
```

What it buys: "`Return` is last" becomes unrepresentable-to-violate; response threading is typed; adding an instruction compiler-flags every interpreter; multiple interpreters (GUI, headless recorder, replay) come free. **Equivalence requirement:** a free-monad implementation must produce, for the same core inputs, the same sequence of instructions as the list/ADT form (test I6). Do not introduce additional instructions.

Skip it when the instruction set is stable at five opcodes and there is exactly one interpreter — the plain recursive type + loop already delivers exhaustive dispatch. Stack safety: use a church-encoded freer/operational monad, `Codensity`, or an explicit interpreter loop from day one. In languages without HKTs, the sealed-enum + loop **is** the free monad by hand, and is fully conforming.

---

## 11. Test suite (normative)

Implementations ship all tests below, marked per version. Core + protocol tests (groups C, N, G, P, I1–I9) must pass with **zero mocks of core functions** (only the PatternStore port is faked); shell tests use recorder/spy ports. Core + protocol tests alone must certify the game semantics.

### Group D — Persistent map (v2; also v1 if the map-based counting is used)

| # | Test |
|---|---|
| D1 | **map basics** — build `d` with entries 25→["a",44], 78→["b",23]; get both; `keys` ascending; values and entries round-trip. |
| D2 | **persistence** — after any subsequent `set`, the original map still satisfies D1 unchanged. |
| D3 | **absent key** — lookup of a missing key returns the absent representation, never a magic value. |
| D4 | **update existing key** — overwriting a key changes its value, leaves other keys and the original map untouched. |
| D5 | **insertion-order independence** — keys inserted ascending vs descending vs random yield the same key set. |
| D6 | **map at scale** — 1000 sequential keys all retrievable; key set complete. |

### Group C — Cell algebra

| # | Version | Test |
|---|---|---|
| C1 | both | **construction/accessor round trip** — `x`, `y` ∈ {-500000, -1, 0, 1, 499999} (v2: and breed ∈ {0, 15}): accessors return exactly the stored values, negative coordinates included. Concretely: `xOf(cell(223, 481, b=3)) = 223`, `yOf(cell(223, 481, b=4)) = 481`, `xOf(cell(-223, -481, b=5)) = -223`, `yOf(cell(-223, -481, b=6)) = -481`. |
| C2 | v2 | **structural equality** — equal components ⇒ equal cells/hash; different breed ⇒ different cell. |
| C3 | v2 | **position drops breed** — `positionOf(cell(p, 0)) == positionOf(cell(p, 15))`; positions of distinct squares differ. |
| C4 | v2 | **cross breed** — `(0,0)→0`; `(8,9)→1`; `(15,15)→14`; commutative; folding [3,5,9,12] in any order gives the same result. |

### Group N — Neighbors and counting

| # | Test |
|---|---|
| N1 | **neighbors** — exactly 8, all distinct, all within Chebyshev distance 1 of the input. |
| N2 | **memoized neighbors** — repeated calls return equal results; observationally pure. |
| N3 | **count lookup** — from counts built as `{(4,8)→(2,0), (5,2)→(3,0), (9,1)→(4,0)}`: count of (5,2) is 3, of (9,1) is 4, of (1,1) is 0 (v2 form; v1 analogously with counts only). |
| N4 | **bump (v2)** — bumping an existing entry increments the count and cross-breeds: from the N3 map, `bump(counts, (5,2), 0)` yields `(4, ·)`; `bump(counts, (9,1), 0)` yields `(5, ·)`; `bump(counts, (1,1), 0)` yields `(1, 0)` at a fresh position. `bump(bump(counts, p, 5), p, 9)` at fresh p ⇒ count 2, breed `cross_breed(5,9)` = 14. |
| N5 | **bump immutability** — after any bump, the original counts map is unchanged. |

### Group G — Life rules

| # | Test |
|---|---|
| G1 | **should-live truth table** — living survives for n ∈ {2,3} only, dies for n ∈ {0,1,4,5,8}; dead born only for n = 3 (9×2 cases). |
| G2 | **oscillator periods** — blinker: vertical ⇒ horizontal ⇒ vertical (period 2); likewise toad and beacon. |
| G3 | **still lifes** — block, beehive, loaf, boat, tub are fixed points of nextGeneration. |
| G4 | **pentadecathlon period 15** — `nextGeneration^15` returns the original set (pins the rule and the catalog data hard). |
| G5 | **glider advances** — gen 4 of a glider = glider translated by (1,1), same relative shape; (v2) same breed multiset. |
| G6 | **fixed fixture, two-generation chain** — the fixture of Appendix D: given the 31-cell input (breed 0), `sorted(nextGeneration(input))` equals the expected generation-1 list; and `sorted(nextGeneration(gen1))` equals the expected generation-2 list. Copy the fixture verbatim. |
| G7 | **extinction** — single cell and 2-cell line both yield [] after one generation; `statusMessage([])` consistent. |
| G8 | **birth breed (v2)** — three cells of breeds 4, 9, 2 adjacent to one dead square ⇒ newborn breed = (4+9+2) mod 16 = 15; and folding the contributors in any order yields the same breed. |
| G9 | **purity** — nextGeneration twice on the same input: equal results, input not mutated (deep compare). |
| G10 | **strategy equivalence** — the v1 filtering strategy and the v2 map-fold strategy produce identical **position sets** on identical inputs (run both if both exist; otherwise test the shipped strategy against the standard fixtures G2–G6). |
| G11 | **unboundedness** — cells at ±10⁶ (or beyond the packed range, if Appendix E is used) behave identically to small ones; no wrapping, no bounds errors. |
| G12 | **property-based (where supported)** — for arbitrary finite cell sets: candidates ⊇ cells; neighbor symmetry (x ∈ neighbors(y) ⇔ y ∈ neighbors(x)); output ⊆ candidates; status count == \|cells\|. |

### Group P — Patterns, translation, status

| # | Test |
|---|---|
| P1 | **status grammar** — [] → "No cell is alive"; 1 cell → "1 cell is alive"; 3 cells → "3 cells are alive"; n ≥ 2 → "<n> cells are alive". |
| P2 | **translate (v1)** — `translate([(0,-1),(0,0),(0,1)], (10,3)) == {(10,2),(10,3),(10,4)}`. |
| P3 | **translate re-centers (v2)** — `translate(block, cell(10,20,7))` = block at (10,20),(11,20),(10,21),(11,21). |
| P4 | **translate cross-breeds (v2)** — breed-0 point onto breed-3 center ⇒ breed 3; relative positions and cardinality preserved (bijection on shapes). |
| P5 | **store round trip** — save then load returns the same cells (v2: incl. breed); unknown name → empty; corrupt entry → empty; `names()` sorted and complete. |
| P6 | **catalog integrity** — all 19 names load with the cardinalities of §2.9. |
| P7 | **stamp composition** — stamping A then B yields the same cell set as B then A; stamping the same pattern twice at the same spot is idempotent (set semantics). |
| P8 | **breed palette (v2)** — the shell's Display renders each breed 0–15 with the exact RGB color of Appendix B (verified with a recorder port that asserts the mapping data). |

### Group I — Programs and interpreter (fake ports: recording display, in-memory store, captured state)

| # | Test |
|---|---|
| I1 | **instruction identity** — the five opcodes are pairwise distinguishable values/constructors (in static languages: an equality test on constructors). |
| I2 | **nextGeneration program shape** — status-invisible core ⇒ `[ShowCells(new), Return(core')]` in order; visible core ⇒ additionally `DisplayMessage(statusMessage(new))` before Return. |
| I3 | **switch status visibility** — visible ⇒ program starts HideMessage, returns statusVisible=false; invisible ⇒ starts DisplayMessage, returns true. |
| I4 | **add pattern** — program emits GetPatternCells(name); interpretation renders old cells ∪ translated pattern and commits them; visible-status variant includes the message. |
| I5 | **add "current" (v2)** — with `currentPatternOf(core) = "toad"`, the program's first instruction is `GetPatternCells("toad")` (the name is resolved from core state at construction time); with `currentPatternOf(core) = ""` the store returns empty and the world is unchanged. |
| I6 | **program equivalence** — (if both encodings exist) free-monad and list/ADT representations produce identical instruction sequences under a collecting interpreter. |
| I7 | **determinism under interpretation** — interpreting a program twice from the same state yields identical port traces and identical final states. |
| I8 | **program well-formedness** — every operation's program ends with Return/Finish; nothing follows it; continuations are pure (double invocation with the same response yields equal programs — primarily for dynamic languages). |
| I9 | **unknown pattern** — addPattern with a name absent from the store: unchanged cells in ShowCells, equal-content Return core, no error. |
| I10 | **interpreter dispatch** — with recorder ports: ShowCells ⇒ exactly one render with identical cells; message instructions ⇒ one status call each; GetPatternCells ⇒ one store lookup, response reaches the continuation; Return ⇒ state reference updated, nothing further interpreted. |
| I11 | **full loop** — seed demo + N ticks with a deterministic recorder: the recorded ShowCells sequence for the first ticks shows the blinker alternating vertical/horizontal (end-to-end wiring without a GUI). |

### Group X — Cross-cutting

| # | Test |
|---|---|
| X1a | **core constructor defaults** — initial core: no cells, no status message, (v2) empty current pattern. |
| X1b | **startup visibility** — after the shell's startup wiring, the status bar is hidden in v1 and visible in v2 (first interpreted program of v2 startup emits DisplayMessage if cells exist; switching from hidden yields DisplayMessage per I3). |
| X2 | **field updates are non-destructive** — every `with*` update leaves the original core and the original collections untouched (deep compare). |
| X3 | **catalog cardinalities** — the 19 values of §2.9. |
| X4 | **startup demo scene** — after startup wiring, the world contains exactly the cells produced by the 13 Appendix C stamps (v1: positions only; v2: including the listed breeds, via cross-breeding the breed-0 catalog patterns with the reference breeds). Same scene in both versions. |

**Acceptance criterion:** the full suite, unchanged in its structure, must pass **after the v1→v2 evolution** with the v2 additions (C2–C4, N4, G8, P3, P4, P8, I5) green — that double pass is the mechanical proof that v2 is an evolution, not a rewrite.

---

## 12. Deliverables per port (conformance checklist)

1. **Core module(s)**, both versions: value types (§2), pure functions (§2.3–2.8), program type (§3.2), core operations (§3.3).
2. **Shell module**, both versions: state reference, interpreter (§4.3), event wiring (§4.4).
3. **Adapters**: Display, PatternStore, EventSource, State — at least one working set per platform; a headless recorder set for tests.
4. **Pattern data**: the 19 reference patterns (Appendix A), names exact, coordinates verbatim; stored via the PatternStore or compiled in.
5. **Palette data (v2)**: the 16 breed→RGB colors of Appendix B, verbatim.
6. **Demo scene data**: the 13 stamps of Appendix C, verbatim, applied at startup (both versions).
7. **Test suite** (§11), all green, in both versions (v1 runs the applicable subset; v2 runs all), including the Appendix D fixture verbatim.
8. **README** documenting: the v1→v2 diff as applied to this codebase; deviations (ordering policy of §2.2, seeding policy of §4.4, cadence, geometry, serialization format, memoization and map idioms chosen); whether the optional packed encoding (Appendix E) is used and with what coordinate domain.

---

## 13. The evolution checklist (v1 → v2)

### 13.1 What must NOT change

- The instruction set (five opcodes), the program shape, the interpreter, and the shell wiring (§4.4 rows "Tick" and "Toggle status"; clock-vs-step available from v1).
- The Life truth table, the neighbor function (including memoization), the status-message grammar.
- The pattern catalog (19 patterns already in v1; v2 only adds optional per-cell breeds).
- The startup demo scene (the 13 stamps of Appendix C are identical in both versions; only the breeds — ignored in v1 — distinguish them).
- The architecture: pure core, dumb shell, single mutation point, ports, test framework.

### 13.2 What changes (the visible evolution)

1. **Cell gains a breed**: `Position` → `{position, breed}` + `positionOf` projection + `Breed` bounded type.
2. **Cross-breeding**: `cross_breed(a,b) = (a+b) mod 16`, threaded through (a) newborn cells (sum over all contributors, order-independent) and (b) translation.
3. **Neighbor counting strategy**: candidate-set filtering → persistent-map fold with `(count, breed)` entries (identical observable position sets, test G10).
4. **Core state**: + `currentPattern` field, `withCurrentPattern`, `currentPatternOf`.
5. **New core operation**: `defineCurrentPatternProgram` (pattern selection promoted from shell config to core state; clicks pass the `"current"` sentinel resolved from core state).
6. **addPattern**: reference is now a bred cell; the `"current"` sentinel resolves to the stored pattern name at construction time.
7. **Store**: read-only → authoring (save/remove/clear/names); persistence format carries breed; seeding policy is implementation-defined.
8. **UI and rendering**: + breed/color selector rendered with the normative palette of Appendix B; the reference-cell pipeline (selector → clicked cell → cross-breed → color) is explained to the user. Startup shows the status bar.
9. **Tests**: add C2–C4, N4, G8, P3, P4, P8, I5, X1b; X4 (demo scene) applies to both versions and must pass before and after the evolution; everything else keeps passing unmodified.

If your v2 diff touches anything in §13.1, treat it as an implementation error, not an evolution.

---

## Appendix A — Pattern catalog (normative coordinates)

Breed 0 unless stated.

| Name           | Cells (x, y)                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| blinker        | (-1,0), (0,0), (1,0)                                                                                                                                                                                                                                                                                                                                                                                                                              |
| toad           | (0,0), (1,0), (2,0), (-1,1), (0,1), (1,1)                                                                                                                                                                                                                                                                                                                                                                                                         |
| beacon         | (-1,-1), (0,-1), (-1,0), (0,0), (1,1), (2,1), (1,2), (2,2)                                                                                                                                                                                                                                                                                                                                                                                        |
| pentadecathlon | (-2,-1), (3,-1), (-4,0), (-3,0), (-1,0), (0,0), (1,0), (2,0), (4,0), (5,0), (-2,1), (3,1)                                                                                                                                                                                                                                                                                                                                                         |
| block          | (0,0), (1,0), (0,1), (1,1)                                                                                                                                                                                                                                                                                                                                                                                                                        |
| beehive        | (0,-1), (1,-1), (-1,0), (2,0), (0,1), (1,1)                                                                                                                                                                                                                                                                                                                                                                                                       |
| loaf           | (0,-1), (1,-1), (-1,0), (2,0), (0,1), (2,1), (1,2)                                                                                                                                                                                                                                                                                                                                                                                               |
| boat           | (-1,-1), (0,-1), (-1,0), (1,0), (0,1)                                                                                                                                                                                                                                                                                                                                                                                                             |
| tub            | (0,-1), (-1,0), (1,0), (0,1)                                                                                                                                                                                                                                                                                                                                                                                                                      |
| glider         | (0,-1), (1,0), (-1,1), (0,1), (1,1)                                                                                                                                                                                                                                                                                                                                                                                                               |
| lwss           | (-1,-1), (2,-1), (-2,0), (-2,1), (2,1), (-2,2), (-1,2), (0,2), (1,2)                                                                                                                                                                                                                                                                                                                                                                              |
| mwss           | (1,-2), (-1,-1), (3,-1), (-2,0), (-2,1), (3,1), (-2,2), (-1,2), (0,2), (1,2), (2,2)                                                                                                                                                                                                                                                                                                                                                               |
| hwss           | (0,-2), (1,-2), (-2,-1), (3,-1), (-3,0), (-3,1), (3,1), (-3,2), (-2,2), (-1,2), (0,2), (1,2), (2,2)                                                                                                                                                                                                                                                                                                                                               |
| rpentomino     | (0,-1), (1,-1), (-1,0), (0,0), (0,1)                                                                                                                                                                                                                                                                                                                                                                                                              |
| diehard        | (3,-1), (-3,0), (-2,0), (-2,1), (2,1), (3,1), (4,1)                                                                                                                                                                                                                                                                                                                                                                                               |
| acorn          | (-2,-1), (0,0), (-3,1), (-2,1), (1,1), (2,1), (3,1)                                                                                                                                                                                                                                                                                                                                                                                               |
| pulsar         | (-4,-6), (-3,-6), (-2,-6), (2,-6), (3,-6), (4,-6),<br>(-6,-4), (-1,-4), (1,-4), (6,-4),<br>(-6,-3), (-1,-3), (1,-3), (6,-3),<br>(-6,-2), (-1,-2), (1,-2), (6,-2),<br>(-4,-1), (-3,-1), (-2,-1), (2,-1), (3,-1), (4,-1),<br>(-4, 1), (-3, 1), (-2, 1), (2, 1), (3, 1), (4, 1),<br>(-6, 2), (-1, 2), (1, 2), (6, 2),<br>(-6, 3), (-1, 3), (1, 3), (6, 3),<br>(-6, 4), (-1, 4), (1, 4), (6, 4),<br>(-4, 6), (-3, 6), (-2, 6), (2, 6), (3, 6), (4, 6) |
| gosperGun      | (7,-4), (5,-3), (7,-3),<br>(-5,-2), (-4,-2), (3,-2), (4,-2), (17,-2), (18,-2),<br>(-6,-1), (-2,-1), (3,-1), (4,-1), (17,-1), (18,-1),<br>(-17,0), (-16,0), (-7,0), (-1,0), (3,0), (4,0),<br>(-17,1), (-16,1), (-7,1), (-3,1), (-1,1), (0,1), (5,1), (7,1),<br>(-7,2), (-1,2), (7,2),<br>(-6,3), (-2,3), (-5,4), (-4,4)                                                                                                                            |
| simkinGun      | (-16,-10), (-15,-10), (-9,-10), (-8,-10),<br>(-16, -9), (-15, -9), (-9, -9), (-8, -9),<br>(-12,-7), (-11,-7), (-12,-6), (-11,-6),<br>(6,-1), (7,-1), (9,-1), (10,-1),<br>(5,0), (11,0),<br>(5,1), (12,1), (15,1), (16,1),<br>(5,2), (6,2), (7,2), (11,2), (15,2), (16,2), (10,3),<br>(4,7), (5,7), (4,8), (5,9), (6,9), (7,9), (7,10)                                                                                                             |

---

## Appendix B — Breed color palette (normative, v2)

Every v2 implementation must render live cells with **exactly these RGB colors**, one per breed. Colors are copied verbatim into each implementation's rendering data.

| Breed | RGB (r, g, b) | Breed | RGB (r, g, b) |
|---|---|---|---|
| 0 | (127, 127, 127) | 8 | (0, 255, 0) |
| 1 | (127, 0, 0) | 9 | (190, 63, 255) |
| 2 | (0, 127, 0) | 10 | (0, 255, 255) |
| 3 | (0, 0, 127) | 11 | (190, 255, 63) |
| 4 | (255, 0, 0) | 12 | (0, 0, 255) |
| 5 | (255, 255, 0) | 13 | (255, 63, 190) |
| 6 | (63, 190, 255) | 14 | (255, 190, 63) |
| 7 | (63, 255, 190) | 15 | (255, 0, 255) |

Normative requirements:

- The mapping breed → RGB is fixed and identical across all ports; a port may not substitute, permute, or derive its own palette (test P8).
- The mapping lives in the shell/renderer (or rendering data), never in the core: the core knows breeds only as integers 0–15 (§2.1).
- Dead cells, background, borders, and other rendering details remain shell configuration (§4.5).
- Color-space conversions (e.g. a terminal with a 16-color palette) must approximate these RGB values as closely as the target allows and document the approximation.

---

## Appendix C — Startup demo scene (normative, both versions)

Every implementation seeds the **same demo world** at startup. The scene is a list of 13 stamps: a catalog pattern (Appendix A), translated to a reference position, with a reference breed (v2). Copy verbatim.

| # | Pattern | Reference position (x, y) | Reference breed (v2) |
|---|---|---|---|
| 1 | hwss | (33, 20) | 1 |
| 2 | rpentomino | (35, 10) | 2 |
| 3 | pentadecathlon | (25, 30) | 3 |
| 4 | blinker | (25, 10) | 4 |
| 5 | pentadecathlon | (20, 15) | 5 |
| 6 | mwss | (40, 15) | 6 |
| 7 | glider | (13, 13) | 7 |
| 8 | toad | (30, 15) | 8 |
| 9 | gosperGun | (12, 10) | 9 |
| 10 | pulsar | (13, 24) | 10 |
| 11 | acorn | (25, 25) | 11 |
| 12 | beacon | (25, 20) | 12 |
| 13 | pulsar | (36, 30) | 13 |

Normative requirements:

- **Both versions stamp the same 13 patterns at the same reference positions.** In v1 the breed column is ignored (monochrome rendering); in v2 it is applied.
- In v2, each stamp's cells carry the listed reference breed: catalog patterns are breed 0, and `cross_breed(0, b) = b` (§2.7), so stamping through the reference cell yields the breed of the stamp (§2.8).
- Seeding happens at startup, after `initialCore` and the visibility configuration (§4.4), using the store-backed catalog patterns; the resulting world is exactly the union of the 13 translated patterns (set semantics, overlaps allowed and idempotent).
- A port may not change, drop, add to, or reorder the demo scene (test X4). Nothing here constrains geometry or clipping: if the display crops, the core world still contains all 13 stamps.

---

## Appendix D — Life-rules fixture (normative, test G6)

All cells breed 0. Copy verbatim as test data.

**Input (31 cells):**

```
(23,21), (24,21), (23,22), (26,23), (25,24), (26,24),
(8,13), (10,13), (9,15), (9,18), (8,20), (10,20),
(19,5), (32,11), (29,12), (9,9), (9,24), (8,15), (10,15),
(8,18), (10,18), (9,10), (9,23), (19,4), (19,6),
(31,11), (31,12), (30,11), (30,12), (9,12), (9,21)
```

**Expected generation 1 — `sorted(nextGeneration(input))` (29 cells):**

```
(8,14),  (8,19),  (9,12),  (9,13),  (9,15),  (9,16),
(9,17),  (9,18),  (9,20),  (9,21),  (10,14), (10,19),
(18,5),  (19,5),  (20,5),  (23,21), (23,22), (24,21),
(24,22), (25,23), (25,24), (26,23), (26,24), (29,11),
(29,12), (30,13), (31,10), (32,11), (32,12)
```

**Expected generation 2 — `sorted(nextGeneration(gen1))` (37 cells):**

```
(8,13),  (8,14),  (8,15),  (8,16),  (8,17),  (8,18),
(8,19),  (8,20),  (9,13),  (9,15),  (9,16),  (9,17),
(9,18),  (9,20),  (10,13), (10,14), (10,15), (10,16),
(10,17), (10,18), (10,19), (10,20), (19,4),  (19,5),
(19,6),  (23,21), (23,22), (24,21), (25,24), (26,23),
(26,24), (29,12), (30,11), (30,12), (31,11), (31,12),
(32,11)
```

(These expected lists are independently machine-verifiable: they are exactly what the standard Conway rules of §2.3 produce from the input — implementers may re-derive them as a cross-check, but must ship the lists above as the test data.)

(Sorted order = ascending by x, then y; adapt mechanically to the implementation's sort of positions.)

---

## Appendix E — Optional packed cell encoding (non-normative)

For targets where packed integers genuinely pay off (no structurally hashable keys, tight embedded constraints), the conforming recipe is:

```
CELL   = breed · 1000002000001 + (x + 500000) · 1000001 + (y + 500000)
breedOf(cell)    = CELL div 1000002000001
xOf(cell)        = (CELL div 1000001) mod 1000001 − 500000
yOf(cell)        = CELL mod 1000001 − 500000
positionOf(cell) = CELL mod 1000002000001
```

- Rationale: `1000001 > 2·500000 + 1` makes (x,y) injective; `1000002000001 = 1000001²` stacks breed above position without overlap.
- **Consequences you must accept and document:** coordinates restricted to [-500000, 500000]; breed 0–15 keeps the total < 2⁶¹ (safe for signed 64-bit); JS requires BigInt; test G11 (unboundedness) is adapted to the documented bound; round-trip and `position` tests (C1, C3) still apply.
- Conformance never depends on this encoding. The value-record model of §2.1 is the default and the normative one.

---

## Appendix F — History of the original prototypes (non-normative; not required reading)

This spec was distilled from two prototype implementations built in the Snap! visual programming environment ("part 5" → v1, "part 6" → v2). This appendix records how those prototypes realized the spec, for historical and explanatory interest only. Nothing here is a requirement; where a prototype deviated from this spec, **the spec text wins**.

**Realization of the architecture.** Both prototypes had block-palette categories literally named "Functional Core", "Imperative Shell", "SUnit!", "Data Structure". The interpreter was a single recursive command block matching on instruction operators and threading the `GetPatternCells` result; the program tail was a reified niladic closure — the hand-rolled `bind` of §10. Interpretation ran under `warp` (atomic display refresh), the origin of the §4.3 batching rule. Effects were dispatched as broadcasts to sprites (Cell Grid, Status bar), the origin of the Display port.

**Cells.** The v2 prototype packed `(breed, x, y)` into a single integer (Appendix E) so that Snap! lists could serve as dictionary keys — the encoding is a platform artifact, not a design. The v1 prototype used plain `(x, y)` pairs.

**Data structures.** Snap! has no dictionary type, so the prototype hand-rolled an AVL tree in embedded JavaScript (`{t: node}` wrapper) for the neighbor-count map, plus a generic data-structure library (hash map, directed graph, Dijkstra) and list utilities (fold/take/reverse/split) — none of which is to be ported (§0.1, §2.5). Neighbors were memoized through a hand-rolled per-block cache dict keyed by position; §2.3 makes this the language-idiomatic memoization instead.

**Pattern store.** Snap!'s localStorage extension was wrapped as the store. Patterns were serialized as LISP-ish text (`("cell x: _ y: _" 23 21)`) via `encode blocks`/`parse code`; the store port of §7 deliberately leaves the format open. The prototype saved all 19 catalog patterns **unconditionally at every startup** (overwriting user saves); this spec leaves the seeding policy to the implementation (§4.4). A stray "pulsarLattice" entry in one sprite's variable list was dead data and is not part of the catalog.

**Cross-breeding quirk.** The prototype's `bump` stored `(1, 0)` on the first insertion — dropping the first contributing neighbor's breed and making birth breeds depend on cell iteration order. This spec deliberately fixes that: the fold starts with the first contributor's breed, making the birth breed the order-independent sum of all contributors mod 16 (§2.4.2, G8). Ports must implement the spec, not the quirk.

**UI and shell configuration (reference values).** v1: 43×32 grid, cell size 10, tick every ~0.1 s, status bar hidden at startup, demo scene blinker@(19,5), toad@(30,12), beacon@(24,22), pentadecathlon@(9,16). v2: grid floor(430/8)×floor(320/8), cell size 8, screen mapping `x = col·8 − 220`, `y = 165 − row·8`, tick every 0.2 s, status bar **visible** at startup (the Stage applies `withStatusVisible(true)` after `initialCore`), toggle key `s`, and a demo scene stamping: hwss@(33,20,b1), rpentomino@(35,10,b2), pentadecathlon@(25,30,b3), blinker@(25,10,b4), pentadecathlon@(20,15,b5), mwss@(40,15,b6), glider@(13,13,b7), toad@(30,15,b8), gosperGun@(12,10,b9), pulsar@(13,24,b10), acorn@(25,25,b11), beacon@(25,20,b12), pulsar@(36,30,b13) — these stamps became, unchanged, the normative demo scene of Appendix C, now required in **both** versions (the v1 prototype's own smaller demo scene — blinker@(19,5), toad@(30,12), beacon@(24,22), pentadecathlon@(9,16) — is superseded by the spec; the spec text wins). The v2 pattern selector cycled the sorted repository names and broadcast the selection into `defineCurrentPattern`; the color selector cycled 16 costumes and its index (minus 1) fed the reference cell's breed; clicks always passed the `"current"` sentinel.

**Breed palette.** The prototype's 16 costume colors became, unchanged, the normative palette of Appendix B — the reference values were promoted from example configuration to spec requirement.

**Tests.** The prototypes shipped a custom SUnit-style framework (tests as data, Given/When/Then combinators, per-test Total/Passed/Failed reports with actual-vs-expected failure records) — the origin of §8's required report shape. Their functional suites (cell encoding, count/bump, the two-generation fixture of Appendix D, immutable-dict) are preserved as §11's D/C/N/G groups; the remaining groups are this spec's additions. A performance-test harness existed and is out of scope (§0.1).

**"Current" resolution.** In the prototypes, `"current"` was resolved at program-construction time to the stored pattern name, and the store was then consulted with that name — exactly as §3.3 specifies (an earlier draft of this spec wrongly assumed the store was bypassed; the source settled the question).

---

*End of specification.*
