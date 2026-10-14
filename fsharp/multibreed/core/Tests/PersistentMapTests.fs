module GameOfLife.Multibreed.Core.Tests.PersistentMapTests

open Expecto
open GameOfLife.Multibreed.Core

// Helper for creating positions
let private p x y = Position.create x y

// Helper for creating breeds
let private b n = Breed.create n

// ============================================================================
// Group D - Persistent map
// ============================================================================

[<Tests>]
let allPersistentMapTests =
    testList "Group D - Persistent Map" [
        test "map basics" {
            // Build map with entries 25->("a",44), 78->("b",23)
            // Note: In our implementation, the map stores (int * Breed), but we'll use int for simplicity
            let map = Map.empty
            let map1 = Map.add (p 25 0) (1, b 44) map
            let map2 = Map.add (p 78 0) (2, b 23) map1
            
            // Get both entries
            match Map.tryFind (p 25 0) map2 with
            | Some (count, breed) ->
                Expect.equal count 1 "First entry should have count 1"
                Expect.equal (Breed.value breed) 12 "First entry should have breed 12 (44 mod 16 = 12)"
            | None -> failtest "First entry should exist"
            
            match Map.tryFind (p 78 0) map2 with
            | Some (count, breed) ->
                Expect.equal count 2 "Second entry should have count 2"
                Expect.equal (Breed.value breed) 7 "Second entry should have breed 7 (23 mod 16 = 7)"
            | None -> failtest "Second entry should exist"
            
            // keys ascending
            let keys = Map.keys map2 |> Seq.toList
            let sorted = List.sort keys
            Expect.equal keys sorted "Keys should be in ascending order"
            
            // values and entries round-trip
            let values = Map.values map2 |> Seq.toList
            Expect.equal (List.length values) 2 "Should have 2 values"
            
            let entries = Map.toList map2
            Expect.equal (List.length entries) 2 "Should have 2 entries"
        }

        test "persistence" {
            let map1 = Map.empty
            let map2 = Map.add (p 1 0) (1, b 0) map1
            let map3 = Map.add (p 2 0) (2, b 1) map2
            
            // map1 should still be empty
            Expect.equal (Map.count map1) 0 "Original map should still be empty"
            
            // map2 should still have only the first entry
            Expect.equal (Map.count map2) 1 "First derived map should have 1 entry"
            match Map.tryFind (p 1 0) map2 with
            | Some _ -> ()
            | None -> failtest "First map should still have first entry"
            match Map.tryFind (p 2 0) map2 with
            | Some _ -> failtest "First map should not have second entry"
            | None -> ()
            
            // map3 should have both entries
            Expect.equal (Map.count map3) 2 "Second derived map should have 2 entries"
        }

        test "absent key" {
            let map = Map.empty
            
            match Map.tryFind (p 1 0) map with
            | Some _ -> failtest "Should return None for absent key"
            | None -> ()
            
            // Not a magic value
            // In F#, Map.find would throw for missing key, but tryFind returns option
            // This test verifies we're not using a magic sentinel value
        }

        test "update existing key" {
            let map1 = Map.empty
            let map2 = Map.add (p 1 0) (1, b 0) map1
            let map3 = Map.add (p 1 0) (2, b 1) map2
            
            // map2 should still have the old value
            match Map.tryFind (p 1 0) map2 with
            | Some (count, breed) ->
                Expect.equal count 1 "Original map should have count 1"
                Expect.equal (Breed.value breed) 0 "Original map should have breed 0"
            | None -> failtest "Original map should have entry"
            
            // map3 should have the new value
            match Map.tryFind (p 1 0) map3 with
            | Some (count, breed) ->
                Expect.equal count 2 "Updated map should have count 2"
                Expect.equal (Breed.value breed) 1 "Updated map should have breed 1"
            | None -> failtest "Updated map should have entry"
        }

        test "insertion-order independence" {
            // Insert keys in ascending order
            let map1 = Map.empty
            let map1 = Map.add (p 1 0) (1, b 0) map1
            let map1 = Map.add (p 2 0) (1, b 0) map1
            let map1 = Map.add (p 3 0) (1, b 0) map1
            
            // Insert keys in descending order
            let map2 = Map.empty
            let map2 = Map.add (p 3 0) (1, b 0) map2
            let map2 = Map.add (p 2 0) (1, b 0) map2
            let map2 = Map.add (p 1 0) (1, b 0) map2
            
            // Both should have the same key set
            let keys1 = Map.keys map1 |> Set.ofSeq
            let keys2 = Map.keys map2 |> Set.ofSeq
            Expect.equal keys1 keys2 "Key sets should be equal regardless of insertion order"
            
            // Insert keys in random order
            let map3 = Map.empty
            let map3 = Map.add (p 2 0) (1, b 0) map3
            let map3 = Map.add (p 1 0) (1, b 0) map3
            let map3 = Map.add (p 3 0) (1, b 0) map3
            
            let keys3 = Map.keys map3 |> Set.ofSeq
            Expect.equal keys1 keys3 "Key sets should be equal for random order too"
        }

        test "map at scale" {
            // Insert 1000 sequential keys
            let rec insertMany map n =
                if n <= 0 then map
                else insertMany (Map.add (p n 0) (1, b (n % 16)) map) (n - 1)
            
            let map = insertMany Map.empty 1000
            
            // All should be retrievable
            for i in 1..1000 do
                match Map.tryFind (p i 0) map with
                | Some (count, breed) ->
                    Expect.equal count 1 (sprintf "Entry %d should have count 1" i)
                    Expect.equal (Breed.value breed) (i % 16) (sprintf "Entry %d should have correct breed" i)
                | None -> failtest (sprintf "Entry %d should exist" i)
            
            // Key set should be complete
            let keys = Map.keys map |> Set.ofSeq
            let expectedKeys = Set.ofList [for i in 1..1000 do p i 0]
            Expect.equal keys expectedKeys "All keys should be present"
        }
    ]
