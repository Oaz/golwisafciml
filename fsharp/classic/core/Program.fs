namespace GameOfLife.Classic.Core

open System

/// The program type: a generic free-monad-inspired encoding.
/// The 'a type parameter represents the return value (typically Core for our operations).
/// This is a pure data structure with no behavior - all interpretation happens in the shell.
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
  /// Monadic bind as a plain generic function. Calling a generic member on `this`
  /// from another member pins its type variables in F#, hence this let-bound helper.
  let rec bind (m: Program<'a>) (f: 'a -> Program<'b>) : Program<'b> =
    match m with
    | Pure x -> f x
    | ShowCells(cells, next) -> ShowCells(cells, bind next f)
    | DisplayMessage(msg, next) -> DisplayMessage(msg, bind next f)
    | HideMessage next -> HideMessage(bind next f)
    | GetPatternCells(name, cont) -> GetPatternCells(name, fun cells -> bind (cont cells) f)

  /// Computation expression builder for Program.
  /// Enables building pure Program values using computation expression syntax.
  /// This showcases F#'s ability to create domain-specific effect systems for the Functional Core.
  type ProgramBuilder() =
    member _.Zero() : Program<unit> = Pure()
    member _.Delay(f: unit -> Program<'a>) : Program<'a> = f ()
    member _.Combine(a: Program<unit>, b: Program<'b>) : Program<'b> = bind a (fun () -> b)
    member _.Bind(m: Program<'a>, f: 'a -> Program<'b>) : Program<'b> = bind m f
    member _.Return(x: 'a) : Program<'a> = Pure x
    member _.ReturnFrom(m: Program<'a>) : Program<'a> = m

  /// Create a global program builder instance for use in computation expressions.
  let program = ProgramBuilder()

  let nextGenerationProgram (core: Core) : CoreProgram =
    program {
      let newCells = nextGenerationCells (cellsOf core)
      let core' = withCells core newCells

      if statusVisibleOf core then
        do! displayMessage (statusMessage newCells)

      do! showCells newCells
      return core'
    }

  let addPatternProgram (core: Core) (patternName: string) (reference: Position) : CoreProgram =
    program {
      let! patternCells = getPatternCells patternName

      let translated = translate patternCells reference
      let newCells = Set.union (cellsOf core) translated
      let core' = withCells core newCells

      if statusVisibleOf core then
        do! displayMessage (statusMessage newCells)

      do! showCells newCells
      return core'
    }

  let switchStatusVisibilityProgram (core: Core) : CoreProgram =
    program {
      if statusVisibleOf core then
        do! hideMessage
        return withStatusVisible core false
      else
        do! displayMessage (statusMessage (cellsOf core))
        return withStatusVisible core true
    }
