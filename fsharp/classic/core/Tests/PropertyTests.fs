module GameOfLife.Classic.Core.Tests.PropertyTests

open Expecto
open GameOfLife.Classic.Core

// Helper for creating positions
let private p x y = Position.create x y

// ============================================================================
// Group G12 - Property-like tests
// ============================================================================

[<Tests>]
let allPropertyTests =
    testList "Property Tests" [
        testList "Group G12 - Property-like Tests" [
            test "candidates contains all cells" {
                let cells = Set.ofList [p 0 0; p 1 0; p 2 0; p 0 1]
                let candidates = Core.allCellsAndNeighbors cells
                
                for cell in cells do
                    Expect.isTrue (Set.contains cell candidates) (sprintf "Cell %A should be in candidates" cell)
            }

            test "candidates contains all neighbors" {
                let cells = Set.ofList [p 0 0; p 1 0]
                let candidates = Core.allCellsAndNeighbors cells
                
                for cell in cells do
                    let neighbors = Core.neighbors cell
                    for neighbor in neighbors do
                        Expect.isTrue (Set.contains neighbor candidates) (sprintf "Neighbor %A of cell %A should be in candidates" neighbor cell)
            }

            test "neighbors always returns 8 distinct positions" {
                let positions = [
                    p 0 0
                    p 100 200
                    p -10 -20
                    p 1000000 1000000
                ]
                
                for position in positions do
                    let n = Core.neighbors position
                    Expect.equal (Set.count n) 8 (sprintf "Neighbors of %A should have 8 elements" position)
            }

            test "neighbors does not include the original position" {
                let positions = [
                    p 0 0
                    p 1 1
                    p 100 200
                ]
                
                for position in positions do
                    let n = Core.neighbors position
                    Expect.isFalse (Set.contains position n) (sprintf "Neighbors of %A should not contain itself" position)
            }

            test "nextGenerationCells output is subset of candidates" {
                let cells = Set.ofList [p 0 0; p 1 0; p 0 1; p 1 1]
                let candidates = Core.allCellsAndNeighbors cells
                let nextGen = Core.nextGenerationCells cells
                
                Expect.isTrue (Set.isSubset nextGen candidates) "Next generation should be subset of candidates"
            }

            test "statusMessage count equals cell count" {
                let testCases = [
                    Set.empty, "No cell is alive"
                    Set.ofList [p 0 0], "1 cell is alive"
                    Set.ofList [p 0 0; p 1 0], "2 cells are alive"
                    Set.ofList [for i in 0..99 do p i 0], "100 cells are alive"
                ]
                
                for (cells, expectedMsg) in testCases do
                    Expect.equal (Core.statusMessage cells) expectedMsg (sprintf "Status message for %d cells" (Set.count cells))
            }

            test "translate preserves cardinality" {
                let cells = Set.ofList [
                    for x in 0..9 do
                        for y in 0..9 do
                            p x y
                ]
                
                let translated = Core.translate cells (p 100 200)
                Expect.equal (Set.count translated) (Set.count cells) "Translated set should have same cardinality"
            }

            test "translate is injective" {
                let cells = Set.ofList [p 0 0; p 1 1; p 2 2]
                let offset = p 10 20
                
                let translated = Core.translate cells offset
                Expect.equal (Set.count translated) (Set.count cells) "Translate should be injective"
            }

            test "translate by zero is identity" {
                let cells = Set.ofList [p 1 2; p 3 4; p 5 6]
                let zero = p 0 0
                
                let translated = Core.translate cells zero
                Expect.equal translated cells "Translate by (0,0) should be identity"
            }

            test "translate is compositional" {
                let cells = Set.ofList [p 0 0; p 1 1]
                let offset1 = p 2 3
                let offset2 = p 4 5
                
                let translatedOnce = Core.translate cells offset1
                let translatedTwice = Core.translate translatedOnce offset2
                
                let combinedOffset = p (offset1.X + offset2.X) (offset1.Y + offset2.Y)
                let translatedCombined = Core.translate cells combinedOffset
                
                Expect.equal translatedTwice translatedCombined "Translate should be compositional"
            }

            test "empty cells remain empty" {
                let empty = Set.empty
                let nextGen = Core.nextGenerationCells empty
                Expect.equal nextGen empty "Empty cells should remain empty"
            }
        ]
    ]
