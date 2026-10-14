# golwisafciml

**G**ame **o**f **L**ife **w**ith **I**mperative **S**hell **a**nd **F**unctional **C**ore **i**n **M**ultiple **L**anguages

A collection of small programs implementing [Conway's Game of Life](https://en.wikipedia.org/wiki/Conway%27s_Game_of_Life), written in many programming languages.

Every implementation follows the **Imperative Shell / Functional Core** architecture:

- **Functional Core** — the pure game logic (grid, rules, evolution). No I/O, no side effects, deterministic and easy to test.
- **Imperative Shell** — the environment-specific program that handles input, output, rendering, and everything impure, delegating all game logic to the core.

## Repository structure

One top-level folder per programming language. The list grows over time; currently:

- [`fsharp/`](fsharp/) — F#
- [`rust/`](rust/) — Rust

Inside each language folder, one folder per version of the game:

- **`classic/`** — the classic Game of Life (two states: dead / alive, B3/S23 rules).
- **`multibreed/`** — a variant where multiple "breeds" of cells color the game.

The behavior of each version is defined by the global specifications in [`specs.md`](specs.md), which sits at the top level of the repository and is shared by all languages.

Inside a version folder, there are two possible layouts:

- **Single-environment languages** (e.g. Snap!, Elm, PL/pgSQL) — the program lives directly in the version folder.
- **Multi-environment languages** (e.g. F#, Rust, TypeScript, Java…) — there is a `core/` folder containing the shared functional core (identical across environments), plus one folder per environment program:

```
<language>/
└── classic/
    ├── core/              # shared functional core (same code in every environment)
    ├── DesktopNativeGUI/  # one folder per environment program, each depends on core
    └── WebSPA/
```

### Environments

Environments are named after the technical context of the program, not the technology used (e.g. the same environment name may be implemented with different frameworks in different languages). Possible environments include (the list is not exhaustive):

- `DesktopNativeGUI` — native desktop application with a graphical interface
- `WebSPA` — browser-based single-page application
- `WebServerOnly` — server-side program (renders/serves over the web, no client logic)
- `Mobile` — mobile application
- `GameEngine` — implementation embedded in a game engine
- `Screensaver` — desktop screensaver
- `TerminalUI` — terminal-based user interface (TUI)
- `SSH` — program played over an SSH connection
- `Embedded` — program running on an embedded device

## How environments consume the core

**Rule: each environment project declares the core as a dependency through its language's native package/build mechanism. Core source files are never copied, and symlinks are never used.**

This keeps a single source of truth for the core (Linux is the only build environment) and survives zip downloads of the repository. The concrete mechanism is decided once per language:


| Language              | Mechanism                                                                                                 |
| --------------------- | --------------------------------------------------------------------------------------------------------- |
| F# / C#               | .NET solution; each environment project uses a MSBuild `ProjectReference` to the core `.fsproj`/`.csproj` |
| Rust                  | Cargo workspace; each environment crate has a `path` dependency on the core crate                         |
| Java / Kotlin / Scala | Multi-module build (Gradle / Maven / sbt subprojects)                                                     |
| TypeScript            | Workspace tooling (pnpm/yarn workspaces, `paths` in tsconfig, or a monorepo tool)                         |
| Go                    | Go module with a `replace` directive pointing to the local core                                           |
| Python                | Editable local install (`pip install -e`) or equivalent path-based dependency                             |
| OCaml                 | dune workspace with the core as a library                                                                 |
| Haskell               | Cabal multi-package project with relative-path packages                                                   |
| C / C++               | CMake `add_subdirectory(core)` or a static library target                                                 |
| Zig                   | `build.zig` module dependency on the core directory                                                       |
| Clojure               | `deps.edn` `:local/root`                                                                                  |
| Elixir                | Umbrella project with the core as an in-umbrella dependency                                               |


## Documentation conventions

Each folder level carries its own documentation:

- **Repo root** (this file): overall architecture, folder structure, and cross-language conventions.
- **Each language folder** (`<language>/README.md`): how the projects are locally organized in that language — build tooling, solution/workspace layout, and how environments reference the core.
- **Each environment folder** (`<language>/<version>/<environment>/README.md`): the local technologies used in that program (framework, renderer, deployment, how to build and run it).

## Status

Work in progress. New languages and environments are added over time.

## License

This project is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.
