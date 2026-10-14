module GameOfLife.Classic.Core.Tests.CoreTests

open Expecto
open GameOfLife.Classic.Core

// Helper for creating positions
let private p x y = Position.create x y

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
            let blinkerV = Set.ofList [p 0 0; p 0 1; p 0 2]
            let blinkerH = Set.ofList [p -1 1; p 0 1; p 1 1]
            
            let gen1 = Core.nextGenerationCells blinkerV
            Expect.equal gen1 blinkerH "Blinker vertical -> horizontal in 1 gen"
            
            let gen2 = Core.nextGenerationCells gen1
            Expect.equal gen2 blinkerV "Blinker horizontal -> vertical in 2 gens (period 2)"
        }

        test "still lifes are fixed points" {
            // Block
            let block = Set.ofList [p 0 0; p 1 0; p 0 1; p 1 1]
            Expect.equal (Core.nextGenerationCells block) block "Block is a fixed point"
            
            // Beehive
            let beehive = Set.ofList [
                p 0 1; p 1 0; p 2 0; p 3 1; p 2 2; p 1 2
            ]
            Expect.equal (Core.nextGenerationCells beehive) beehive "Beehive is a fixed point"
        }

        test "extinction patterns" {
            // Single cell dies immediately
            let singleCell = Set.ofList [p 0 0]
            let singleGen1 = Core.nextGenerationCells singleCell
            Expect.equal singleGen1 Set.empty "Single cell dies in next generation"
            
            // 2-cell horizontal line
            let twoCellLine = Set.ofList [p 0 0; p 1 0]
            let twoGen1 = Core.nextGenerationCells twoCellLine
            let twoGen2 = Core.nextGenerationCells twoGen1
            Expect.equal twoGen2 Set.empty "2-cell line dies within 2 generations"
        }

        test "nextGenerationCells is pure" {
            let cells = Set.ofList [
                p 0 0; p 1 0; p 2 0; p 0 1; p 1 2
            ]
            
            let gen1a = Core.nextGenerationCells cells
            let gen1b = Core.nextGenerationCells cells
            
            Expect.equal gen1a gen1b "Same input yields same output"
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
            let emptyCells = Set.empty
            Expect.equal (Core.statusMessage emptyCells) "No cell is alive" "0 cells message"
            
            let oneCell = Set.ofList [p 0 0]
            Expect.equal (Core.statusMessage oneCell) "1 cell is alive" "1 cell message"
            
            let twoCells = Set.ofList [p 0 0; p 1 0]
            Expect.equal (Core.statusMessage twoCells) "2 cells are alive" "2 cells message"
            
            let manyCells = Set.ofList [for i in 0..99 do p i 0]
            Expect.equal (Core.statusMessage manyCells) "100 cells are alive" "100 cells message"
        }

        test "translate offsets correctly" {
            let cells = Set.ofList [p 0 0; p 1 0; p 0 1]
            let translated = Core.translate cells (p 2 3)
            
            let expected = Set.ofList [
                p 2 3
                p 3 3
                p 2 4
            ]
            
            Expect.equal translated expected "Translation by (2,3) is correct"
        }

        test "translate preserves cardinality" {
            let cells = Set.ofList [
                for x in 0..9 do
                    for y in 0..9 do
                        p x y
            ]
            
            Expect.equal (Set.count cells) 100 "Original set has 100 cells"
            
            let translated = Core.translate cells (p 100 200)
            Expect.equal (Set.count translated) 100 "Translated set has same cardinality"
        }
    ]

// ============================================================================
// Group X - Cross-cutting
// ============================================================================

let xTests =
    testList "Group X - Cross-cutting" [
        test "initialCore defaults" {
            let core = Core.initialCore
            Expect.equal (Core.cellsOf core) Set.empty "Initial cells should be empty"
            Expect.isFalse (Core.statusVisibleOf core) "Initial status should not be visible"
        }

        test "field updates are non-destructive" {
            let original = Core.initialCore
            let cells = Set.ofList [p 0 0]
            let withNewCells = Core.withCells original cells
            
            Expect.equal (Core.cellsOf original) Set.empty "Original cells unchanged"
            Expect.equal (Core.cellsOf withNewCells) cells "New core has new cells"
            
            let withNewStatus = Core.withStatusVisible original true
            Expect.isFalse (Core.statusVisibleOf original) "Original status unchanged"
            Expect.isTrue (Core.statusVisibleOf withNewStatus) "New core has new status"
        }

        test "catalog cardinalities" {
            let cells1 = Set.ofList [p 0 0; p 1 0; p 2 0]
            let cells2 = Set.ofList [p 1 0; p 2 0; p 3 0]
            
            let unionCells = Set.union cells1 cells2
            Expect.equal (Set.count unionCells) 4 "Union of overlapping sets has correct cardinality"
        }
    ]

// ============================================================================
// Test Entry Point
// ============================================================================

[<Tests>]
let allCoreTests = testList "Core Tests" [cTests; nTests; gTests; pTests; xTests]
