use im::HashMap;

use crate::cell::Cell;
use crate::position::Position;

/// A collection of cells, mapping each position to at most one cell.
/// Uses im::HashMap for persistent (immutable) map operations.
///
/// This enforces "at most one cell per position" at the type level.
/// Provides order-insensitive equality as required by spec §2.2.
/// All operations are persistent: they return new maps without mutating the original.
pub type Cells = HashMap<Position, Cell>;
