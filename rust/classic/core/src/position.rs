/// A position in the Game of Life grid with x and y coordinates.
/// Uses i32 which comfortably handles the spec's unbounded coordinate requirements
/// (G11 test uses ±1,000,000 which fits well within i32's ±2.1 billion range).
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Position {
    pub x: i32,
    pub y: i32,
}

impl Position {
    /// Creates a new Position with the given x and y coordinates.
    /// This is a const function to allow compile-time construction.
    pub const fn new(x: i32, y: i32) -> Self {
        Self { x, y }
    }
}
