# Plan: F# Multi-Breed DesktopGUI (v2)

**Task:** Implement DesktopGUI for fsharp/multibreed/

**Location:** `fsharp/multibreed/DesktopGUI/`

**Phase:** Phase 1 — PLAN

---

## 0. Prerequisites (Phase 0)

✅ `specs.md` read (Appendix B palette, Appendix C demo scene, §4.4 event wiring)
✅ READMEs along path read (`/README.md` only; no fsharp/ or multibreed/ READMEs exist)
✅ External dependencies decided: DIKU.Canvas (same as classic DesktopGUI)
✅ Core exists: `fsharp/multibreed/core/` implemented with `Map<Position, Cell>`

---

## 1. Scope

### IN scope
- DesktopGUI shell for multibreed core
- Copy and edit `fsharp/classic/DesktopGUI/` files
- DIKU.Canvas integration
- In-memory PatternStore (same as classic)
- White background with colored cells
- Breed selector (color square in bottom-right status bar)
- Up/Down keys change selected breed
- Demo scene with breeds from Appendix C
- Status bar visible at startup (v2 requirement)

### OUT of scope
- Functional core (already in `fsharp/multibreed/core/`)
- Any changes to interpreter or instruction set

---

## 2. Decisions

| Decision | Choice | Rationale |
|---|---|---|
| GUI framework | DIKU.Canvas | Same as classic, cross-platform, F#-native |
| Pattern store | LiteDB 5.0.10 | Embedded .NET NoSQL database for pattern persistence, same as classic DesktopGUI |
| Background | White | User requirement: "with colored cells, we want a white background" |
| Breed palette | Appendix B RGB values | Normative requirement (§4.5) |
| Breed selector | Color square in bottom-right status bar | User requirement |
| Breed selection keys | Up/Down arrows | User requirement |
| Pattern selection | Core state via defineCurrentPatternProgram | Spec §4.4: promoted to core in v2 |
| Click handler | Passes "current" pattern name + Cell with selected breed | Spec §4.4: `addPatternProgram(core, "current", cell(x, y, selectedBreed))` |
| Startup status | Visible | Spec §4.4: v2 starts with status visible |

---

## 3. File Layout

```
fsharp/multibreed/DesktopGUI/
├── DesktopGUI.fsproj
├── Program.fs
├── Interpreter.fs          # COPY from classic, update namespace
├── PatternStore.fs        # COPY from classic, update namespace/types
├── Palette.fs             # NEW: breed->RGB mapping
├── Patterns/
│   ├── Catalog.fs         # COPY from classic, adapt Cells to Map<Position, Cell>
│   └── DemoScene.fs       # MODIFY: add ReferenceBreed field
└── README.md
```

---

## 4. File Responsibilities

| File | Responsibility | Changes from classic |
|------|---------------|---------------------|
| `DesktopGUI.fsproj` | Project config | Update core reference to multibreed, add LiteDB dependency |
| `Interpreter.fs` | Program interpretation | None (domain-ignorant, identical to v1) |
| `Program.fs` | Main, draw, react | White bg, colored cells, breed selector UI, Up/Down handlers, click with Cell ref |
| `PatternStore.fs` | Pattern catalog access with LiteDB | Type changes for Cells + persistence operations |
| `Palette.fs` | Breed->color mapping | NEW |
| `Patterns/Catalog.fs` | 19 patterns from Appendix A | Convert to Map<Position, Cell> with breed 0 |
| `Patterns/DemoScene.fs` | 13 stamps from Appendix C | Add ReferenceBreed field |

---

## 5. Shell State

```fsharp
type ShellState = {
    mutable CurrentCore: Core
    mutable CurrentPatternName: string
    mutable SelectedBreed: Breed      // NEW: selected breed for reference cell
    mutable IsClockMode: bool
    mutable CellsToRender: Cells option
    mutable StatusText: string option
    mutable StatusVisible: bool
    PatternNames: string list
}
```

**Changes from classic:** Added `SelectedBreed: Breed` field.

---

## 6. Breed Palette (NEW)

**File:** `Palette.fs`

Maps breeds 0-15 to exact RGB colors from Appendix B:

| Breed | RGB | Breed | RGB |
|---|---|---|---|
| 0 | (127,127,127) | 8 | (0,255,0) |
| 1 | (127,0,0) | 9 | (190,63,255) |
| 2 | (0,127,0) | 10 | (0,255,255) |
| 3 | (0,0,127) | 11 | (190,255,63) |
| 4 | (255,0,0) | 12 | (0,0,255) |
| 5 | (255,255,0) | 13 | (255,63,190) |
| 6 | (63,190,255) | 14 | (255,190,63) |
| 7 | (63,255,190) | 15 | (255,0,255) |

---

## 7. Pattern Catalog

**File:** `Patterns/Catalog.fs`

Same 19 patterns as classic (Appendix A), but stored as `Map<Position, Cell>` where each Cell has breed 0.

Example:
```fsharp
let patterns: Map<string, Cells> = Map.ofList [
    ("blinker", Map.ofList [
        (Position.create -1 0, Cell.create -1 0 (Breed.create 0))
        (Position.create 0 0, Cell.create 0 0 (Breed.create 0))
        (Position.create 1 0, Cell.create 1 0 (Breed.create 0))
    ])
    // ... all 19 patterns
]
```

---

## 8. Demo Scene

**File:** `Patterns/DemoScene.fs`

13 stamps from Appendix C with reference breeds:

```fsharp
type Stamp = {
    PatternName: string
    ReferencePosition: Position
    ReferenceBreed: Breed      // NEW: from Appendix C column 4
}

let stamps: Stamp list = [
    { PatternName = "hwss"; ReferencePosition = Position.create 33 20; ReferenceBreed = Breed.create 1 }
    { PatternName = "rpentomino"; ReferencePosition = Position.create 35 10; ReferenceBreed = Breed.create 2 }
    // ... all 13 stamps with breeds 1-13
]
```

---

## 9. Pattern Store (LiteDB)

**File:** `PatternStore.fs`

Uses LiteDB for persistence. Same operations as classic but with `Cells = Map<Position, Cell>`.

**Operations:**
- `initCatalog()` - Seed database with Appendix A patterns (seed missing only)
- `getPatternCells(name)` - Fetch pattern from DB, return as `Map<Position, Cell>` with breed 0
- `getPatternNames()` - Return sorted list of pattern names
- `save(name, cells)` - Save pattern (v2 authoring)
- `remove(name)` - Remove pattern (v2 authoring)
- `clear()` - Clear all patterns (v2 authoring)
- `names()` - Get all pattern names sorted (v2 authoring)

**Serialization:**
- Store each Cell's Position (X, Y) and Breed value
- Use BsonDocument for pattern records with `_id` = pattern name, `cells` = array of {x, y, breed}

**Seed policy:** Seed only missing patterns on startup (protect user data)

---

## 10. Interpreter

**File:** `Interpreter.fs`

Exact copy from classic, only namespace change. Unchanged because:
- Program type identical in v1 and v2
- Interpreter is domain-ignorant
- Only dispatches on instruction constructors

---

## 11. Main Program (Program.fs)

### Key Changes from Classic

1. **Background:** White (was black)
   ```fsharp
   let bg = filledRectangle white (float windowWidth) (float windowHeight)
   ```

2. **Cell rendering:** Colored by breed
   ```fsharp
   let drawCell (pos: Position) (breed: Breed) : PrimitiveTree =
       let x, y = positionToScreen pos
       filledRectangle (Palette.breedToColor breed) cellSize cellSize |> translate x y
   ```

3. **Draw cells:** Use Map.fold
   ```fsharp
   let drawCells (st: ShellState) : PrimitiveTree =
       match st.CellsToRender with
       | None -> emptyTree
       | Some cells ->
           Map.fold (fun acc pos cell -> acc |> onTopOf (drawCell pos cell.Breed)) emptyTree cells
   ```

4. **Breed selector in bottom-right:**
   ```fsharp
   let drawBreedSelector (st: ShellState) : PrimitiveTree =
       let selectorX = float windowWidth - selectorSize - margin
       let selectorY = margin
       filledRectangle (Palette.breedToColor st.SelectedBreed) selectorSize selectorSize
       |> translate selectorX selectorY
   ```

5. **Up/Down handlers:** Change selected breed
   ```fsharp
   | Event.UpArrow ->
       let newBreed = Breed.create (Breed.value st.SelectedBreed + 1)
       Some { st with SelectedBreed = newBreed }
   | Event.DownArrow ->
       let newBreedValue = (Breed.value st.SelectedBreed - 1 + 16) % 16
       Some { st with SelectedBreed = Breed.create newBreedValue }
   ```

6. **Left/Right handlers:** Change pattern AND call defineCurrentPatternProgram
   ```fsharp
   | Event.RightArrow ->
       // Find next pattern index
       let nextIdx = (currentIdx + 1) % List.length st.PatternNames
       let newPatternName = st.PatternNames.[nextIdx]
       // Update core via defineCurrentPatternProgram
       let program = ProgramOperations.defineCurrentPatternProgram st.CurrentCore newPatternName
       let newSt = updateStateWithInterpretation st program
       Some { newSt with CurrentPatternName = newPatternName }
   ```

7. **Click handler:** Pass Cell with selected breed
   ```fsharp
   | Event.MouseButtonDown (x, y) ->
       let pos = screenToPosition x y
       let referenceCell = Cell.create pos.X pos.Y st.SelectedBreed
       let program = ProgramOperations.addPatternProgram st.CurrentCore "current" referenceCell
       let newSt = updateStateWithInterpretation st program
       Some newSt
   ```

8. **Demo scene application:** Use stamp's breed
   ```fsharp
   let applyDemoScene (st: ShellState) : ShellState =
       let rec applyStamps stamps core state =
           match stamps with
           | [] -> { state with CurrentCore = core }
           | stamp::rest ->
               let referenceCell = Cell.create stamp.ReferencePosition.X 
                                                  stamp.ReferencePosition.Y 
                                                  stamp.ReferenceBreed
               let program = ProgramOperations.addPatternProgram core "current" referenceCell
               // ... interpret and recurse
       applyStamps DemoScene.stamps Core.initialCore st
   ```

9. **Startup:** Status visible (v2 requirement)
   ```fsharp
   let initialCore = Core.withStatusVisible Core.initialCore true
   let initialState = {
       CurrentCore = initialCore
       CurrentPatternName = "glider"
       SelectedBreed = Breed.create 0
       IsClockMode = true
       CellsToRender = None
       StatusText = None
       StatusVisible = true
       PatternNames = PatternStore.getPatternNames()
   }
   ```

---

## 12. Project Configuration

**DesktopGUI.fsproj:**
```xml
<Project Sdk="Microsoft.NET.Sdk">
  <PropertyGroup>
    <OutputType>Exe</OutputType>
    <TargetFramework>net10.0</TargetFramework>
  </PropertyGroup>
  <ItemGroup>
    <ProjectReference Include="..\core\Core.fsproj" />
    <PackageReference Include="DIKU.Canvas" Version="2.0.0" />
    <PackageReference Include="LiteDB" Version="5.0.10" />
  </ItemGroup>
  <ItemGroup>
    <Compile Include="Program.fs" />
    <Compile Include="Interpreter.fs" />
    <Compile Include="PatternStore.fs" />
    <Compile Include="Palette.fs" />
    <Compile Include="Patterns\**\*.fs" />
  </ItemGroup>
</Project>
```

---

## 13. Core Black Box Principle

**The shell NEVER accesses Core fields directly.**

✅ Holds `currentCore: Core` reference
✅ Passes `currentCore` to core operations
✅ Interprets resulting `CoreProgram`
✅ Updates `currentCore` on `Return`
✅ Caches rendering state from instructions
✅ Creates Cell references for clicks using selected breed

❌ `currentCore.Cells`
❌ `currentCore.StatusVisible`
❌ `currentCore.CurrentPattern`
❌ Any pattern matching on Core
❌ Any direct field access or mutation

---

## 14. Evolution from Classic

| Aspect | v1 | v2 |
|---|---|---|
| Background | Black | White |
| Cell color | White | Breed-based (Appendix B) |
| Pattern selection | Shell config, used at click | Core state via defineCurrentPatternProgram, click uses "current" |
| Breed selection | N/A | Shell config, used at click |
| Click reference | Position | Cell with selected breed |
| Status at startup | Hidden | Visible |
| Demo scene | Positions only | Positions + reference breeds |

**Interpreter:** IDENTICAL (no changes)
**Instruction set:** IDENTICAL (no new opcodes)
**Shell wiring:** IDENTICAL (same event loop structure)

---

## 15. Acceptance Criteria

1. Application starts with white background
2. Colored cells rendered per Appendix B palette
3. Breed selector visible in bottom-right as color square
4. Up/Down keys cycle through breeds 0-15
5. Left/Right keys change pattern (calls defineCurrentPatternProgram)
6. Click adds pattern with selected breed as reference cell
7. Status bar visible at startup
8. All classic functionality preserved (clock/step, tick, etc.)
9. Shell never accesses Core fields directly
10. Interpreter unchanged from classic
11. README documents implementation

---

## 16. How Spec Invariants Are Honored

**Pure core, instruction protocol:**
- Core remains untouched (in `fsharp/multibreed/core/`)
- Shell only interacts with core via operations that return `CoreProgram`
- Interpreter is identical to v1, only dispatches on instruction constructors
- No new opcodes added (per §13.1)

**Single mutation point:**
- Only `Return` instruction updates `currentCore` reference in shell
- All other shell state (CellsToRender, StatusText, etc.) is cached from instructions, not read from core

**Replaceable adapters:**
- Display: DIKU.Canvas integration, can be swapped
- PatternStore: In-memory implementation, can be extended to persistence
- EventSource: DIKU.Canvas events, can be swapped

**Evolution discipline (§13.2):**
- Shell wiring unchanged from v1
- Interpreter unchanged from v1
- Instruction set unchanged from v1
- Only additions: breed selector UI, colored rendering, pattern selection via core

---

## 17. Test Plan

Shell tests verify integration and rendering. Core tests are in `fsharp/multibreed/core/Tests/`.

**Test groups affected:**
- **I7, I10, I11**: Interpreter dispatch (unchanged logic, v2 types)
- **P8**: Breed palette mapping (verify Appendix B colors)
- **X1b**: Startup status visible (v2 requirement)
- **X4**: Demo scene with breeds

**Shell-specific tests:**
- Breed selector renders correct color
- Up/Down keys change selected breed
- Click with selected breed creates correct reference cell
- White background rendering
- Pattern selection via defineCurrentPatternProgram
- PatternStore persistence with LiteDB (get, list, seed)

---

## 18. Open Questions

None. All decisions resolved from user requirements and spec.

---

## 19. Implementation Checklist (Phase 2)

- [ ] Create directory structure
- [ ] Create `DesktopGUI.fsproj`
- [ ] Create `Palette.fs`
- [ ] Create `Program.fs` (copy classic, apply changes)
- [ ] Copy `Interpreter.fs` from classic (update namespace)
- [ ] Create `PatternStore.fs` with LiteDB persistence (adapt from classic)
- [ ] Create `Patterns/Catalog.fs` (adapt from classic)
- [ ] Create `Patterns/DemoScene.fs` (add breeds)
- [ ] Create `README.md`
- [ ] Build and test
- [ ] Verify all acceptance criteria

---

**Status:** Plan complete, all decisions resolved.

**Approval needed:** Review and respond with "Plan approved" or "Go ahead" before Phase 2.
