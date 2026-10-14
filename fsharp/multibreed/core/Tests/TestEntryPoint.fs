module GameOfLife.Multibreed.Core.Tests.TestEntryPoint

open Expecto

// Import all test modules
open GameOfLife.Multibreed.Core.Tests.CoreTests
open GameOfLife.Multibreed.Core.Tests.ProgramTests
open GameOfLife.Multibreed.Core.Tests.PropertyTests
open GameOfLife.Multibreed.Core.Tests.PersistentMapTests

[<EntryPoint>]
let main argv =
    // Combine all test lists
    let allTests = testList "All Tests" [
        allCoreTests
        allProgramTests
        allPropertyTests
        allPersistentMapTests
    ]
    
    // Run tests with Expecto
    runTestsInAssemblyWithCLIArgs [] argv
