namespace GameOfLife.Classic.DesktopGUI

open GameOfLife.Classic.Core
open GameOfLife.Classic.DesktopGUI.Patterns

module PatternStore =
    /// Initialize catalog (no-op for in-memory implementation)
    let initCatalog () : unit =
        ()

    /// Get pattern cells by name (returns empty set if not found)
    let getPatternCells (name: string) : Cells =
        match Map.tryFind name Catalog.patterns with
        | Some cells -> cells
        | None -> Set.empty

    /// Get all pattern names in sorted order
    let getPatternNames () : string list =
        Catalog.patternNames
