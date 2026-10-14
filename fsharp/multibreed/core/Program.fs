namespace GameOfLife.Multibreed.Core

open System

/// The program type: a generic free-monad-inspired encoding.
/// The 'a type parameter represents the return value (typically Core for our operations).
/// This is a pure data structure with no behavior - all interpretation happens in the shell.
/// IDENTICAL to v1 - the shell never changes.
type Program<'a> =
  | Pure of 'a
  | ShowCells of Cells * Program<'a>
  | DisplayMessage of string * Program<'a>
  | HideMessage of Program<'a>
  | GetPatternCells of string * (Cells -> Program<'a>)

/// Instruction constructors: build single-effect programs with a Pure () tail.
[<AutoOpen>]
module ProgramInstructions =
  let showCells (cells: Cells) : Program<unit> = ShowCells(cells, Pure())
  let displayMessage (msg: string) : Program<unit> = DisplayMessage(msg, Pure())
  let hideMessage: Program<unit> = HideMessage(Pure())

  let getPatternCells (name: string) : Program<Cells> =
    GetPatternCells(name, fun cells -> Pure cells)

/// Specialized type alias for core operations which always return Core.
type CoreProgram = Program<Core>

/// Module containing all Program-related functionality
[<AutoOpen>]
module ProgramOperations =
  /// Monadic bind as a plain generic function.
  let rec bind (m: Program<'a>) (f: 'a -> Program<'b>) : Program<'b> =
    match m with
    | Pure x -> f x
    | ShowCells(cells, next) -> ShowCells(cells, bind next f)
    | DisplayMessage(msg, next) -> DisplayMessage(msg, bind next f)
    | HideMessage next -> HideMessage(bind next f)
    | GetPatternCells(name, cont) -> GetPatternCells(name, fun cells -> bind (cont cells) f)

  /// Computation expression builder for Program.
  type ProgramBuilder() =
    member _.Zero() : Program<unit> = Pure()
    member _.Delay(f: unit -> Program<'a>) : Program<'a> = f ()
    member _.Combine(a: Program<unit>, b: Program<'b>) : Program<'b> = bind a (fun () -> b)
    member _.Bind(m: Program<'a>, f: 'a -> Program<'b>) : Program<'b> = bind m f
    member _.Return(x: 'a) : Program<'a> = Pure x
    member _.ReturnFrom(m: Program<'a>) : Program<'a> = m

  /// Create a global program builder instance
  let program = ProgramBuilder()

  let nextGenerationProgram (core: Core) : CoreProgram =
    program {
      let newCells = Core.nextGenerationCells (Core.cellsOf core)
      let core' = Core.withCells core newCells

      if Core.statusVisibleOf core then
        do! ProgramInstructions.displayMessage (Core.statusMessage newCells)

      do! ProgramInstructions.showCells newCells
      return core'
    }

  let addPatternProgram (core: Core) (patternName: string) (reference: Cell) : CoreProgram =
    program {
      // v2: resolve "current" sentinel to the stored currentPattern
      let resolvedName =
          if patternName = "current" then
              Core.currentPatternOf core
          else
              patternName
      
      let! patternCells = ProgramInstructions.getPatternCells resolvedName

      let translated = Core.translate patternCells reference
      // Union two Maps: translated cells replace existing ones at same positions
      let newCells = List.fold (fun acc (pos, breed) -> Map.add pos breed acc) (Core.cellsOf core) (Map.toList translated)
      let core' = Core.withCells core newCells

      if Core.statusVisibleOf core then
        do! ProgramInstructions.displayMessage (Core.statusMessage newCells)

      do! ProgramInstructions.showCells newCells
      return core'
    }

  let switchStatusVisibilityProgram (core: Core) : CoreProgram =
    program {
      if Core.statusVisibleOf core then
        do! ProgramInstructions.hideMessage
        return Core.withStatusVisible core false
      else
        do! ProgramInstructions.displayMessage (Core.statusMessage (Core.cellsOf core))
        return Core.withStatusVisible core true
    }

  /// NEW for v2: Produces a program that defines the current pattern name in core state.
  let defineCurrentPatternProgram (core: Core) (name: string) : CoreProgram =
    program {
      return Core.withCurrentPattern core name
    }
