namespace GameOfLife.Classic.Core

open System
open System.Collections.Concurrent

/// Represents a position with X and Y coordinates.
/// Uses Int32 coordinates which are practically unbounded for the spec's requirements.
/// Implements IComparable for use in F# Set, and CustomEquality/CustomComparison for custom GetHashCode/Equals/Compare.
[<CustomEquality>]
[<CustomComparison>]
[<StructuredFormatDisplay("{StructuredFormatDisplay}")>]
type Position = { X: int; Y: int }
    with
    /// Static factory method for Position creation
    static member create x y = { X = x; Y = y }
    
    /// Implement IComparable for use in F# Set
    interface System.IComparable with
        member this.CompareTo(other) =
            match other with
            | :? Position as p ->
                let cmpX = compare this.X p.X
                if cmpX <> 0 then cmpX else compare this.Y p.Y
            | _ -> invalidArg "other" "Cannot compare Position with non-Position"
    
    interface System.IComparable<Position> with
        member this.CompareTo(other) =
            let cmpX = compare this.X other.X
            if cmpX <> 0 then cmpX else compare this.Y other.Y
    
    /// Structural equality based on X and Y coordinates
    override this.Equals(other) =
        match other with
        | :? Position as p -> this.X = p.X && this.Y = p.Y
        | _ -> false
    
    /// Hash code based on X and Y coordinates
    override this.GetHashCode() =
        HashCode.Combine(this.X, this.Y)

    /// String representation for display
    override this.ToString() = sprintf "{ X = %d; Y = %d }" this.X this.Y
    
    member private this.StructuredFormatDisplay = this.ToString()

/// Represents a set of cell positions.
/// Uses F# Set for immutable, order-insensitive collection with structural equality.
type Cells = Set<Position>

/// Represents the core state of the Game of Life.
/// Contains the set of live cells and the visibility status of the message.
type Core = {
    Cells: Cells
    StatusVisible: bool
}

/// Module containing all core domain functions and operations
[<AutoOpen>]
module Core =
    /// Private memoization cache for neighbor calculations
    let private neighborCache = ConcurrentDictionary<Position, Cells>()

    /// Returns the initial core state with no cells and status not visible.
    let initialCore = {
        Cells = Set.empty
        StatusVisible = false
    }

    /// Returns a new core with the specified cells, keeping status visibility unchanged.
    let withCells core cells = { core with Cells = cells }

    /// Returns a new core with the specified status visibility, keeping cells unchanged.
    let withStatusVisible core visible = { core with StatusVisible = visible }

    /// Returns the cells from the core state.
    let cellsOf core = core.Cells

    /// Returns the status visibility from the core state.
    let statusVisibleOf core = core.StatusVisible

    /// Returns the set of all 8 neighboring positions for a given position.
    /// Uses memoization via ConcurrentDictionary for thread-safe, observationally pure caching.
    let neighbors (position: Position) : Cells =
        neighborCache.GetOrAdd(position, fun p ->
            Set.ofList [
                { X = p.X - 1; Y = p.Y - 1 }; { X = p.X; Y = p.Y - 1 }; { X = p.X + 1; Y = p.Y - 1 }
                { X = p.X - 1; Y = p.Y     };                          { X = p.X + 1; Y = p.Y     }
                { X = p.X - 1; Y = p.Y + 1 }; { X = p.X; Y = p.Y + 1 }; { X = p.X + 1; Y = p.Y + 1 }
            ])

    /// Determines if a cell should be alive in the next generation based on Conway's rules.
    /// - A live cell with 2 or 3 live neighbors survives
    /// - A dead cell with exactly 3 live neighbors becomes alive
    /// - All other cases result in death
    let shouldLive (isAlive: bool) (liveNeighborCount: int) : bool =
        if isAlive then
            liveNeighborCount = 2 || liveNeighborCount = 3
        else
            liveNeighborCount = 3

    /// Returns all cells and their neighbors as a candidate set for the next generation.
    /// This implements the candidate-set filtering strategy for v1.
    let allCellsAndNeighbors (cells: Cells) : Cells =
        let allPositions =
            seq {
                for cell in cells do
                    yield cell
                    for neighbor in (neighbors cell) do
                        yield neighbor
            }
        Set.ofSeq allPositions

    /// Computes the next generation of cells using the candidate-set filtering strategy.
    /// For each candidate position (all cells and their neighbors), determines if it should be alive
    /// based on the current cell set and Conway's rules.
    let nextGenerationCells (cells: Cells) : Cells =
        let candidates = allCellsAndNeighbors cells
        Set.filter (fun position ->
            let isAlive = Set.contains position cells
            let liveNeighborCount =
                (neighbors position) |> Set.filter (fun n -> Set.contains n cells) |> Set.count
            shouldLive isAlive liveNeighborCount
        ) candidates

    /// Translates a set of cells by a given position offset.
    /// Each cell's coordinates are added to the corresponding coordinates of the offset position.
    let translate (points: Cells) (p: Position) : Cells =
        Set.map (fun q -> { X = q.X + p.X; Y = q.Y + p.Y }) points

    /// Returns the status message for a given set of cells.
    /// Uses the normative text from spec §2.6.
    let statusMessage (cells: Cells) : string =
        let n = Set.count cells
        match n with
        | 0 -> "No cell is alive"
        | 1 -> "1 cell is alive"
        | _ -> sprintf "%d cells are alive" n
