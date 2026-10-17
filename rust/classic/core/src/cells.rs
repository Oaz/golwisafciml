use std::collections::HashSet;

use crate::position::Position;

/// A collection of cell positions.
/// Uses HashSet<Position> for O(1) membership testing and automatic uniqueness.
/// HashSet provides order-insensitive equality as required by spec §2.2.
pub type Cells = HashSet<Position>;
