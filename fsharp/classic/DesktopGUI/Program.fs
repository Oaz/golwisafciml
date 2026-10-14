namespace GameOfLife.Classic.DesktopGUI

open GameOfLife.Classic.Core
open GameOfLife.Classic.DesktopGUI.Patterns
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
    
    /// Camera offset to center the demo scene
    /// Demo scene ranges roughly from x=12 to x=40, y=10 to y=30
    /// Center at x=25, y=20 to show the scene properly
    let cameraOffsetX = 25.0
    let cameraOffsetY = 20.0
    
    /// Convert Core.Position to screen coordinates
    /// Apply camera offset to center the demo scene
    /// Note: DIKU.Canvas has y increasing downward, so we need to flip the Y axis
    let positionToScreen (pos: GameOfLife.Classic.Core.Position) : float * float =
        let x = (float pos.X - cameraOffsetX) * cellSize + float windowWidth / 2.0
        let y = float windowHeight / 2.0 + (float pos.Y - cameraOffsetY) * cellSize
        (x, y)
    
    /// Draw a single cell
    let drawCell (pos: GameOfLife.Classic.Core.Position) : PrimitiveTree =
        let x, y = positionToScreen pos
        filledRectangle white cellSize cellSize |> translate x y
    
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
            Set.fold (fun acc cell -> acc |> onto (drawCell cell)) emptyTree cells
    
    /// Main draw function for Canvas.interact
    let draw (st: ShellState) : Picture =
        let bg = filledRectangle black (float windowWidth) (float windowHeight)
        let tree = bg |> onto (drawCells st) |> onto (drawStatusLine st)
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
            // Previous pattern
            if not (List.isEmpty st.PatternNames) then
                let rec findIndex name list idx =
                    match list with
                    | [] -> 0
                    | h::t when h = name -> idx
                    | _::t -> findIndex name t (idx + 1)
                let currentIdx = findIndex st.CurrentPatternName st.PatternNames 0
                let prevIdx = (currentIdx - 1 + List.length st.PatternNames) % List.length st.PatternNames
                Some { st with CurrentPatternName = st.PatternNames.[prevIdx] }
            else
                Some st
        
        | Event.RightArrow ->
            // Next pattern
            if not (List.isEmpty st.PatternNames) then
                let rec findIndex name list idx =
                    match list with
                    | [] -> 0
                    | h::t when h = name -> idx
                    | _::t -> findIndex name t (idx + 1)
                let currentIdx = findIndex st.CurrentPatternName st.PatternNames 0
                let nextIdx = (currentIdx + 1) % List.length st.PatternNames
                Some { st with CurrentPatternName = st.PatternNames.[nextIdx] }
            else
                Some st
        
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
            // Add pattern at clicked position
            let pos = screenToPosition x y
            let patternName = st.CurrentPatternName
            let program = ProgramOperations.addPatternProgram st.CurrentCore patternName pos
            let newSt = updateStateWithInterpretation st program
            Some newSt
        
        | _ -> None
    
    /// Apply demo scene by running addPatternProgram for each stamp
    let applyDemoScene (st: ShellState) : ShellState =
        let rec applyStamps stamps core state =
            match stamps with
            | [] -> { state with CurrentCore = core }
            | stamp::rest ->
                let program = ProgramOperations.addPatternProgram core stamp.PatternName stamp.ReferencePosition
                let newSt = { state with CurrentCore = core }
                Interpreter.interpret newSt program
                let newCore = newSt.CurrentCore
                applyStamps rest newCore newSt
        
        applyStamps DemoScene.stamps Core.initialCore st
    
    /// Print key bindings
    let printKeyBindings () : unit =
        printfn "Key bindings:"
        printfn "  Enter       - Next generation (step mode)"
        printfn "  s           - Toggle status visibility"
        printfn "  p           - Toggle clock/step mode"
        printfn "  Left/Right   - Change pattern"
        printfn "  Click        - Add pattern at position"
    
    /// Main entry point
    [<EntryPoint>]
    let main argv : int =
        try
            // Initialize pattern store
            PatternStore.initCatalog()
            
            // Get pattern names
            let patternNames = PatternStore.getPatternNames()
            
            // Initial state - start in step mode so user can see initial patterns
            let initialState : ShellState = {
                CurrentCore = Core.initialCore
                CurrentPatternName = "glider"
                IsClockMode = true
                CellsToRender = None
                StatusText = None
                StatusVisible = false
                PatternNames = patternNames
            }
            
            // Apply demo scene
            let stateWithDemo = applyDemoScene initialState
            
            // Print key bindings
            printKeyBindings()
            
            // Start interact loop
            interact "Game of Life - Classic" windowWidth windowHeight timerInterval draw react stateWithDemo
            
            0
        with ex ->
            eprintfn "Error: %s" ex.Message
            1
