module GameOfLife.Multibreed.Core.Tests.CoreTests

open Expecto
open GameOfLife.Multibreed.Core

// Helper for creating positions
let private p x y = Position.create x y

// Helper for creating breeds
let private b n = Breed.create n

// ============================================================================
// Group C - Cell algebra
// ============================================================================

let cTests =
    testList "Group C - Cell Algebra" [
        test "Position construction and accessor round-trip" {
            let positions = [
                (0, 0)
                (1, 1)
                (-1, -1)
                (100, 200)
                (-100, -200)
                (2147483647, 2147483647)
                (-2147483648, -2147483648)
            ]
            
            for (x, y) in positions do
                let position = p x y
                Expect.equal position.X x (sprintf "X coordinate for (%d, %d)" x y)
                Expect.equal position.Y y (sprintf "Y coordinate for (%d, %d)" x y)
        }

        test "Breed construction and value round-trip" {
            for n in 0..15 do
                let breed = b n
                Expect.equal (Breed.value breed) n (sprintf "Breed value for %d" n)
            
            // Test wrapping with smart constructor
            Expect.equal (Breed.value (b 16)) 0 "Breed 16 wraps to 0"
            Expect.equal (Breed.value (b 17)) 1 "Breed 17 wraps to 1"
            Expect.equal (Breed.value (b 31)) 15 "Breed 31 wraps to 15"
            Expect.equal (Breed.value (b (-1))) 15 "Breed -1 wraps to 15"
        }

        test "cross breed" {
            // Basic cases
            Expect.equal (Breed.value (Core.cross_breed (b 0) (b 0))) 0 "0 cross 0 = 0"
            Expect.equal (Breed.value (Core.cross_breed (b 8) (b 9))) 1 "8 cross 9 = 1 (17 mod 16 = 1)"
            Expect.equal (Breed.value (Core.cross_breed (b 15) (b 15))) 14 "15 cross 15 = 14 (30 mod 16 = 14)"
            
            // Commutative
            Expect.equal (Core.cross_breed (b 3) (b 5)) (Core.cross_breed (b 5) (b 3)) "Cross breed is commutative"
            
            // Associative: (a + b + c) mod 16 = ((a + b) mod 16 + c) mod 16
            let result1 = Core.cross_breed (Core.cross_breed (b 3) (b 5)) (b 9)
            let result2 = Core.cross_breed (b 3) (Core.cross_breed (b 5) (b 9))
            Expect.equal (Breed.value result1) (Breed.value result2) "Cross breed is associative"
            
            // Folding [3,5,9,12] in any order gives same result
            let total = 3 + 5 + 9 + 12
            let expectedBreed = b (total % 16)
            
            let resultA = Core.cross_breed (Core.cross_breed (Core.cross_breed (b 3) (b 5)) (b 9)) (b 12)
            let resultB = Core.cross_breed (Core.cross_breed (b 3) (b 5)) (Core.cross_breed (b 9) (b 12))
            let resultC = Core.cross_breed (b 3) (Core.cross_breed (b 5) (Core.cross_breed (b 9) (b 12)))
            
            Expect.equal (Breed.value resultA) (Breed.value expectedBreed) "Fold order A gives correct result"
            Expect.equal (Breed.value resultB) (Breed.value expectedBreed) "Fold order B gives correct result"
            Expect.equal (Breed.value resultC) (Breed.value expectedBreed) "Fold order C gives correct result"
        }

        test "positionOf projection" {
            let cell = Cell.create 5 10 (b 3)
            let pos = Core.positionOf cell
            Expect.equal pos.X 5 "positionOf X should be 5"
            Expect.equal pos.Y 10 "positionOf Y should be 10"
        }
    ]

// ============================================================================
// Group N - Neighbors and counting
// ============================================================================

let nTests =
    testList "Group N - Neighbors and Counting" [
        test "neighbors returns exactly 8 distinct positions" {
            let center = p 0 0
            let n = Core.neighbors center
            Expect.equal (Set.count n) 8 "Should have exactly 8 neighbors"
            
            let expected = Set.ofList [
                p -1 -1; p 0 -1; p 1 -1
                p -1  0;           p 1  0
                p -1  1; p 0  1; p 1  1
            ]
            Expect.equal n expected "Should contain all 8 neighboring positions"
        }

        test "neighbor cache count lookup works correctly" {
            let center = p 0 0
            let n = Core.neighbors center
            Expect.equal (Set.count n) 8 "Should have exactly 8 neighbors"
            
            let n2 = Core.neighbors center
            Expect.equal (Set.count n2) 8 "Cached result should also have 8 neighbors"
            Expect.equal n n2 "Cached result should equal original"
        }

        test "neighborCache does not mutate inputs" {
            let position = p 5 5
            let originalHash = position.GetHashCode()
            
            for _ in 1..10 do
                Core.neighbors position |> ignore
            
            Expect.equal position.X 5 "X should be unchanged"
            Expect.equal position.Y 5 "Y should be unchanged"
            Expect.equal (position.GetHashCode()) originalHash "Hash code should be unchanged"
        }

        test "memoized neighbors is observationally pure" {
            for x in -10..10 do
                for y in -10..10 do
                    let position = p x y
                    let n1 = Core.neighbors position
                    let n2 = Core.neighbors position
                    Expect.equal n1 n2 (sprintf "Neighbors should be equal for (%d, %d)" x y)
        }

        test "bump function increments count and cross-breeds" {
            // Start with empty map
            let empty : NeighborCounts = Map.empty
            
            // First bump for position (5,2) with breed 0
            let afterFirst = Core.bump empty (p 5 2) (b 0)
            match Map.tryFind (p 5 2) afterFirst with
            | Some (count, breed) ->
                Expect.equal count 1 "First bump should have count 1"
                Expect.equal (Breed.value breed) 0 "First bump should have breed 0"
            | None -> failtest "Position should be in map after first bump"
            
            // Second bump for same position with breed 0
            let afterSecond = Core.bump afterFirst (p 5 2) (b 0)
            match Map.tryFind (p 5 2) afterSecond with
            | Some (count, breed) ->
                Expect.equal count 2 "Second bump should have count 2"
                Expect.equal (Breed.value breed) 0 "Cross-breed of 0 and 0 should be 0"
            | None -> failtest "Position should be in map after second bump"
            
            // Bump for (9,1) with breed 0 four times
            let afterFour = List.fold (fun acc _ -> Core.bump acc (p 9 1) (b 0)) afterFirst [1..4]
            match Map.tryFind (p 9 1) afterFour with
            | Some (count, breed) ->
                Expect.equal count 4 "Four bumps should have count 4"
                Expect.equal (Breed.value breed) 0 "Cross-breed of 0+0+0+0 should be 0"
            | None -> failtest "Position should be in map"
            
            // Fresh position with breed 5 then 9
            let afterTwoBreeds = Core.bump (Core.bump empty (p 1 1) (b 5)) (p 1 1) (b 9)
            match Map.tryFind (p 1 1) afterTwoBreeds with
            | Some (count, breed) ->
                Expect.equal count 2 "Should have count 2"
                Expect.equal (Breed.value breed) 14 "Cross-breed of 5 and 9 should be 14"
            | None -> failtest "Position should be in map"
        }

        test "bump immutability" {
            let empty : NeighborCounts = Map.empty
            let afterBump = Core.bump empty (p 5 2) (b 3)
            
            Expect.equal (Map.count empty) 0 "Original map should still be empty"
            Expect.equal (Map.count afterBump) 1 "New map should have 1 entry"
            
            let afterSecondBump = Core.bump afterBump (p 5 2) (b 5)
            Expect.equal (Map.count afterBump) 1 "First map should still have 1 entry"
            Expect.equal (Map.count afterSecondBump) 1 "Second map should have 1 entry"
        }
    ]

// ============================================================================
// Group G - Life rules
// ============================================================================

let gTests =
    testList "Group G - Life Rules" [
        test "shouldLive truth table" {
            // Live cell cases
            Expect.isFalse (Core.shouldLive true 0) "Live cell with 0 neighbors dies"
            Expect.isFalse (Core.shouldLive true 1) "Live cell with 1 neighbor dies"
            Expect.isTrue (Core.shouldLive true 2) "Live cell with 2 neighbors survives"
            Expect.isTrue (Core.shouldLive true 3) "Live cell with 3 neighbors survives"
            Expect.isFalse (Core.shouldLive true 4) "Live cell with 4 neighbors dies"
            Expect.isFalse (Core.shouldLive true 5) "Live cell with 5 neighbors dies"
            Expect.isFalse (Core.shouldLive true 6) "Live cell with 6 neighbors dies"
            Expect.isFalse (Core.shouldLive true 7) "Live cell with 7 neighbors dies"
            Expect.isFalse (Core.shouldLive true 8) "Live cell with 8 neighbors dies"
            
            // Dead cell cases
            Expect.isFalse (Core.shouldLive false 0) "Dead cell with 0 neighbors stays dead"
            Expect.isFalse (Core.shouldLive false 1) "Dead cell with 1 neighbor stays dead"
            Expect.isFalse (Core.shouldLive false 2) "Dead cell with 2 neighbors stays dead"
            Expect.isTrue (Core.shouldLive false 3) "Dead cell with 3 neighbors is born"
            Expect.isFalse (Core.shouldLive false 4) "Dead cell with 4 neighbors stays dead"
            Expect.isFalse (Core.shouldLive false 5) "Dead cell with 5 neighbors stays dead"
            Expect.isFalse (Core.shouldLive false 6) "Dead cell with 6 neighbors stays dead"
            Expect.isFalse (Core.shouldLive false 7) "Dead cell with 7 neighbors stays dead"
            Expect.isFalse (Core.shouldLive false 8) "Dead cell with 8 neighbors stays dead"
        }

        test "oscillator periods" {
            // Blinker (vertical): period 2
            let blinkerV = Map.ofList [
                (p 0 0, b 0)
                (p 0 1, b 0)
                (p 0 2, b 0)
            ]
            
            // Blinker (horizontal)
            let blinkerH = Map.ofList [
                (p -1 1, b 0)
                (p 0 1, b 0)
                (p 1 1, b 0)
            ]
            
            let gen1 = Core.nextGenerationCells blinkerV
            
            // Compare as sets of positions (ignoring breed for comparison)
            let positionsEqual (cells1: Cells) (cells2: Cells) =
                let positions1 = Set.ofSeq (Map.keys cells1)
                let positions2 = Set.ofSeq (Map.keys cells2)
                positions1 = positions2
            
            Expect.isTrue (positionsEqual gen1 blinkerH) "Blinker vertical -> horizontal in 1 gen"
            
            let gen2 = Core.nextGenerationCells gen1
            Expect.isTrue (positionsEqual gen2 blinkerV) "Blinker horizontal -> vertical in 2 gens (period 2)"
        }

        test "still lifes are fixed points" {
            let block = Map.ofList [
                (p 0 0, b 0)
                (p 1 0, b 0)
                (p 0 1, b 0)
                (p 1 1, b 0)
            ]
            
            let positionsEqual (cells1: Cells) (cells2: Cells) =
                let positions1 = Set.ofSeq (Map.keys cells1)
                let positions2 = Set.ofSeq (Map.keys cells2)
                positions1 = positions2
            
            Expect.isTrue (positionsEqual (Core.nextGenerationCells block) block) "Block is a fixed point"
        }

        test "extinction patterns" {
            // Single cell dies immediately
            let singleCell = Map.ofList [(p 0 0, b 0)]
            let singleGen1 = Core.nextGenerationCells singleCell
            Expect.equal (Map.count singleGen1) 0 "Single cell dies in next generation"
            
            // 2-cell horizontal line
            let twoCellLine = Map.ofList [
                (p 0 0, b 0)
                (p 1 0, b 0)
            ]
            let twoGen1 = Core.nextGenerationCells twoCellLine
            let twoGen2 = Core.nextGenerationCells twoGen1
            Expect.equal (Map.count twoGen2) 0 "2-cell line dies within 2 generations"
        }

        test "nextGenerationCells is pure" {
            let cells = Map.ofList [
                (p 0 0, b 5)
                (p 1 0, b 3)
                (p 2 0, b 7)
                (p 0 1, b 2)
                (p 1 2, b 11)
            ]
            
            let gen1a = Core.nextGenerationCells cells
            let gen1b = Core.nextGenerationCells cells
            
            // Compare as sets of positions and breeds
            let cellsEqual (cells1: Cells) (cells2: Cells) =
                Map.count cells1 = Map.count cells2 &&
                Map.forall (fun pos breed1 -> 
                    match Map.tryFind pos cells2 with
                    | Some breed2 -> Breed.value breed1 = Breed.value breed2
                    | None -> false
                ) cells1
            
            Expect.isTrue (cellsEqual gen1a gen1b) "Same input yields same output"
        }

        test "newborn breed inheritance" {
            // Create a pattern where a cell will be born from neighbors with different breeds
            // Pattern: three cells in a row, with breeds 1, 2, 4
            // The cell below the middle should be born with breed (1+2+4) mod 16 = 7
            let cells = Map.ofList [
                (p 0 0, b 1)
                (p 1 0, b 2)
                (p 2 0, b 4)
            ]
            
            let nextGen = Core.nextGenerationCells cells
            
            // Check if position (1, -1) is in the next generation
            match Map.tryFind (p 1 -1) nextGen with
            | Some breed ->
                // This cell should be born from neighbors at (0,0), (1,0), (2,0)
                // Breed = (1 + 2 + 4) mod 16 = 7
                Expect.equal (Breed.value breed) 7 "Newborn cell should have breed 7"
            | None -> 
                // If (1,-1) is not the position, try (1,1)
                match Map.tryFind (p 1 1) nextGen with
                | Some breed ->
                    Expect.equal (Breed.value breed) 7 "Newborn cell should have breed 7"
                | None -> failtest "Expected newborn cell at position (1, -1) or (1, 1)"
        }

        test "nextGenerationCells preserves position set (G10)" {
            // This test verifies that v2 produces the same position set as v1
            let v1StyleCells = Set.ofList [p 0 0; p 1 0; p 0 1; p 1 1] // Block
            
            // Convert to v2 style (all breed 0)
            let cells = Map.ofList [
                (p 0 0, b 0)
                (p 1 0, b 0)
                (p 0 1, b 0)
                (p 1 1, b 0)
            ]
            
            let nextGen = Core.nextGenerationCells cells
            
            // The positions should be the same as v1
            let positions = Set.ofSeq (Map.keys nextGen)
            Expect.equal positions v1StyleCells "Position set should match v1 (block is fixed point)"
        }

        test "unbounded coordinates" {
            let largePos1 = p 1000000 1000000
            let n1 = Core.neighbors largePos1
            Expect.equal (Set.count n1) 8 "Neighbors count is correct for large positive coordinates"
            
            let largePos2 = p (-1000000) (-1000000)
            let n2 = Core.neighbors largePos2
            Expect.equal (Set.count n2) 8 "Neighbors count is correct for large negative coordinates"
        }
    ]

// ============================================================================
// Group P - Patterns, translation, status
// ============================================================================

let pTests =
    testList "Group P - Patterns, Translation, Status" [
        test "statusMessage grammar" {
            let emptyCells : Cells = Map.empty
            Expect.equal (Core.statusMessage emptyCells) "No cell is alive" "0 cells message"
            
            let oneCell = Map.ofList [(p 0 0, b 0)]
            Expect.equal (Core.statusMessage oneCell) "1 cell is alive" "1 cell message"
            
            let twoCells = Map.ofList [
                (p 0 0, b 0)
                (p 1 0, b 0)
            ]
            Expect.equal (Core.statusMessage twoCells) "2 cells are alive" "2 cells message"
            
            let manyCells = Map.ofList [for i in 0..99 do (p i 0, b 0)]
            Expect.equal (Core.statusMessage manyCells) "100 cells are alive" "100 cells message"
        }

        test "translate offsets correctly" {
            let cells = Map.ofList [
                (p 0 0, b 5)
                (p 1 0, b 3)
                (p 0 1, b 7)
            ]
            
            let reference = Cell.create 2 3 (b 11)
            let translated = Core.translate cells reference
            
            // Check the translated positions and breeds
            Expect.equal (Map.count translated) 3 "Should have 3 cells"
            
            match Map.tryFind (p 2 3) translated with
            | Some breed ->
                Expect.equal (Breed.value breed) (Breed.value (Core.cross_breed (b 5) (b 11))) "Breed should be cross-bred"
            | None -> failtest "Expected cell at (2, 3)"
            
            match Map.tryFind (p 3 3) translated with
            | Some breed ->
                Expect.equal (Breed.value breed) (Breed.value (Core.cross_breed (b 3) (b 11))) "Breed should be cross-bred"
            | None -> failtest "Expected cell at (3, 3)"
            
            match Map.tryFind (p 2 4) translated with
            | Some breed ->
                Expect.equal (Breed.value breed) (Breed.value (Core.cross_breed (b 7) (b 11))) "Breed should be cross-bred"
            | None -> failtest "Expected cell at (2, 4)"
        }

        test "translate preserves cardinality" {
            let cells = Map.ofList [
                for x in 0..9 do
                    for y in 0..9 do
                        (p x y, b 0)
            ]
            
            Expect.equal (Map.count cells) 100 "Original map has 100 cells"
            
            let reference = Cell.create 100 200 (b 0)
            let translated = Core.translate cells reference
            Expect.equal (Map.count translated) 100 "Translated map has same cardinality"
        }

        test "translate is a bijection on shapes" {
            let cells = Map.ofList [
                (p 0 0, b 5)
                (p 1 0, b 3)
                (p 0 1, b 7)
            ]
            
            let reference = Cell.create 10 20 (b 11)
            let translated = Core.translate cells reference
            
            Expect.equal (Map.count translated) (Map.count cells) "Cardinality should be preserved"
            
            // Check that relative positions are preserved
            let positions = Set.ofSeq (Map.keys translated)
            let expectedPositions = Set.ofList [p 10 20; p 11 20; p 10 21]
            Expect.equal positions expectedPositions "Relative positions should be preserved"
        }
    ]

// ============================================================================
// Group X - Cross-cutting
// ============================================================================

let xTests =
    testList "Group X - Cross-cutting" [
        test "initialCore defaults" {
            let core = Core.initialCore
            Expect.equal (Map.count (Core.cellsOf core)) 0 "Initial cells should be empty"
            Expect.isFalse (Core.statusVisibleOf core) "Initial status should not be visible"
            Expect.equal (Core.currentPatternOf core) "" "Initial current pattern should be empty string"
        }

        test "field updates are non-destructive" {
            let original = Core.initialCore
            let cells = Map.ofList [(p 0 0, b 0)]
            let withNewCells = Core.withCells original cells
            
            Expect.equal (Map.count (Core.cellsOf original)) 0 "Original cells unchanged"
            Expect.equal (Map.count (Core.cellsOf withNewCells)) 1 "New core has new cells"
            
            let withNewStatus = Core.withStatusVisible original true
            Expect.isFalse (Core.statusVisibleOf original) "Original status unchanged"
            Expect.isTrue (Core.statusVisibleOf withNewStatus) "New core has new status"
            
            let withNewPattern = Core.withCurrentPattern original "test"
            Expect.equal (Core.currentPatternOf original) "" "Original pattern unchanged"
            Expect.equal (Core.currentPatternOf withNewPattern) "test" "New core has new pattern"
        }

        test "catalog cardinalities" {
            let cells1 = Map.ofList [(p 0 0, b 0); (p 1 0, b 0); (p 2 0, b 0)]
            let cells2 = Map.ofList [(p 1 0, b 0); (p 2 0, b 0); (p 3 0, b 0)]
            
            // Union manually
            let unionCells = List.fold (fun acc (pos, breed) -> Map.add pos breed acc) cells1 (Map.toList cells2)
            Expect.equal (Map.count unionCells) 4 "Union of overlapping maps has correct cardinality"
        }
    ]

// ============================================================================
// Test Entry Point
// ============================================================================

[<Tests>]
let allCoreTests = testList "Core Tests" [cTests; nTests; gTests; pTests; xTests]
