use crate::breed::Breed;
use crate::position::Position;

/// A cell at a position with a breed.
/// Implements Copy because both Position and Breed are Copy.
/// This is a value type representing a single live cell in the Game of Life grid.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Cell {
    pub position: Position,
    pub breed: Breed,
}

impl Cell {
    /// Creates a new Cell at the given position with the given breed.
    pub const fn new(position: Position, breed: Breed) -> Self {
        Self { position, breed }
    }

    /// Projects a Cell to its Position, dropping the breed.
    pub const fn position(&self) -> Position {
        self.position
    }

    /// Returns the breed of this cell.
    pub const fn breed(&self) -> Breed {
        self.breed
    }
}
