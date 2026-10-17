use crate::cells::Cells;
use crate::position::Position;

/// Returns the set of all 8 Moore neighbors of a given position.
/// Computes directly without memoization - this is faster than any cache lookup
/// for this trivial function and maintains the core's purity.
pub fn neighbors(position: &Position) -> Cells {
    let mut result = Cells::with_capacity(8);
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
pub fn should_live(is_alive: bool, live_neighbor_count: usize) -> bool {
    if is_alive {
        live_neighbor_count == 2 || live_neighbor_count == 3
    } else {
        live_neighbor_count == 3
    }
}

/// Computes the union of cells and all their neighbors (the candidate set).
fn all_cells_and_neighbors(cells: &Cells) -> Cells {
    let mut result = Cells::new();

    for &cell in cells {
        result.insert(cell);
        for neighbor in neighbors(&cell) {
            result.insert(neighbor);
        }
    }

    result
}

/// Computes the next generation of cells using the candidate-set filtering strategy.
/// Builds the union of cells and their neighbors, then filters based on Life rules.
/// This is the v1 strategy as specified in spec §2.4.1.
pub fn next_generation_cells(cells: &Cells) -> Cells {
    let candidates = all_cells_and_neighbors(cells);

    candidates
        .into_iter()
        .filter(|&position| {
            let is_alive = cells.contains(&position);
            let live_neighbor_count = cells.intersection(&neighbors(&position)).count();
            should_live(is_alive, live_neighbor_count)
        })
        .collect()
}
