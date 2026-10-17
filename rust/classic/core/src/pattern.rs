use crate::cells::Cells;
use crate::position::Position;

/// Translates all points in a cell collection by adding the reference position's coordinates.
/// This effectively moves the entire pattern by the vector (reference.x, reference.y).
pub fn translate(points: &Cells, reference: &Position) -> Cells {
    points
        .iter()
        .map(|&p| Position::new(p.x + reference.x, p.y + reference.y))
        .collect()
}

/// Returns a status message describing how many cells are alive.
/// Uses the exact normative text from spec §2.6.
pub fn status_message(cells: &Cells) -> String {
    let n = cells.len();
    match n {
        0 => "No cell is alive".to_string(),
        1 => "1 cell is alive".to_string(),
        _ => format!("{} cells are alive", n),
    }
}
