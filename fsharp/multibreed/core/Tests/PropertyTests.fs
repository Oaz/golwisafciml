module GameOfLife.Multibreed.Core.Tests.PropertyTests

open Expecto
open GameOfLife.Multibreed.Core

// Helper for creating positions
let private p x y = Position.create x y

// Helper for creating breeds
let private b n = Breed.create n

// ============================================================================
// Group G12 - Property-like tests
// ============================================================================

[<Tests>]
let allPropertyTests =
    testList "Property Tests" [
        testList "Group G12 - Property-like Tests" [
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

            test "statusMessage count equals cell count" {
                let testCases = [
                    Map.empty, "No cell is alive"
                    Map.ofList [(p 0 0, b 0)], "1 cell is alive"
                    Map.ofList [(p 0 0, b 0); (p 1 0, b 0)], "2 cells are alive"
                    Map.ofList [for i in 0..99 do (p i 0, b 0)], "100 cells are alive"
                ]
                
                for (cells, expectedMsg) in testCases do
                    Expect.equal (Core.statusMessage cells) expectedMsg (sprintf "Status message for %d cells" (Map.count cells))
            }

            test "translate preserves cardinality" {
                let cells = Map.ofList [
                    for x in 0..9 do
                        for y in 0..9 do
                            (p x y, b 0)
                ]
                
                let reference = Cell.create 100 200 (b 0)
                let translated = Core.translate cells reference
                Expect.equal (Map.count translated) (Map.count cells) "Translated map should have same cardinality"
            }

            test "translate is injective" {
                let cells = Map.ofList [
                    (p 0 0, b 5)
                    (p 1 1, b 3)
                    (p 2 2, b 7)
                ]
                
                let reference = Cell.create 10 20 (b 11)
                let translated = Core.translate cells reference
                Expect.equal (Map.count translated) (Map.count cells) "Translate should be injective"
            }

            test "translate by zero is identity" {
                let cells = Map.ofList [
                    (p 1 2, b 0)
                    (p 3 4, b 0)
                    (p 5 6, b 0)
                ]
                let zero = Cell.create 0 0 (b 0)
                
                let translated = Core.translate cells zero
                Expect.equal (Map.count translated) (Map.count cells) "Translate by (0,0) should preserve count"
                
                // Check positions and breeds
                for (pos, breed) in Map.toList cells do
                    let expectedPos = Position.create (pos.X + 0) (pos.Y + 0)
                    let expectedBreed = Core.cross_breed breed (b 0)
                    match Map.tryFind expectedPos translated with
                    | Some actualBreed ->
                        Expect.equal (Breed.value actualBreed) (Breed.value expectedBreed) (sprintf "Breed at %A should match" pos)
                    | None -> failtest (sprintf "Expected position %A in translated" expectedPos)
            }

            test "translate is compositional" {
                let cells = Map.ofList [
                    (p 0 0, b 0)
                    (p 1 1, b 0)
                ]
                let offset1 = Cell.create 2 3 (b 0)
                let offset2 = Cell.create 4 5 (b 0)
                
                let translatedOnce = Core.translate cells offset1
                let translatedTwice = Core.translate translatedOnce offset2
                
                let combinedOffset = Cell.create (2 + 4) (3 + 5) (b 0)
                let translatedCombined = Core.translate cells combinedOffset
                
                Expect.equal (Map.count translatedTwice) (Map.count translatedCombined) "Translate should be compositional"
                
                // Check positions match
                let positionsTwice = Set.ofSeq (Map.keys translatedTwice)
                let positionsCombined = Set.ofSeq (Map.keys translatedCombined)
                Expect.equal positionsTwice positionsCombined "Position sets should match"
            }

            test "empty cells remain empty" {
                let empty : Cells = Map.empty
                let nextGen = Core.nextGenerationCells empty
                Expect.equal (Map.count nextGen) 0 "Empty cells should remain empty"
            }

            test "bump produces correct counts" {
                let empty : NeighborCounts = Map.empty
                
                // Bump same position multiple times
                let afterThree = Core.bump (Core.bump (Core.bump empty (p 0 0) (b 1)) (p 0 0) (b 2)) (p 0 0) (b 4)
                
                match Map.tryFind (p 0 0) afterThree with
                | Some (count, breed) ->
                    Expect.equal count 3 "Count should be 3 after three bumps"
                    Expect.equal (Breed.value breed) 7 "Breed should be (1+2+4) mod 16 = 7"
                | None -> failtest "Position should be in map"
            }

            test "cross breed is commutative and associative" {
                // Test various combinations
                let values = [1; 2; 4; 8]
                
                // All permutations should give the same result
                let total = List.sum values
                let expected = b (total % 16)
                
                let result1 = List.fold (fun acc x -> Core.cross_breed acc (b x)) (b 0) values
                Expect.equal (Breed.value result1) (Breed.value expected) "Fold should give correct result"
                
                // Different order
                let reversed = List.rev values
                let result2 = List.fold (fun acc x -> Core.cross_breed acc (b x)) (b 0) reversed
                Expect.equal (Breed.value result2) (Breed.value expected) "Reverse fold should give same result"
            }

            test "positionOf projection is consistent" {
                for x in 0..10 do
                    for y in 0..10 do
                        for breedVal in 0..15 do
                            let cell = Cell.create x y (b breedVal)
                            let pos = Core.positionOf cell
                            Expect.equal pos.X x (sprintf "positionOf X for (%d, %d, %d)" x y breedVal)
                            Expect.equal pos.Y y (sprintf "positionOf Y for (%d, %d, %d)" x y breedVal)
            }
        ]
    ]
