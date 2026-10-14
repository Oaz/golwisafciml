namespace GameOfLife.Multibreed.DesktopGUI

open GameOfLife.Multibreed.Core
open GameOfLife.Multibreed.DesktopGUI.Patterns
open Canvas
open Color

module Main =
    /// Configuration
    let cellSize = 10.0
    let statusLineHeight = 20.0
    let windowWidth = 1600
    let windowHeight = 1200
    let timerInterval = Some 200 // 200ms timer
    let font = makeFont "Arial" 12.0
    
    /// Breed selector size and margin
    let selectorSize = 20.0
    let margin = 10.0
    
    /// Camera offset to center the demo scene
    /// Demo scene ranges roughly from x=12 to x=40, y=10 to y=30
    /// Center at x=25, y=20 to show the scene properly
    let cameraOffsetX = 25.0
    let cameraOffsetY = 20.0
    
    /// Convert Core.Position to screen coordinates
    /// Apply camera offset to center the demo scene
    /// Note: DIKU.Canvas has y increasing downward, so we need to flip the Y axis
    let positionToScreen (pos: GameOfLife.Multibreed.Core.Position) : float * float =
        let x = (float pos.X - cameraOffsetX) * cellSize + float windowWidth / 2.0
        let y = float windowHeight / 2.0 + (float pos.Y - cameraOffsetY) * cellSize
        (x, y)
    
    /// Draw a single cell with color based on its breed
    let drawCell (pos: GameOfLife.Multibreed.Core.Position) (breed: Breed) : PrimitiveTree =
        let x, y = positionToScreen pos
        filledRectangle (Palette.breedToColor breed) cellSize cellSize |> translate x y
    
    /// Draw the breed selector in bottom-right corner
    let drawBreedSelector (st: ShellState) : PrimitiveTree =
        let selectorX = float windowWidth - selectorSize - margin
        let selectorY = float windowHeight - selectorSize - margin
        let borderSize = selectorSize + 2.0
        // Border rectangle (slightly larger with black border)
        let border = filledRectangle black borderSize borderSize
                   |> translate (selectorX - 1.0) (selectorY - 1.0)
        // Breed color rectangle
        let breedRect = filledRectangle (Palette.breedToColor st.SelectedBreed) selectorSize selectorSize
                      |> translate selectorX selectorY
        // Combine: border on bottom, breed color on top
        border |> onto breedRect
    
    /// Draw the status line at top
    let drawStatusLine (st: ShellState) : PrimitiveTree =
        // Pattern name and mode (right)
        let patternDisplay = sprintf "%s %s" st.CurrentPatternName 
                                (if st.IsClockMode then "[CLOCK]" else "[STEP]")
        
        // Status message (left, if visible)
        let statusMsg = 
            match st.StatusText with
            | Some text when st.StatusVisible -> text
            | _ -> ""
        
        // Background
        let bg = filledRectangle lightGray (float windowWidth) (statusLineHeight)
                 |> translate 0.0 (float windowHeight - statusLineHeight)
        
        // Status message text (left-aligned)
        let statusTextShape = 
            if statusMsg <> "" then
                text black font statusMsg |> translate 10.0 (float windowHeight - statusLineHeight + 5.0)
            else
                emptyTree
        
        // Pattern text (right-aligned)
        let patternText = text black font patternDisplay
                         |> translate (float windowWidth - 150.0) (float windowHeight - statusLineHeight + 5.0)
        
        bg |> onto statusTextShape |> onto patternText
    
    /// Draw all cached cells
    let drawCells (st: ShellState) : PrimitiveTree =
        match st.CellsToRender with
        | None -> emptyTree
        | Some cells ->
            Map.fold (fun acc pos breed -> acc |> onto (drawCell pos breed)) emptyTree cells
    
    /// Main draw function for Canvas.interact
    let draw (st: ShellState) : Picture =
        // White background (v2 requirement)
        let bg = filledRectangle white (float windowWidth) (float windowHeight)
        let tree = bg |> onto (drawCells st) |> onto (drawStatusLine st) |> onto (drawBreedSelector st)
        make tree
    
    /// Screen to Position conversion
    let screenToPosition screenX screenY =
        let originX = float windowWidth / 2.0
        let originY = float windowHeight / 2.0
        let x = int ((screenX - originX) / cellSize + cameraOffsetX)
        let y = int ((screenY - originY) / cellSize + cameraOffsetY)
        Position.create x y
    
    /// Update shell state with new core from interpretation
    let updateStateWithInterpretation (st: ShellState) (program: CoreProgram) : ShellState =
        Interpreter.interpret st program
        st
    
    /// React function: handle events
    let react (st: ShellState) (ev: Event) : ShellState option =
        match ev with
        | Event.TimerTick ->
            // Timer tick - next generation in clock mode
            if st.IsClockMode then
                let newSt = updateStateWithInterpretation st (ProgramOperations.nextGenerationProgram st.CurrentCore)
                Some newSt
            else
                Some st
        
        | Event.LeftArrow ->
            // Previous pattern - use defineCurrentPatternProgram (v2)
            if not (List.isEmpty st.PatternNames) then
                let rec findIndex name list idx =
                    match list with
                    | [] -> 0
                    | h::t when h = name -> idx
                    | _::t -> findIndex name t (idx + 1)
                let currentIdx = findIndex st.CurrentPatternName st.PatternNames 0
                let prevIdx = (currentIdx - 1 + List.length st.PatternNames) % List.length st.PatternNames
                let newPatternName = st.PatternNames.[prevIdx]
                // Update core via defineCurrentPatternProgram
                let program = ProgramOperations.defineCurrentPatternProgram st.CurrentCore newPatternName
                let newSt = updateStateWithInterpretation st program
                Some { newSt with CurrentPatternName = newPatternName }
            else
                Some st
        
        | Event.RightArrow ->
            // Next pattern - use defineCurrentPatternProgram (v2)
            if not (List.isEmpty st.PatternNames) then
                let rec findIndex name list idx =
                    match list with
                    | [] -> 0
                    | h::t when h = name -> idx
                    | _::t -> findIndex name t (idx + 1)
                let currentIdx = findIndex st.CurrentPatternName st.PatternNames 0
                let nextIdx = (currentIdx + 1) % List.length st.PatternNames
                let newPatternName = st.PatternNames.[nextIdx]
                // Update core via defineCurrentPatternProgram
                let program = ProgramOperations.defineCurrentPatternProgram st.CurrentCore newPatternName
                let newSt = updateStateWithInterpretation st program
                Some { newSt with CurrentPatternName = newPatternName }
            else
                Some st
        
        | Event.UpArrow ->
            // Increase selected breed
            let newBreed = Breed.create (Breed.value st.SelectedBreed + 1)
            Some { st with SelectedBreed = newBreed }
        
        | Event.DownArrow ->
            // Decrease selected breed (wraps around)
            let newBreedValue = (Breed.value st.SelectedBreed - 1 + 16) % 16
            Some { st with SelectedBreed = Breed.create newBreedValue }
        
        | Event.Return ->
            // Next generation in step mode
            if not st.IsClockMode then
                let newSt = updateStateWithInterpretation st (ProgramOperations.nextGenerationProgram st.CurrentCore)
                Some newSt
            else
                Some st
        
        | Event.Key 's' ->
            // Toggle status visibility
            let newSt = updateStateWithInterpretation st (ProgramOperations.switchStatusVisibilityProgram st.CurrentCore)
            Some newSt
        
        | Event.Key 'p' ->
            // Toggle clock/step mode (MediaPlayPause equivalent)
            Some { st with IsClockMode = not st.IsClockMode }
        
        | Event.Key _ ->
            Some st
        
        | Event.MouseButtonDown (x, y) ->
            // Add pattern at clicked position with selected breed as reference cell
            let pos = screenToPosition x y
            let referenceCell = Cell.create pos.X pos.Y st.SelectedBreed
            let program = ProgramOperations.addPatternProgram st.CurrentCore "current" referenceCell
            let newSt = updateStateWithInterpretation st program
            Some newSt
        
        | _ -> None
    
    /// Apply demo scene by running addPatternProgram for each stamp
    let applyDemoScene (st: ShellState) : ShellState =
        let rec applyStamps stamps core state =
            match stamps with
            | [] -> { state with CurrentCore = core }
            | stamp::rest ->
                // Set the current pattern to the stamp's pattern name
                let program1 = ProgramOperations.defineCurrentPatternProgram core stamp.PatternName
                let tempSt1 = { state with CurrentCore = core }
                Interpreter.interpret tempSt1 program1
                let coreWithPattern = tempSt1.CurrentCore
                
                // Now add the pattern using "current" sentinel
                let referenceCell = Cell.create stamp.ReferencePosition.X 
                                                  stamp.ReferencePosition.Y 
                                                  stamp.ReferenceBreed
                let program2 = ProgramOperations.addPatternProgram coreWithPattern "current" referenceCell
                let tempSt2 = { state with CurrentCore = coreWithPattern }
                Interpreter.interpret tempSt2 program2
                let newCore = tempSt2.CurrentCore
                applyStamps rest newCore tempSt2
        
        applyStamps DemoScene.stamps Core.initialCore st
    
    /// Print key bindings
    let printKeyBindings () : unit =
        printfn "Key bindings:"
        printfn "  Enter       - Next generation (step mode)"
        printfn "  s           - Toggle status visibility"
        printfn "  p           - Toggle clock/step mode"
        printfn "  Left/Right   - Change pattern (uses defineCurrentPatternProgram)"
        printfn "  Up/Down      - Change selected breed"
        printfn "  Click        - Add pattern at position with selected breed"
    
    /// Main entry point
    [<EntryPoint>]
    let main argv : int =
        try
            // Initialize pattern store
            PatternStore.initCatalog()
            
            // Get pattern names
            let patternNames = PatternStore.getPatternNames()
            
            // Initial core with status visible (v2 requirement)
            let initialCore = Core.withStatusVisible Core.initialCore true
            
            // Initial state - start in step mode so user can see initial patterns
            let initialState : ShellState = {
                CurrentCore = initialCore
                CurrentPatternName = "glider"
                SelectedBreed = Breed.create 0
                IsClockMode = true
                CellsToRender = None
                StatusText = None
                StatusVisible = true
                PatternNames = patternNames
            }
            
            // Apply demo scene
            let stateWithDemo = applyDemoScene initialState
            
            // Print key bindings
            printKeyBindings()
            
            // Start interact loop
            interact "Game of Life - Multi-Breed" windowWidth windowHeight timerInterval draw react stateWithDemo
            
            0
        with ex ->
            eprintfn "Error: %s" ex.Message
            1
