module GameOfLife.Classic.Core.Tests.ProgramTests

open Expecto
open GameOfLife.Classic.Core

// Helper for creating positions
let private p x y = Position.create x y

// ============================================================================
// Group I - Programs and protocol
// ============================================================================

let iTests =
    testList "Group I - Programs and Protocol" [
        test "all opcodes are distinguishable" {
            let core = Core.initialCore
            
            let pureProg : CoreProgram = Program.Pure core
            let showCellsProg = Program.ShowCells (Set.empty, pureProg)
            let displayMsgProg = Program.DisplayMessage ("test", pureProg)
            let hideMsgProg = Program.HideMessage pureProg
            let getPatternProg = Program.GetPatternCells ("test", fun _ -> pureProg)
            
            match pureProg with
            | Program.Pure _ -> ()
            | _ -> failtest "Pure should match Pure case"
            
            match showCellsProg with
            | Program.ShowCells _ -> ()
            | _ -> failtest "ShowCells should match ShowCells case"
            
            match displayMsgProg with
            | Program.DisplayMessage _ -> ()
            | _ -> failtest "DisplayMessage should match DisplayMessage case"
            
            match hideMsgProg with
            | Program.HideMessage _ -> ()
            | _ -> failtest "HideMessage should match HideMessage case"
            
            match getPatternProg with
            | Program.GetPatternCells _ -> ()
            | _ -> failtest "GetPatternCells should match GetPatternCells case"
        }

        test "nextGenerationProgram has correct shape with status visible" {
            let cells = Set.ofList [p 0 0; p 1 0; p 0 1]
            let core = Core.withCells (Core.withStatusVisible Core.initialCore true) cells
            
            let program = ProgramOperations.nextGenerationProgram core
            
            match program with
            | Program.DisplayMessage (msg, next) ->
                Expect.equal msg (Core.statusMessage (Core.nextGenerationCells cells)) "DisplayMessage should show status message"
                
                match next with
                | Program.ShowCells (newCells, Program.Pure returnedCore) ->
                    Expect.equal newCells (Core.nextGenerationCells cells) "ShowCells should display next generation cells"
                    Expect.equal (Core.cellsOf returnedCore) newCells "Returned core should have new cells"
                | _ -> failtest "Expected ShowCells followed by Pure"
            | _ -> failtest "Expected DisplayMessage as first instruction when status visible"
        }

        test "nextGenerationProgram with status not visible" {
            let cells = Set.ofList [p 0 0; p 1 0; p 0 1]
            let core = Core.withCells Core.initialCore cells
            
            let program = ProgramOperations.nextGenerationProgram core
            
            match program with
            | Program.ShowCells (newCells, Program.Pure returnedCore) ->
                Expect.equal newCells (Core.nextGenerationCells cells) "ShowCells should display next generation cells"
                Expect.equal (Core.cellsOf returnedCore) newCells "Returned core should have new cells"
            | _ -> failtest "Expected ShowCells followed directly by Pure"
        }

        test "switchStatusVisibilityProgram has correct shape" {
            let cells = Set.ofList [p 0 0]
            let coreVisible = Core.withCells (Core.withStatusVisible Core.initialCore true) cells
            
            let programVisible = ProgramOperations.switchStatusVisibilityProgram coreVisible
            
            match programVisible with
            | Program.HideMessage (Program.Pure returnedCore) ->
                Expect.isFalse (Core.statusVisibleOf returnedCore) "Status should be hidden"
                Expect.equal (Core.cellsOf returnedCore) cells "Cells should be unchanged"
            | _ -> failtest "Expected HideMessage followed by Pure when toggling off"
            
            let coreHidden = Core.withCells Core.initialCore cells
            let programHidden = ProgramOperations.switchStatusVisibilityProgram coreHidden
            
            match programHidden with
            | Program.DisplayMessage (msg, Program.Pure returnedCore) ->
                Expect.equal msg (Core.statusMessage cells) "Should display current status"
                Expect.isTrue (Core.statusVisibleOf returnedCore) "Status should be visible"
                Expect.equal (Core.cellsOf returnedCore) cells "Cells should be unchanged"
            | _ -> failtest "Expected DisplayMessage followed by Pure when toggling on"
        }

        test "addPatternProgram has correct shape" {
            let cells = Set.ofList [p 0 0]
            let core = Core.withCells (Core.withStatusVisible Core.initialCore true) cells
            
            let program = ProgramOperations.addPatternProgram core "test" (p 10 10)
            
            match program with
            | Program.GetPatternCells (name, cont) ->
                Expect.equal name "test" "Pattern name should be 'test'"
                
                let patternCells = Set.ofList [p 0 0; p 1 0; p 0 1]
                let resultProgram = cont patternCells
                
                match resultProgram with
                | Program.DisplayMessage (msg, next) ->
                    let translated = Core.translate patternCells (p 10 10)
                    let expected = Set.union cells translated
                    Expect.equal msg (Core.statusMessage expected) "Should display status message"
                    
                    match next with
                    | Program.ShowCells (newCells, Program.Pure returnedCore) ->
                        Expect.equal newCells expected "ShowCells should display union of cells"
                        Expect.equal (Core.cellsOf returnedCore) expected "Returned core should have new cells"
                    | _ -> failtest "Expected ShowCells followed by Pure"
                | _ -> failtest "Expected DisplayMessage followed by ShowCells after continuation"
            | _ -> failtest "Expected GetPatternCells as first instruction"
        }

        test "all programs end with Pure" {
            let core = Core.withStatusVisible Core.initialCore true
            
            let rec endsWithPure (prog: Program<'a>) : bool =
                match prog with
                | Program.Pure _ -> true
                | Program.ShowCells (_, next) -> endsWithPure next
                | Program.DisplayMessage (_, next) -> endsWithPure next
                | Program.HideMessage next -> endsWithPure next
                | Program.GetPatternCells (_, cont) -> endsWithPure (cont Set.empty)
            
            let nextGenProg = ProgramOperations.nextGenerationProgram core
            Expect.isTrue (endsWithPure nextGenProg) "nextGenerationProgram ends with Pure"
            
            let addPatternProg = ProgramOperations.addPatternProgram core "test" (p 0 0)
            Expect.isTrue (endsWithPure addPatternProg) "addPatternProgram ends with Pure"
            
            let switchProg = ProgramOperations.switchStatusVisibilityProgram core
            Expect.isTrue (endsWithPure switchProg) "switchStatusVisibilityProgram ends with Pure"
        }

        test "unknown pattern continuation handles empty cells" {
            let cells = Set.ofList [p 0 0; p 1 0]
            let core = Core.withCells Core.initialCore cells
            
            let program = ProgramOperations.addPatternProgram core "nonexistent" (p 5 5)
            
            match program with
            | Program.GetPatternCells (name, cont) ->
                Expect.equal name "nonexistent" "Pattern name should be 'nonexistent'"
                
                let resultProgram = cont Set.empty
                
                match resultProgram with
                | Program.ShowCells (newCells, Program.Pure returnedCore) ->
                    Expect.equal newCells cells "Cells should be unchanged when pattern is empty"
                    Expect.equal (Core.cellsOf returnedCore) cells "Core should have original cells"
                | _ -> failtest "Expected ShowCells followed by Pure"
            | _ -> failtest "Expected GetPatternCells as first instruction"
        }
    ]

// ============================================================================
// Test Entry Point
// ============================================================================

[<Tests>]
let allProgramTests = iTests
