use crate::cell::Cell;
use crate::cells::Cells;
use crate::life::cross_breed;
use crate::position::Position;

/// Translates all cells in a pattern by:
/// 1. Re-centering on the reference cell's position
/// 2. Cross-breeding each pattern cell's breed with the reference cell's breed
///
/// This is a bijection on shapes: relative positions preserved, cardinality preserved (test P4).
pub fn translate(points: &Cells, reference: &Cell) -> Cells {
    points
        .iter()
        .fold(Cells::new(), |mut acc, (&position, &cell)| {
            // New position: offset by reference cell's position
            let new_position = Position::new(
                position.x + reference.position.x,
                position.y + reference.position.y,
            );

            // New breed: cross-breed pattern cell's breed with reference cell's breed
            let new_breed = cross_breed(cell.breed, reference.breed);

            // Create new cell at new position with new breed
            let new_cell = Cell::new(new_position, new_breed);

            acc.insert(new_position, new_cell);
            acc
        })
}

/// Returns a status message describing how many cells are alive.
/// Uses the exact normative text from spec §2.6.
///
/// This is unchanged from v1 - status message doesn't change for v2.
pub fn status_message(cells: &Cells) -> String {
    let n = cells.len();
    match n {
        0 => "No cell is alive".to_string(),
        1 => "1 cell is alive".to_string(),
        _ => format!("{} cells are alive", n),
    }
}
