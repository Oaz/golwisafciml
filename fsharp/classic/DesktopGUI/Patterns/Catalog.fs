namespace GameOfLife.Classic.DesktopGUI.Patterns

open GameOfLife.Classic.Core

module Catalog =
    /// All 19 patterns from Appendix A with exact normative coordinates
    let patterns: Map<string, Cells> = Map.ofList [
        ("blinker", Set.ofList [
            Position.create -1 0
            Position.create 0 0
            Position.create 1 0
        ])

        ("toad", Set.ofList [
            Position.create 0 0
            Position.create 1 0
            Position.create 2 0
            Position.create -1 1
            Position.create 0 1
            Position.create 1 1
        ])

        ("beacon", Set.ofList [
            Position.create -1 -1
            Position.create 0 -1
            Position.create -1 0
            Position.create 0 0
            Position.create 1 1
            Position.create 2 1
            Position.create 1 2
            Position.create 2 2
        ])

        ("pentadecathlon", Set.ofList [
            Position.create -2 -1
            Position.create 3 -1
            Position.create -4 0
            Position.create -3 0
            Position.create -1 0
            Position.create 0 0
            Position.create 1 0
            Position.create 2 0
            Position.create 4 0
            Position.create 5 0
            Position.create -2 1
            Position.create 3 1
        ])

        ("block", Set.ofList [
            Position.create 0 0
            Position.create 1 0
            Position.create 0 1
            Position.create 1 1
        ])

        ("beehive", Set.ofList [
            Position.create 0 -1
            Position.create 1 -1
            Position.create -1 0
            Position.create 2 0
            Position.create 0 1
            Position.create 1 1
        ])

        ("loaf", Set.ofList [
            Position.create 0 -1
            Position.create 1 -1
            Position.create -1 0
            Position.create 2 0
            Position.create 0 1
            Position.create 2 1
            Position.create 1 2
        ])

        ("boat", Set.ofList [
            Position.create -1 -1
            Position.create 0 -1
            Position.create -1 0
            Position.create 1 0
            Position.create 0 1
        ])

        ("tub", Set.ofList [
            Position.create 0 -1
            Position.create -1 0
            Position.create 1 0
            Position.create 0 1
        ])

        ("glider", Set.ofList [
            Position.create 0 -1
            Position.create 1 0
            Position.create -1 1
            Position.create 0 1
            Position.create 1 1
        ])

        ("lwss", Set.ofList [
            Position.create -1 -1
            Position.create 2 -1
            Position.create -2 0
            Position.create -2 1
            Position.create 2 1
            Position.create -2 2
            Position.create -1 2
            Position.create 0 2
            Position.create 1 2
        ])

        ("mwss", Set.ofList [
            Position.create 1 -2
            Position.create -1 -1
            Position.create 3 -1
            Position.create -2 0
            Position.create -2 1
            Position.create 3 1
            Position.create -2 2
            Position.create -1 2
            Position.create 0 2
            Position.create 1 2
            Position.create 2 2
        ])

        ("hwss", Set.ofList [
            Position.create 0 -2
            Position.create 1 -2
            Position.create -2 -1
            Position.create 3 -1
            Position.create -3 0
            Position.create -3 1
            Position.create 3 1
            Position.create -3 2
            Position.create -2 2
            Position.create -1 2
            Position.create 0 2
            Position.create 1 2
            Position.create 2 2
        ])

        ("rpentomino", Set.ofList [
            Position.create 0 -1
            Position.create 1 -1
            Position.create -1 0
            Position.create 0 0
            Position.create 0 1
        ])

        ("diehard", Set.ofList [
            Position.create 3 -1
            Position.create -3 0
            Position.create -2 0
            Position.create -2 1
            Position.create 2 1
            Position.create 3 1
            Position.create 4 1
        ])

        ("acorn", Set.ofList [
            Position.create -2 -1
            Position.create 0 0
            Position.create -3 1
            Position.create -2 1
            Position.create 1 1
            Position.create 2 1
            Position.create 3 1
        ])

        ("pulsar", Set.ofList [
            Position.create -4 -6
            Position.create -3 -6
            Position.create -2 -6
            Position.create 2 -6
            Position.create 3 -6
            Position.create 4 -6
            Position.create -6 -4
            Position.create -1 -4
            Position.create 1 -4
            Position.create 6 -4
            Position.create -6 -3
            Position.create -1 -3
            Position.create 1 -3
            Position.create 6 -3
            Position.create -6 -2
            Position.create -1 -2
            Position.create 1 -2
            Position.create 6 -2
            Position.create -4 -1
            Position.create -3 -1
            Position.create -2 -1
            Position.create 2 -1
            Position.create 3 -1
            Position.create 4 -1
            Position.create -4 1
            Position.create -3 1
            Position.create -2 1
            Position.create 2 1
            Position.create 3 1
            Position.create 4 1
            Position.create -6 2
            Position.create -1 2
            Position.create 1 2
            Position.create 6 2
            Position.create -6 3
            Position.create -1 3
            Position.create 1 3
            Position.create 6 3
            Position.create -6 4
            Position.create -1 4
            Position.create 1 4
            Position.create 6 4
            Position.create -4 6
            Position.create -3 6
            Position.create -2 6
            Position.create 2 6
            Position.create 3 6
            Position.create 4 6
        ])

        ("gosperGun", Set.ofList [
            Position.create 7 -4
            Position.create 5 -3
            Position.create 7 -3
            Position.create -5 -2
            Position.create -4 -2
            Position.create 3 -2
            Position.create 4 -2
            Position.create 17 -2
            Position.create 18 -2
            Position.create -6 -1
            Position.create -2 -1
            Position.create 3 -1
            Position.create 4 -1
            Position.create 17 -1
            Position.create 18 -1
            Position.create -17 0
            Position.create -16 0
            Position.create -7 0
            Position.create -1 0
            Position.create 3 0
            Position.create 4 0
            Position.create -17 1
            Position.create -16 1
            Position.create -7 1
            Position.create -3 1
            Position.create -1 1
            Position.create 0 1
            Position.create 5 1
            Position.create 7 1
            Position.create -7 2
            Position.create -1 2
            Position.create 7 2
            Position.create -6 3
            Position.create -2 3
            Position.create -5 4
            Position.create -4 4
        ])

        ("simkinGun", Set.ofList [
            Position.create -16 -10
            Position.create -15 -10
            Position.create -9 -10
            Position.create -8 -10
            Position.create -16 -9
            Position.create -15 -9
            Position.create -9 -9
            Position.create -8 -9
            Position.create -12 -7
            Position.create -11 -7
            Position.create -12 -6
            Position.create -11 -6
            Position.create 6 -1
            Position.create 7 -1
            Position.create 9 -1
            Position.create 10 -1
            Position.create 5 0
            Position.create 11 0
            Position.create 5 1
            Position.create 12 1
            Position.create 15 1
            Position.create 16 1
            Position.create 5 2
            Position.create 6 2
            Position.create 7 2
            Position.create 11 2
            Position.create 15 2
            Position.create 16 2
            Position.create 10 3
            Position.create 4 7
            Position.create 5 7
            Position.create 4 8
            Position.create 5 9
            Position.create 6 9
            Position.create 7 9
            Position.create 7 10
        ])
    ]

    /// Get pattern names in sorted order
    let patternNames: string list =
        Map.keys patterns |> Seq.sort |> Seq.toList
