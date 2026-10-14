namespace GameOfLife.Classic.DesktopGUI.Patterns

open GameOfLife.Classic.Core

/// A stamp: pattern to place at a position
type Stamp = { PatternName: string; ReferencePosition: Position }

module DemoScene =
    /// All 13 stamps from Appendix C with exact normative positions
    /// Breed column is ignored for v1 (monochrome)
    let stamps: Stamp list = [
        { PatternName = "hwss"; ReferencePosition = Position.create 33 20 }
        { PatternName = "rpentomino"; ReferencePosition = Position.create 35 10 }
        { PatternName = "pentadecathlon"; ReferencePosition = Position.create 25 30 }
        { PatternName = "blinker"; ReferencePosition = Position.create 25 10 }
        { PatternName = "pentadecathlon"; ReferencePosition = Position.create 20 15 }
        { PatternName = "mwss"; ReferencePosition = Position.create 40 15 }
        { PatternName = "glider"; ReferencePosition = Position.create 13 13 }
        { PatternName = "toad"; ReferencePosition = Position.create 30 15 }
        { PatternName = "gosperGun"; ReferencePosition = Position.create 12 10 }
        { PatternName = "pulsar"; ReferencePosition = Position.create 13 24 }
        { PatternName = "acorn"; ReferencePosition = Position.create 25 25 }
        { PatternName = "beacon"; ReferencePosition = Position.create 25 20 }
        { PatternName = "pulsar"; ReferencePosition = Position.create 36 30 }
    ]
