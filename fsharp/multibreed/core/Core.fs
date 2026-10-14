namespace GameOfLife.Multibreed.Core

open System
open System.Collections.Concurrent

/// Represents a position with X and Y coordinates.
/// Uses Int32 coordinates which are practically unbounded for the spec's requirements.
/// Implements IComparable for use in F# Map, and CustomEquality/CustomComparison.
[<CustomEquality>]
[<CustomComparison>]
[<StructuredFormatDisplay("{StructuredFormatDisplay}")>]
type Position = { X: int; Y: int }
    with
    /// Static factory method for Position creation
    static member create x y = { X = x; Y = y }
    
    /// Implement IComparable for use in F# Map
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

/// Represents a breed value (0-15).
/// Uses a private discriminated union with smart constructor to ensure validity.
type Breed = private Breed of int

/// Module for Breed creation and access
module Breed = 
    /// Smart constructor ensures breed is in range 0-15 by masking with 0xF
    let create n = Breed (n &&& 0xF)
    
    /// Raw value accessor
    let value (Breed b) = b
    
    /// Convenience overloads
    let ofInt = create
    let toInt = value

/// Represents a cell with position and breed.
type Cell = { Position: Position; Breed: Breed }
    with
    /// Static factory method for Cell creation
    static member create x y breed = { Position = Position.create x y; Breed = breed }

/// Represents the live cells as a Map from Position to Breed.
/// This enforces at most one cell per position while tracking breeds.
type Cells = Map<Position, Breed>

/// Represents the neighbor count map: Position -> (count, breed)
type NeighborCounts = Map<Position, int * Breed>

/// Module alias for NeighborCounts type (for external access)
module NeighborCounts = 
    type T = NeighborCounts

/// Represents the core state of the Game of Life (v2).
type Core = {
    Cells: Cells
    StatusVisible: bool
    CurrentPattern: string
}

/// Module containing all core domain functions and operations
module Core =
    /// Private memoization cache for neighbor calculations
    let private neighborCache = ConcurrentDictionary<Position, Set<Position>>()

    /// Returns the initial core state with no cells, status not visible, and empty current pattern.
    let initialCore = {
        Cells = Map.empty
        StatusVisible = false
        CurrentPattern = ""
    }

    /// Returns a new core with the specified cells, keeping other fields unchanged.
    let withCells core cells = { core with Cells = cells }

    /// Returns a new core with the specified status visibility, keeping other fields unchanged.
    let withStatusVisible core visible = { core with StatusVisible = visible }

    /// Returns a new core with the specified current pattern name, keeping other fields unchanged.
    let withCurrentPattern core patternName = { core with CurrentPattern = patternName }

    /// Returns the cells from the core state.
    let cellsOf core = core.Cells

    /// Returns the status visibility from the core state.
    let statusVisibleOf core = core.StatusVisible

    /// Returns the current pattern name from the core state.
    let currentPatternOf core = core.CurrentPattern

    /// Returns the set of all 8 neighboring positions for a given position.
    let neighbors (position: Position) : Set<Position> =
        neighborCache.GetOrAdd(position, fun p ->
            Set.ofList [
                { X = p.X - 1; Y = p.Y - 1 }; { X = p.X; Y = p.Y - 1 }; { X = p.X + 1; Y = p.Y - 1 }
                { X = p.X - 1; Y = p.Y     };                          { X = p.X + 1; Y = p.Y     }
                { X = p.X - 1; Y = p.Y + 1 }; { X = p.X; Y = p.Y + 1 }; { X = p.X + 1; Y = p.Y + 1 }
            ])

    /// Determines if a cell should be alive in the next generation based on Conway's rules.
    let shouldLive (isAlive: bool) (liveNeighborCount: int) : bool =
        if isAlive then
            liveNeighborCount = 2 || liveNeighborCount = 3
        else
            liveNeighborCount = 3

    /// Cross-breeds two breeds: (a + b) mod 16.
    let cross_breed (a: Breed) (b: Breed) : Breed =
        Breed.create (Breed.value a + Breed.value b)

    /// Extracts the position from a cell, dropping the breed.
    let positionOf (cell: Cell) : Position = cell.Position

    /// Bump function: updates the neighbor count map for a position with a given breed.
    let bump (counts: NeighborCounts) (position: Position) (breed: Breed) : NeighborCounts =
        match Map.tryFind position counts with
        | Some (count, existingBreed) ->
            let newCount = count + 1
            let newBreed = cross_breed existingBreed breed
            Map.add position (newCount, newBreed) counts
        | None ->
            Map.add position (1, breed) counts

    /// Computes the next generation of cells using the persistent-map fold strategy.
    let nextGenerationCells (cells: Cells) : Cells =
        // Get the set of positions that are currently alive
        let livingPositions = Set.ofArray (Map.keys cells |> Array.ofSeq)
        
        // Build the neighbor count map
        let counts : NeighborCounts =
            List.fold (fun (acc: NeighborCounts) (pos, breedValue) ->
                let neighborsList = Set.toList (neighbors pos)
                List.fold (fun (acc': NeighborCounts) (neighborPos: Position) ->
                    bump acc' neighborPos breedValue
                ) acc neighborsList
            ) (Map.empty : NeighborCounts) (Map.toList cells)
        
        // Determine which positions survive or are born
        let resultMap = Map.empty : Cells
        List.fold (fun (acc: Cells) (pos, (count, breed)) ->
            let isAlive = Set.contains pos livingPositions
            if shouldLive isAlive count then
                Map.add pos breed acc
            else
                acc
        ) resultMap (Map.toList counts)

    /// Translates a set of cells (pattern) by a reference cell.
    let translate (points: Cells) (reference: Cell) : Cells =
        Map.toList points
        |> List.map (fun (pos, breed) ->
            let newPos = Position.create (pos.X + reference.Position.X) (pos.Y + reference.Position.Y)
            (newPos, cross_breed breed reference.Breed)
        )
        |> Map.ofList

    /// Returns the status message for a given set of cells.
    let statusMessage (cells: Cells) : string =
        let n = Map.count cells
        match n with
        | 0 -> "No cell is alive"
        | 1 -> "1 cell is alive"
        | _ -> sprintf "%d cells are alive" n
