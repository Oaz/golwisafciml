use im::HashMap;

use crate::breed::Breed;
use crate::cell::Cell;
use crate::cells::Cells;
use crate::position::Position;

/// Returns the set of all 8 Moore neighbors of a given position.
/// Computes directly without memoization - this is faster than any cache lookup
/// for this trivial function and maintains the core's purity.
pub fn neighbors(position: &Position) -> std::collections::HashSet<Position> {
    let mut result = std::collections::HashSet::with_capacity(8);
    for dx in [-1, 0, 1] {
        for dy in [-1, 0, 1] {
            if dx == 0 && dy == 0 {
                continue; // Skip the position itself
            }
            result.insert(Position::new(position.x + dx, position.y + dy));
        }
    }
    result
}

/// Determines if a cell should be alive in the next generation based on Conway's rules.
/// - A live cell survives with exactly 2 or 3 live neighbors
/// - A dead cell is born with exactly 3 live neighbors
///
/// This is unchanged from v1 - Life rules don't change for v2.
pub fn should_live(is_alive: bool, live_neighbor_count: usize) -> bool {
    if is_alive {
        live_neighbor_count == 2 || live_neighbor_count == 3
    } else {
        live_neighbor_count == 3
    }
}

/// Computes the cross-breed of two breeds: (a + b) mod 16.
/// This is commutative and associative, making fold order-independent.
///
/// The masking in Breed::new() automatically handles mod 16 via bitwise AND.
pub const fn cross_breed(a: Breed, b: Breed) -> Breed {
    Breed::new(a.value() + b.value())
}

/// Entry type for neighbor counting: (count, breed)
/// Count of live neighbors that have this position as a neighbor.
/// Breed is the running cross-breed sum of all contributing cells.
pub type NeighborCountEntry = (usize, Breed);

/// Type alias for the neighbor count map.
pub type NeighborCounts = HashMap<Position, NeighborCountEntry>;

/// Updates the neighbor count map by adding one more contribution.
///
/// - If the position is not in the map: inserts (1, breed)
/// - If the position is in the map: increments count and cross-breeds the breed
///
/// This is pure: the input map is not mutated, a new map is returned.
/// Uses im::HashMap's persistent operations - passing by value and mutating creates a new map.
pub fn bump(mut counts: NeighborCounts, position: Position, breed: Breed) -> NeighborCounts {
    let entry = counts.get(&position);

    let new_entry = match entry {
        Some(&(count, existing_breed)) => {
            let new_count = count + 1;
            let new_breed = cross_breed(existing_breed, breed);
            (new_count, new_breed)
        }
        None => (1, breed),
    };

    // im::HashMap uses copy-on-write: modifying `counts` (passed by value) creates a new map
    counts.insert(position, new_entry);
    counts
}

/// Computes the next generation of cells using the persistent-map fold strategy.
///
/// Strategy per spec §2.4.2:
/// 1. Build a map `position -> (count, breed)` by folding over all cells and their neighbors
/// 2. For each live cell at position p, fold over its 8 neighbors
/// 3. For each neighbor position n, bump the count and cross-breed with the cell's breed
/// 4. Filter the map: positions where shouldLive(p is in livingPositions, count) is true
/// 5. For surviving/born positions, create a Cell with the folded breed
///
/// This produces identical position sets as v1 (test G10).
pub fn next_generation_cells(cells: &Cells) -> Cells {
    // Build the set of living positions
    let living_positions: std::collections::HashSet<Position> = cells.keys().cloned().collect();

    // Build neighbor counts map: position -> (count, breed)
    let counts = cells
        .iter()
        .fold(NeighborCounts::new(), |acc, (&position, &cell)| {
            // For each neighbor of this live cell, bump the count
            neighbors(&position)
                .iter()
                .fold(acc, |inner_acc, &neighbor_pos| {
                    bump(inner_acc, neighbor_pos, cell.breed)
                })
        });

    // Filter: positions that survive or are born
    counts
        .iter()
        .fold(Cells::new(), |mut acc, (&position, &(count, breed))| {
            let is_alive = living_positions.contains(&position);
            if should_live(is_alive, count) {
                // Create cell at this position with the cross-bred breed
                let new_cell = Cell::new(position, breed);
                acc.insert(position, new_cell);
                acc
            } else {
                acc
            }
        })
}
