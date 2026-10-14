namespace GameOfLife.Multibreed.DesktopGUI

open GameOfLife.Multibreed.Core
open LiteDB
open System

/// Module for pattern storage using LiteDB
/// Primary source is the in-memory Catalog, with LiteDB for persistence
module PatternStore =
    /// Database file path
    let private dbPath = "patterns.db"
    /// Collection name for patterns
    let private collectionName = "patterns"
    
    /// Pattern record for LiteDB storage
    type private PatternRecord() =
        member val Id = "" with get, set
        member val Cells = [||] with get, set
    
    /// Convert Cells (Map<Position, Breed>) to array of cell records
    let private cellsToArray (cells: Cells) : {| X: int; Y: int; Breed: int |} array =
        cells
        |> Map.toArray
        |> Array.map (fun (pos, breed) -> {| X = pos.X; Y = pos.Y; Breed = Breed.value breed |})
    
    /// Convert array of cell records to Cells (Map<Position, Breed>)
    let private arrayToCells (records: {| X: int; Y: int; Breed: int |} array) : Cells =
        records
        |> Array.map (fun r -> (Position.create r.X r.Y, Breed.create r.Breed))
        |> Map.ofArray
    
    /// Get a LiteDB connection
    let private getConnection () : LiteDatabase =
        new LiteDatabase(dbPath)
    
    /// Get pattern cells from in-memory Catalog (primary source)
    /// Falls back to database if not in Catalog
    let getPatternCells (name: string) : Cells =
        // First try in-memory Catalog
        match Map.tryFind name Patterns.Catalog.patterns with
        | Some cells -> cells
        | None ->
            // Fall back to database
            use db = getConnection()
            let collector = db.GetCollection<PatternRecord>(collectionName)
            let record = collector.FindById(name)
            if obj.ReferenceEquals(record, null) then
                Map.empty
            else
                arrayToCells record.Cells
    
    /// Get all pattern names from in-memory Catalog (primary source)
    /// Optionally merge with database
    let getPatternNames () : string list =
        Patterns.Catalog.patternNames
    
    /// Initialize catalog in database (seed missing patterns)
    let initCatalog () : unit =
        use db = getConnection()
        let collector = db.GetCollection<PatternRecord>(collectionName)
        
        // Get existing pattern names from database
        let existingNames = 
            collector.FindAll()
            |> Seq.map (fun p -> p.Id)
            |> Set.ofSeq
        
        // Seed missing patterns from Catalog to database
        for (name, cells) in Map.toSeq Patterns.Catalog.patterns do
            if not (Set.contains name existingNames) then
                let record = PatternRecord()
                record.Id <- name
                record.Cells <- cellsToArray cells
                collector.Insert(record) |> ignore
    
    /// Save a pattern to database (v2 authoring)
    let save (name: string) (cells: Cells) : unit =
        use db = getConnection()
        let collector = db.GetCollection<PatternRecord>(collectionName)
        let record = PatternRecord()
        record.Id <- name
        record.Cells <- cellsToArray cells
        collector.Upsert(record) |> ignore
    
    /// Remove a pattern from database (v2 authoring)
    let remove (name: string) : unit =
        use db = getConnection()
        let collector = db.GetCollection<PatternRecord>(collectionName)
        collector.Delete(name) |> ignore
    
    /// Clear all patterns from database (v2 authoring)
    let clear () : unit =
        use db = getConnection()
        let collector = db.GetCollection<PatternRecord>(collectionName)
        collector.DeleteAll() |> ignore
    
    /// Get all pattern names from database (v2 authoring)
    let names () : string list =
        use db = getConnection()
        let collector = db.GetCollection<PatternRecord>(collectionName)
        collector.FindAll()
        |> Seq.map (fun p -> p.Id)
        |> Seq.sort
        |> Seq.toList
