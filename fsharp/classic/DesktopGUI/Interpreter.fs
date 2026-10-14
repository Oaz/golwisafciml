namespace GameOfLife.Classic.DesktopGUI

open GameOfLife.Classic.Core

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
}

module Interpreter =
    /// Interpret a CoreProgram using the shell's state
    /// This is tail-recursive to avoid stack growth
    /// Modifies the shellState in place
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
