namespace GameOfLife.Multibreed.DesktopGUI.Patterns

open GameOfLife.Multibreed.Core

/// A stamp: pattern to place at a position with a reference breed (v2)
type Stamp = {
    PatternName: string
    ReferencePosition: Position
    ReferenceBreed: Breed      // NEW: from Appendix C column 4
}

module DemoScene =
    /// All 13 stamps from Appendix C with exact normative positions and breeds
    /// Stamp breed is used as the reference cell's breed when placing the pattern
    let stamps: Stamp list = [
        { PatternName = "hwss"; ReferencePosition = Position.create 33 20; ReferenceBreed = Breed.create 1 }
        { PatternName = "rpentomino"; ReferencePosition = Position.create 35 10; ReferenceBreed = Breed.create 2 }
        { PatternName = "pentadecathlon"; ReferencePosition = Position.create 25 30; ReferenceBreed = Breed.create 3 }
        { PatternName = "blinker"; ReferencePosition = Position.create 25 10; ReferenceBreed = Breed.create 4 }
        { PatternName = "pentadecathlon"; ReferencePosition = Position.create 20 15; ReferenceBreed = Breed.create 5 }
        { PatternName = "mwss"; ReferencePosition = Position.create 40 15; ReferenceBreed = Breed.create 6 }
        { PatternName = "glider"; ReferencePosition = Position.create 13 13; ReferenceBreed = Breed.create 7 }
        { PatternName = "toad"; ReferencePosition = Position.create 30 15; ReferenceBreed = Breed.create 8 }
        { PatternName = "gosperGun"; ReferencePosition = Position.create 12 10; ReferenceBreed = Breed.create 9 }
        { PatternName = "pulsar"; ReferencePosition = Position.create 13 24; ReferenceBreed = Breed.create 10 }
        { PatternName = "acorn"; ReferencePosition = Position.create 25 25; ReferenceBreed = Breed.create 11 }
        { PatternName = "beacon"; ReferencePosition = Position.create 25 20; ReferenceBreed = Breed.create 12 }
        { PatternName = "pulsar"; ReferencePosition = Position.create 36 30; ReferenceBreed = Breed.create 13 }
    ]
