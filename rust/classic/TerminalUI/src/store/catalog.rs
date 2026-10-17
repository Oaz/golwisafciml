//! Pattern catalog data from Appendix A
//!
//! Contains all 19 normative patterns with their exact coordinates.

use gol_classic_core::{Cells, Position};
use std::collections::HashMap;

/// Returns the pattern catalog as a HashMap from pattern name to Cells
pub fn get_catalog() -> HashMap<String, Cells> {
    let mut catalog = HashMap::new();

    // blinker: (-1,0), (0,0), (1,0)
    catalog.insert(
        "blinker".to_string(),
        cells_from_coords(&[(-1, 0), (0, 0), (1, 0)]),
    );

    // toad: (0,0), (1,0), (2,0), (-1,1), (0,1), (1,1)
    catalog.insert(
        "toad".to_string(),
        cells_from_coords(&[(0, 0), (1, 0), (2, 0), (-1, 1), (0, 1), (1, 1)]),
    );

    // beacon: (-1,-1), (0,-1), (-1,0), (0,0), (1,1), (2,1), (1,2), (2,2)
    catalog.insert(
        "beacon".to_string(),
        cells_from_coords(&[(-1, -1), (0, -1), (-1, 0), (0, 0), (1, 1), (2, 1), (1, 2), (2, 2)]),
    );

    // pentadecathlon: (-2,-1), (3,-1), (-4,0), (-3,0), (-1,0), (0,0), (1,0), (2,0), (4,0), (5,0), (-2,1), (3,1)
    catalog.insert(
        "pentadecathlon".to_string(),
        cells_from_coords(&[
            (-2, -1), (3, -1),
            (-4, 0), (-3, 0), (-1, 0), (0, 0), (1, 0), (2, 0), (4, 0), (5, 0),
            (-2, 1), (3, 1),
        ]),
    );

    // block: (0,0), (1,0), (0,1), (1,1)
    catalog.insert(
        "block".to_string(),
        cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]),
    );

    // beehive: (0,-1), (1,-1), (-1,0), (2,0), (0,1), (1,1)
    catalog.insert(
        "beehive".to_string(),
        cells_from_coords(&[(0, -1), (1, -1), (-1, 0), (2, 0), (0, 1), (1, 1)]),
    );

    // loaf: (0,-1), (1,-1), (-1,0), (2,0), (0,1), (2,1), (1,2)
    catalog.insert(
        "loaf".to_string(),
        cells_from_coords(&[(0, -1), (1, -1), (-1, 0), (2, 0), (0, 1), (2, 1), (1, 2)]),
    );

    // boat: (-1,-1), (0,-1), (-1,0), (1,0), (0,1)
    catalog.insert(
        "boat".to_string(),
        cells_from_coords(&[(-1, -1), (0, -1), (-1, 0), (1, 0), (0, 1)]),
    );

    // tub: (0,-1), (-1,0), (1,0), (0,1)
    catalog.insert(
        "tub".to_string(),
        cells_from_coords(&[(0, -1), (-1, 0), (1, 0), (0, 1)]),
    );

    // glider: (0,-1), (1,0), (-1,1), (0,1), (1,1)
    catalog.insert(
        "glider".to_string(),
        cells_from_coords(&[(0, -1), (1, 0), (-1, 1), (0, 1), (1, 1)]),
    );

    // lwss: (-1,-1), (2,-1), (-2,0), (-2,1), (2,1), (-2,2), (-1,2), (0,2), (1,2)
    catalog.insert(
        "lwss".to_string(),
        cells_from_coords(&[
            (-1, -1), (2, -1),
            (-2, 0),
            (-2, 1), (2, 1),
            (-2, 2), (-1, 2), (0, 2), (1, 2),
        ]),
    );

    // mwss: (1,-2), (-1,-1), (3,-1), (-2,0), (-2,1), (3,1), (-2,2), (-1,2), (0,2), (1,2), (2,2)
    catalog.insert(
        "mwss".to_string(),
        cells_from_coords(&[
            (1, -2),
            (-1, -1), (3, -1),
            (-2, 0),
            (-2, 1), (3, 1),
            (-2, 2), (-1, 2), (0, 2), (1, 2), (2, 2),
        ]),
    );

    // hwss: (0,-2), (1,-2), (-2,-1), (3,-1), (-3,0), (-3,1), (3,1), (-3,2), (-2,2), (-1,2), (0,2), (1,2), (2,2)
    catalog.insert(
        "hwss".to_string(),
        cells_from_coords(&[
            (0, -2), (1, -2),
            (-2, -1), (3, -1),
            (-3, 0), (-3, 1), (3, 1),
            (-3, 2), (-2, 2), (-1, 2), (0, 2), (1, 2), (2, 2),
        ]),
    );

    // rpentomino: (0,-1), (1,-1), (-1,0), (0,0), (0,1)
    catalog.insert(
        "rpentomino".to_string(),
        cells_from_coords(&[(0, -1), (1, -1), (-1, 0), (0, 0), (0, 1)]),
    );

    // diehard: (3,-1), (-3,0), (-2,0), (-2,1), (2,1), (3,1), (4,1)
    catalog.insert(
        "diehard".to_string(),
        cells_from_coords(&[(3, -1), (-3, 0), (-2, 0), (-2, 1), (2, 1), (3, 1), (4, 1)]),
    );

    // acorn: (-2,-1), (0,0), (-3,1), (-2,1), (1,1), (2,1), (3,1)
    catalog.insert(
        "acorn".to_string(),
        cells_from_coords(&[(-2, -1), (0, 0), (-3, 1), (-2, 1), (1, 1), (2, 1), (3, 1)]),
    );

    // pulsar
    catalog.insert(
        "pulsar".to_string(),
        cells_from_coords(&[
            (-4, -6), (-3, -6), (-2, -6), (2, -6), (3, -6), (4, -6),
            (-6, -4), (-1, -4), (1, -4), (6, -4),
            (-6, -3), (-1, -3), (1, -3), (6, -3),
            (-6, -2), (-1, -2), (1, -2), (6, -2),
            (-4, -1), (-3, -1), (-2, -1), (2, -1), (3, -1), (4, -1),
            (-4, 1), (-3, 1), (-2, 1), (2, 1), (3, 1), (4, 1),
            (-6, 2), (-1, 2), (1, 2), (6, 2),
            (-6, 3), (-1, 3), (1, 3), (6, 3),
            (-6, 4), (-1, 4), (1, 4), (6, 4),
            (-4, 6), (-3, 6), (-2, 6), (2, 6), (3, 6), (4, 6),
        ]),
    );

    // gosperGun
    catalog.insert(
        "gosperGun".to_string(),
        cells_from_coords(&[
            (7, -4), (5, -3), (7, -3),
            (-5, -2), (-4, -2), (3, -2), (4, -2), (17, -2), (18, -2),
            (-6, -1), (-2, -1), (3, -1), (4, -1), (17, -1), (18, -1),
            (-17, 0), (-16, 0), (-7, 0), (-1, 0), (3, 0), (4, 0),
            (-17, 1), (-16, 1), (-7, 1), (-3, 1), (-1, 1), (0, 1), (5, 1), (7, 1),
            (-7, 2), (-1, 2), (7, 2),
            (-6, 3), (-2, 3), (-5, 4), (-4, 4),
        ]),
    );

    // simkinGun
    catalog.insert(
        "simkinGun".to_string(),
        cells_from_coords(&[
            (-16, -10), (-15, -10), (-9, -10), (-8, -10),
            (-16, -9), (-15, -9), (-9, -9), (-8, -9),
            (-12, -7), (-11, -7), (-12, -6), (-11, -6),
            (6, -1), (7, -1), (9, -1), (10, -1),
            (5, 0), (11, 0),
            (5, 1), (12, 1), (15, 1), (16, 1),
            (5, 2), (6, 2), (7, 2), (11, 2), (15, 2), (16, 2), (10, 3),
            (4, 7), (5, 7), (4, 8), (5, 9), (6, 9), (7, 9), (7, 10),
        ]),
    );

    catalog
}

/// Helper function to create Cells from a slice of (x, y) tuples
fn cells_from_coords(coords: &[(i32, i32)]) -> Cells {
    coords.iter().map(|&(x, y)| Position::new(x, y)).collect()
}

/// Returns a sorted list of all pattern names in ascending order
#[allow(dead_code)]
fn get_pattern_names() -> Vec<String> {
    let mut names: Vec<String> = get_catalog().keys().cloned().collect();
    names.sort();
    names
}
