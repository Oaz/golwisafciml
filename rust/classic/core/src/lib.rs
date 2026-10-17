//! Functional core for Conway's Game of Life (v1) - Classic Monochrome
//!
//! This crate provides the pure functional core implementation for the Game of Life.
//! It contains only types, pure functions, and the Program type. It has no dependencies
//! on ports, no interpreter, and no test doubles.

pub use cells::Cells;
pub use core::Core;
pub use life::{neighbors, next_generation_cells, should_live};
pub use pattern::{status_message, translate};
pub use position::Position;
pub use program::{
    add_pattern_program, next_generation_program, switch_status_visibility_program, Program,
};

mod cells;
mod core;
mod life;
mod pattern;
mod position;
mod program;

#[cfg(test)]
mod tests;
