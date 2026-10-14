# Plan: F# Classic DesktopGUI (v1) — Imperative Shell Implementation

**Task:** Implement the **DesktopGUI** imperative shell for Conway's Game of Life, Version 1 (Monochrome) in F#.

**Location:** `fsharp/classic/DesktopGUI/`

**Phase:** Phase 1 — PLAN (no project files may be written before approval)

---

## 0. Context and Scope

This plan covers the **imperative shell** for the **DesktopGUI environment** using **DIKU.Canvas** for rendering and **LiteDB** for pattern persistence.

The shell treats the **core as a black box** — it only:
- Holds a mutable reference to the current `Core`
- Calls core operations to get `CoreProgram` values
- Interprets programs by dispatching on instructions
- Never accesses `Core` fields directly

The shell implements:
- The **interpreter** for `CoreProgram` values
- The **ports** (integrated directly with DIKU.Canvas)
- The **event loop** using DIKU.Canvas's `interact` with draw and react functions
- Startup initialization (pattern catalog, demo scene)

### What is IN scope

- Complete F# implementation of the **v1 DesktopGUI shell**
- `Canvas.interact` integration with draw and react functions
- Pattern storage with LiteDB
- Interpreter for the Program type from core
- Demo scene and pattern catalog initialization
- Shell-specific tests

### What is OUT of scope

- Version 2 (multi-breed)
- The functional core (already in `fsharp/classic/core/`)
- Other environments (WebSPA, etc.)
- Any code that accesses Core fields directly

---

## 1. Decisions

| Decision | Choice | Rationale |
|---|---|---|
| **GUI Framework** | DIKU.Canvas | Cross-platform, F#-native, provides `interact` with draw/react functions |
| **Pattern Store** | LiteDB | Lightweight embedded .NET NoSQL database |
| **Target Framework** | .NET 10.0 | Latest LTS, compatible with both dependencies |
| **Main Loop** | `Canvas.interact draw react` | DIKU.Canvas built-in interactive loop |
| **Pattern Store Location** | `patterns.db` in working directory | Standard location, seed missing only |
| **Error Handling** | stderr + continue | Display errors on stderr and keep running |
| **Core Access** | Black box only | Shell only holds Core reference, never accesses its fields |

---

## 2. Architecture

**Core principle:** The shell uses the core as a black box.

```
┌─────────────────────────────────────────────┐
│                 Main.fs                     │
│  ┌─────────────────────────────────────────┐│
│  │  Shell State (mutable)                  ││
│  │  - currentCore: Core ref  ← only this   ││
│  │  - currentPatternName: string           ││
│  │  - isClockMode: bool                    ││
│  │  - cellsToRender: Cells option          ││  ← for ShowCells caching
│  │  - statusText: string option            ││  ← for DisplayMessage caching
│  └─────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────┐│
│  │  draw() function:                       ││
│  │  - If cellsToRender set, render them    ││
│  │  - If statusText set, show it           ││
│  │  - Render status line at top            ││
│  └─────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────┐│
│  │  react(event) function:                 ││
│  │  - Timer tick → trigger nextGeneration  ││
│  │  - Enter → trigger nextGeneration       ││
│  │  - 's' → trigger switchStatusVisibility ││
│  │  - MediaPlayPause → toggle mode         ││
│  │  - Left/Right → change pattern          ││
│  │  - Click → trigger addPattern           ││
│  └─────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────┐│
│  │  Interpreter: interpret(program)        ││
│  │  - Match on Program constructors        ││
│  │  - ShowCells: cache cells for draw      ││
│  │  - DisplayMessage: cache text           ││
│  │  - HideMessage: clear statusText        ││
│  │  - GetPatternCells: fetch + continue    ││
│  │  - Return: update currentCore ref       ││
│  └─────────────────────────────────────────┘│
└─────────────────────────────────────────────┘
                     │
                     ▼
          ┌────────────────────┐
          │  DIKU.Canvas       │
          │  - Canvas.interact │
          └────────────────────┘
                     │
                     ▼
          ┌───────────────────┐
          │  LiteDB           │
          │  - patterns.db    │
          └───────────────────┘
```

**Key rule:** The shell **never** does `state.CurrentCore.Cells`. It only:
- Calls `Core.initialCore` to get initial state
- Passes `currentCore` to core operations to get `CoreProgram`
- Interprets programs which may end with `Return(newCore)`
- Updates `currentCore <- newCore` on `Return`
- All rendering state (cells to display, status text) is cached in shell state, not read from core

---

## 3. File Layout

```
fsharp/classic/DesktopGUI/
├── DesktopGUI.fsproj          # Console application
├── Program.fs                 # Main entry point with draw/react
├── Patterns/
│   ├── Catalog.fs             # 19 patterns from Appendix A
│   └── DemoScene.fs           # 13 stamps from Appendix C
├── Tests/
│   └── ShellTests.fsproj      # Test project
└── README.md
```

---

## 4. Project Configuration

### DesktopGUI.fsproj

```xml
<Project Sdk="Microsoft.NET.Sdk">
  <PropertyGroup>
    <OutputType>Exe</OutputType>
    <TargetFramework>net10.0</TargetFramework>
    <ImplicitUsings>enable</ImplicitUsings>
  </PropertyGroup>

  <ItemGroup>
    <ProjectReference Include="..\core\Core.fsproj" />
    <PackageReference Include="DIKU.Canvas" Version="2.0.0" />
    <PackageReference Include="LiteDB" Version="5.0.10" />
  </ItemGroup>

  <ItemGroup>
    <Compile Include="Program.fs" />
    <Compile Include="Patterns\**\*.fs" />
  </ItemGroup>
</Project>
```

---

## 5. Implementation

### 5.1 Shell State

```fsharp
namespace GameOfLife.Classic.DesktopGUI

open GameOfLife.Classic.Core
open Canvas
open Color

/// Shell state - holds the shell's mutable state
/// The Core itself is a black box; we only hold a reference to it
type ShellState = {
    mutable CurrentCore: Core
    mutable CurrentPatternName: string
    mutable IsClockMode: bool
    mutable CellsToRender: Cells option      // Cached from ShowCells for draw
    mutable StatusText: string option        // Cached from DisplayMessage for draw
    mutable StatusVisible: bool             // Cached from core via ShowCells/DisplayMessage
    PatternNames: string list
    Display: Canvas option
}
```

### 5.2 Pattern Data

**File:** `Patterns/Catalog.fs`

```fsharp
namespace GameOfLife.Classic.DesktopGUI.Patterns

open GameOfLife.Classic.Core

module Catalog =
    /// All 19 patterns from Appendix A
    let patterns: Map<string, Cells> = Map.ofList [
        ("blinker", Set.ofList [Position.create -1 0; Position.create 0 0; Position.create 1 0])
        // ... all other patterns verbatim from Appendix A
    ]
    
    /// Get pattern names in sorted order
    let patternNames: string list =
        Map.keys patterns |> Seq.sort |> Seq.toList
```

**File:** `Patterns/DemoScene.fs`

```fsharp
namespace GameOfLife.Classic.DesktopGUI.Patterns

open GameOfLife.Classic.Core

/// A stamp: pattern to place at a position
type Stamp = { PatternName: string; ReferencePosition: Position }

module DemoScene =
    let stamps: Stamp list = [
        { PatternName = "hwss"; ReferencePosition = Position.create 33 20 }
        { PatternName = "rpentomino"; ReferencePosition = Position.create 35 10 }
        // ... all 13 stamps from Appendix C (ignore breed column for v1)
    ]
```

### 5.3 Pattern Store (LiteDB)

```fsharp
module PatternStore =
    open LiteDB
    open System.IO
    
    let dbPath = "patterns.db"
    
    /// Serialize Cells to BsonArray
    let private serializeCells (cells: Cells) : BsonArray =
        cells |> Set.toList |> List.map (fun p -> 
            BsonDocument([| "x" := p.X; "y" := p.Y |])) |> BsonArray
    
    /// Deserialize BsonArray to Cells
    let private deserializeCells (arr: BsonArray) : Cells =
        arr |> Seq.map (fun v -> 
            let d = v.AsDocument
            Position.create d["x"].AsInt32 d["y"].AsInt32) |> Set.ofSeq
    
    /// Initialize database with catalog patterns (seed missing only)
    let initCatalog () : unit =
        use db = new LiteDatabase(dbPath)
        let col = db.GetCollection<BsonDocument>("patterns")
        
        for (name, cells) in Map.toList Catalog.patterns do
            if not (col.Exists(fun p -> p["_id"].AsString = name)) then
                let doc = BsonDocument()
                doc["_id"] <- name
                doc["cells"] <- serializeCells cells
                col.Insert(doc) |> ignore
    
    /// Get pattern cells by name (returns empty set if not found)
    let getPatternCells (name: string) : Cells =
        use db = new LiteDatabase(dbPath)
        let col = db.GetCollection<BsonDocument>("patterns")
        
        match col.FindOne(fun p -> p["_id"].AsString = name) with
        | null -> Set.empty
        | doc -> deserializeCells doc["cells"].AsArray
    
    /// Get all pattern names in sorted order
    let getPatternNames () : string list =
        use db = new LiteDatabase(dbPath)
        let col = db.GetCollection<BsonDocument>("patterns")
        col.FindAll() |> Seq.map (fun p -> p["_id"].AsString) |> Seq.sort |> Seq.toList
```

### 5.4 Interpreter

**Key principle:** The interpreter dispatches on Program constructors. It **never** accesses Core fields. It only:
- Renders via cached state when it sees `ShowCells`
- Displays text via cached state when it sees `DisplayMessage`
- Hides status when it sees `HideMessage`
- Fetches patterns from store when it sees `GetPatternCells`
- Updates `currentCore` when it sees `Return`

```fsharp
module Interpreter =
    open GameOfLife.Classic.Core
    
    /// Interpret a CoreProgram using the shell's state
    /// This is tail-recursive to avoid stack growth
    let interpret (shellState: ShellState) (program: CoreProgram) : unit =
        let rec interpretRec (prog: CoreProgram) : unit =
            match prog with
            | Pure core ->
                // Return: commit the new core
                shellState.CurrentCore <- core
            
            | ShowCells (cells, next) ->
                // Cache cells for rendering
                shellState.CellsToRender <- Some cells
                interpretRec next
            
            | DisplayMessage (text, next) ->
                // Cache status text for rendering
                shellState.StatusText <- Some text
                shellState.StatusVisible <- true
                interpretRec next
            
            | HideMessage next ->
                // Clear status text
                shellState.StatusText <- None
                shellState.StatusVisible <- false
                interpretRec next
            
            | GetPatternCells (name, continuation) ->
                // Fetch from pattern store and continue
                let patternCells = PatternStore.getPatternCells name
                let nextProg = continuation patternCells
                interpretRec nextProg
        
        // Start interpretation
        interpretRec program
```

### 5.5 Main Program with Draw and React

**File:** `Program.fs`

```fsharp
namespace GameOfLife.Classic.DesktopGUI

open GameOfLife.Classic.Core
open GameOfLife.Classic.DesktopGUI.Patterns
open Canvas
open Color

module Main =
    /// Shell state
    let state = {
        CurrentCore = Core.initialCore
        CurrentPatternName = "glider"
        IsClockMode = true
        CellsToRender = None
        StatusText = None
        StatusVisible = false
        PatternNames = PatternStore.getPatternNames()
        Display = None
    }
    
    /// Configuration
    let cellSize = 10.0
    let statusLineHeight = 20.0
    let windowWidth = 800
    let windowHeight = 600
    
    /// Convert Position to screen coordinates
    let positionToScreen (pos: Position) : float * float =
        let x = float pos.X * cellSize + float windowWidth / 2.0
        let y = float windowHeight / 2.0 - float pos.Y * cellSize
        (x, y)
    
    /// Draw a single cell
    let drawCell (pos: Position) : Shape =
        let x, y = positionToScreen pos
        filledRectangle black (cellSize / 2.0) (cellSize / 2.0) |> move x y
    
    /// Draw the status line at top
    let drawStatusLine () : Shape =
        // Pattern name and mode (right)
        let patternDisplay = sprintf "%s %s" state.CurrentPatternName 
                                (if state.IsClockMode then "[CLOCK]" else "[STEP]")
        
        // Status message (left, if visible)
        let statusMsg = 
            match state.StatusText with
            | Some text when state.StatusVisible -> text
            | _ -> ""
        
        // Background
        let bg = filledRectangle lightgray (float windowWidth / 2.0) (statusLineHeight / 2.0)
                 |> move (float windowWidth / 2.0) (float windowHeight - statusLineHeight / 2.0)
        
        // Status message text (left-aligned)
        let statusTextShape = 
            if statusMsg <> "" then
                text black statusMsg |> move (10.0) (float windowHeight - statusLineHeight / 2.0 - 5.0)
            else
                empty
        
        // Pattern text (right-aligned)
        let patternText = text black patternDisplay
                         |> move (float windowWidth - 10.0) (float windowHeight - statusLineHeight / 2.0 - 5.0)
        
        bg |> onTopOf statusTextShape |> onTopOf patternText
    
    /// Draw all cached cells
    let drawCells () : Shape =
        match state.CellsToRender with
        | None -> empty
        | Some cells ->
            Set.fold (fun acc cell -> acc |> onTopOf (drawCell cell)) empty cells
    
    /// Main draw function for Canvas.interact
    let draw () : Shape =
        drawCells () |> onTopOf (drawStatusLine ())
    
    /// Screen to Position conversion
    let screenToPosition (screenX: float, screenY: float) : Position =
        let originX = float windowWidth / 2.0
        let originY = float windowHeight / 2.0
        let x = int ((screenX - originX) / cellSize)
        let y = int ((originY - screenY) / cellSize)
        Position.create x y
    
    /// Trigger: run a core operation and interpret the resulting program
    let trigger (coreOp: Core -> CoreProgram) : unit =
        let program = coreOp state.CurrentCore
        Interpreter.interpret state program
    
    /// React function: handle events
    let react (event: Event) : unit =
        match event with
        | Timer _ ->
            // Timer tick - next generation in clock mode
            if state.IsClockMode then
                trigger ProgramOperations.nextGenerationProgram
        
        | Key (key, _) ->
            match key with
            | "Enter" ->
                // Next generation in step mode
                if not state.IsClockMode then
                    trigger ProgramOperations.nextGenerationProgram
            | "s" ->
                // Toggle status visibility
                trigger ProgramOperations.switchStatusVisibilityProgram
            | "MediaPlayPause" ->
                // Toggle clock/step mode
                state.IsClockMode <- not state.IsClockMode
            | "Left" ->
                // Previous pattern
                if not (List.isEmpty state.PatternNames) then
                    let currentIdx = List.findIndex (fun name -> name = state.CurrentPatternName) state.PatternNames
                    let prevIdx = match currentIdx with
                                    | Some idx -> (idx - 1 + List.length state.PatternNames) % List.length state.PatternNames
                                    | None -> 0
                    state.CurrentPatternName <- state.PatternNames[prevIdx]
            | "Right" ->
                // Next pattern
                if not (List.isEmpty state.PatternNames) then
                    let currentIdx = List.findIndex (fun name -> name = state.CurrentPatternName) state.PatternNames
                    let nextIdx = match currentIdx with
                                    | Some idx -> (idx + 1) % List.length state.PatternNames
                                    | None -> 0
                    state.CurrentPatternName <- state.PatternNames[nextIdx]
            | _ -> ()
        
        | MouseDown (x, y, _) ->
            // Add pattern at clicked position
            let pos = screenToPosition x y
            let patternName = state.CurrentPatternName
            // Use addPatternProgram with the current pattern name
            let program = ProgramOperations.addPatternProgram state.CurrentCore patternName pos
            Interpreter.interpret state program
        
        | _ -> ()
    
    /// Apply demo scene by running addPatternProgram for each stamp
    let applyDemoScene () : unit =
        // Start from initial core
        state.CurrentCore <- Core.initialCore
        
        for stamp in DemoScene.stamps do
            let program = ProgramOperations.addPatternProgram state.CurrentCore stamp.PatternName stamp.ReferencePosition
            Interpreter.interpret state program
    
    /// Print key bindings
    let printKeyBindings () : unit =
        printfn "Key bindings:"
        printfn "  Enter       - Next generation (step mode)"
        printfn "  s           - Toggle status visibility"
        printfn "  MediaPlayPause - Toggle clock/step mode"
        printfn "  Left/Right   - Change pattern"
        printfn "  Click        - Add pattern at position"
    
    /// Main entry point
    [<EntryPoint>]
    let main argv : int =
        try
            // Initialize pattern store
            PatternStore.initCatalog()
            
            // Update state with pattern names
            state.PatternNames <- PatternStore.getPatternNames()
            
            // Apply demo scene
            applyDemoScene()
            
            // Print key bindings
            printKeyBindings()
            
            // Create canvas
            let canvas = render "Game of Life - Classic" windowWidth windowHeight (draw())
            state.Display <- Some canvas
            
            // Start interact loop with 100ms timer
            // Timer events will be delivered to react()
            interact canvas draw react
            
            0
        with ex ->
            eprintfn "Error: %s" ex.Message
            1

---

## 6. Pattern Catalog

**File:** `Patterns/Catalog.fs`

Contains all 19 patterns from Appendix A verbatim:
- blinker, toad, beacon, pentadecathlon
- block, beehive, loaf, boat, tub
- glider, lwss, mwss, hwss
- rpentomino, diehard, acorn
- pulsar, gosperGun, simkinGun

Each pattern is defined as `Set<Position>` with exact coordinates from Appendix A.

---

## 7. Demo Scene

**File:** `Patterns/DemoScene.fs`

Contains all 13 stamps from Appendix C verbatim (ignoring breed column for v1):
1. hwss at (33, 20)
2. rpentomino at (35, 10)
3. pentadecathlon at (25, 30)
4. blinker at (25, 10)
5. pentadecathlon at (20, 15)
6. mwss at (40, 15)
7. glider at (13, 13)
8. toad at (30, 15)
9. gosperGun at (12, 10)
10. pulsar at (13, 24)
11. acorn at (25, 25)
12. beacon at (25, 20)
13. pulsar at (36, 30)

---

## 8. Core Black Box Principle

**The shell NEVER accesses Core fields directly.**

What the shell does:
- ✅ Holds `currentCore: Core` reference
- ✅ Passes `currentCore` to core operations: `nextGenerationProgram currentCore`
- ✅ Interprets resulting `CoreProgram`
- ✅ On `Return(newCore)`: updates `currentCore <- newCore`
- ✅ Caches rendering state (CellsToRender, StatusText) from instructions

What the shell does NOT:
- ❌ `currentCore.Cells`
- ❌ `currentCore.StatusVisible`
- ❌ Any pattern matching on Core
- ❌ Any direct field access or mutation of Core

All state observation happens through:
- The interpreter caching instruction payloads (`ShowCells`, `DisplayMessage`)
- The core operations returning new Core values via `Return`

---

## 9. Tests

Shell tests verify the interpreter and integration.

**File:** `Tests/ShellTests.fsproj`

```xml
<Project Sdk="Microsoft.NET.Sdk">
  <PropertyGroup>
    <TargetFramework>net10.0</TargetFramework>
  </PropertyGroup>
  <ItemGroup>
    <PackageReference Include="Expecto" Version="12.0.0" />
    <PackageReference Include="DIKU.Canvas" Version="2.0.0" />
    <PackageReference Include="LiteDB" Version="5.0.10" />
  </ItemGroup>
  <ItemGroup>
    <ProjectReference Include="..\..\core\Core.fsproj" />
    <ProjectReference Include="..\DesktopGUI.fsproj" />
  </ItemGroup>
</Project>
```

Test cases:
- Interpreter dispatch (I7, I10, I11)
- Pattern store get/list operations
- Demo scene produces expected cell count

---

## 10. Deviations

| Spec Requirement | Implementation | Rationale |
|---|---|---|
| Ports injected, not global | Direct DIKU.Canvas integration | Simpler for desktop; DIKU.Canvas provides the abstraction |
| Shell has interpreter | Implemented with tail-recursive interpretRec | Dispatches on Program constructors only |
| Single mutation point | Only `Return` updates currentCore | All other state is in shell, not core |
| Core is black box | Never access Core fields | All observation via instructions |

---

## 11. Acceptance Criteria

1. Application starts and displays the demo scene correctly
2. Clicking on the canvas adds the current pattern at the clicked position
3. 's' key toggles status message visibility
4. Enter key advances one generation (in step mode)
5. Media play/pause key toggles between clock and step modes
6. Left/right arrow keys cycle through patterns
7. In clock mode, generations advance automatically (every timer tick)
8. Status line shows: pattern name + mode on right, status message on left (when visible)
9. All 19 catalog patterns available via pattern cycling
10. All 13 demo stamps rendered at startup
11. Key bindings printed to stdout at startup
12. Errors displayed on stderr, application continues
13. Code compiles cleanly with no warnings
14. Shell never accesses Core fields directly
15. README documents the implementation

---

## 12. Design for v2 Evolution

Changes for v2 (not in this shell):
- **Display:** Add breed→color mapping using Appendix B palette
- **PatternStore:** Add save/remove/clear for pattern authoring
- **React:** Add breed selection and keys for breed cycling
- **Core:** Use v2 core with Breed type

This shell's architecture supports v2 because:
- Core is already a black box
- Interpreter doesn't care about core internals
- PatternStore interface can be extended
- React function can be extended with new event handlers

---

**Status:** Plan complete, awaiting human approval.

**Approval needed:** Please review and respond with "Plan approved" or "Go ahead" before I proceed to Phase 2 — IMPLEMENT.
