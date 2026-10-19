//! Demo scene seeding from Appendix C
//!
//! Applies all 13 stamps from Appendix C to the core.
//! v2: Each stamp now has a reference breed from Appendix C.

use crate::store::PatternStore;
use anyhow::Result;
use gol_multibreed_core::{Breed, Cell, Core, Position};

/// A stamp to apply: pattern name, reference position (x, y), and reference breed
#[derive(Clone, Copy)]
struct Stamp {
    pattern_name: &'static str,
    reference_x: i32,
    reference_y: i32,
    reference_breed: u8, // Breed value from Appendix C
}

/// The 13 stamps from Appendix C with their reference breeds
/// 
/// Appendix C defines breeds for each stamp:
/// 1. hwss at (33, 20) with breed 1
/// 2. rpentomino at (35, 10) with breed 2
/// 3. pentadecathlon at (25, 30) with breed 3
/// 4. blinker at (25, 10) with breed 4
/// 5. pentadecathlon at (20, 15) with breed 5
/// 6. mwss at (40, 15) with breed 6
/// 7. glider at (13, 13) with breed 7
/// 8. toad at (30, 15) with breed 8
/// 9. gosperGun at (12, 10) with breed 9
/// 10. pulsar at (13, 24) with breed 10
/// 11. acorn at (25, 25) with breed 11
/// 12. beacon at (25, 20) with breed 12
/// 13. pulsar at (36, 30) with breed 13
const DEMO_STAMPS: &[Stamp] = &[
    // 1: hwss at (33, 20) with breed 1
    Stamp {
        pattern_name: "hwss",
        reference_x: 33,
        reference_y: 20,
        reference_breed: 1,
    },
    // 2: rpentomino at (35, 10) with breed 2
    Stamp {
        pattern_name: "rpentomino",
        reference_x: 35,
        reference_y: 10,
        reference_breed: 2,
    },
    // 3: pentadecathlon at (25, 30) with breed 3
    Stamp {
        pattern_name: "pentadecathlon",
        reference_x: 25,
        reference_y: 30,
        reference_breed: 3,
    },
    // 4: blinker at (25, 10) with breed 4
    Stamp {
        pattern_name: "blinker",
        reference_x: 25,
        reference_y: 10,
        reference_breed: 4,
    },
    // 5: pentadecathlon at (20, 15) with breed 5
    Stamp {
        pattern_name: "pentadecathlon",
        reference_x: 20,
        reference_y: 15,
        reference_breed: 5,
    },
    // 6: mwss at (40, 15) with breed 6
    Stamp {
        pattern_name: "mwss",
        reference_x: 40,
        reference_y: 15,
        reference_breed: 6,
    },
    // 7: glider at (13, 13) with breed 7
    Stamp {
        pattern_name: "glider",
        reference_x: 13,
        reference_y: 13,
        reference_breed: 7,
    },
    // 8: toad at (30, 15) with breed 8
    Stamp {
        pattern_name: "toad",
        reference_x: 30,
        reference_y: 15,
        reference_breed: 8,
    },
    // 9: gosperGun at (12, 10) with breed 9
    Stamp {
        pattern_name: "gosperGun",
        reference_x: 12,
        reference_y: 10,
        reference_breed: 9,
    },
    // 10: pulsar at (13, 24) with breed 10
    Stamp {
        pattern_name: "pulsar",
        reference_x: 13,
        reference_y: 24,
        reference_breed: 10,
    },
    // 11: acorn at (25, 25) with breed 11
    Stamp {
        pattern_name: "acorn",
        reference_x: 25,
        reference_y: 25,
        reference_breed: 11,
    },
    // 12: beacon at (25, 20) with breed 12
    Stamp {
        pattern_name: "beacon",
        reference_x: 25,
        reference_y: 20,
        reference_breed: 12,
    },
    // 13: pulsar at (36, 30) with breed 13
    Stamp {
        pattern_name: "pulsar",
        reference_x: 36,
        reference_y: 30,
        reference_breed: 13,
    },
];

/// Seeds the demo scene by applying all 13 stamps to the initial core
/// Returns the final core and cells after applying all stamps
/// 
/// v2: Uses breed-aware reference cells. Each stamp's pattern (from catalog, breed 0)
/// is translated using a reference cell with the stamp's reference breed, which
/// causes cross-breeding to produce cells with the reference breed.
pub fn seed_demo_scene(initial_core: Core, store: &PatternStore) -> Result<(Core, gol_multibreed_core::Cells)> {
    use gol_multibreed_core::{Cells, translate};
    
    let mut current_core = initial_core;
    let mut current_cells = Cells::new();

    for stamp in DEMO_STAMPS {
        // Create reference cell with the stamp's reference breed
        let reference_position = Position::new(stamp.reference_x, stamp.reference_y);
        let reference_cell = Cell::new(reference_position, Breed::new(stamp.reference_breed));
        
        // Get the pattern cells from the store (these have breed 0 from catalog)
        let pattern_cells = store.get(stamp.pattern_name)?;
        
        // Translate the pattern using the reference cell
        // v2: translate uses cross-breeding, so the result will have the reference breed
        let translated = translate(&pattern_cells, &reference_cell);
        
        // Merge translated cells
        current_cells = current_cells.clone().union(translated.clone());
        current_core = current_core.with_cells(current_cells.clone());
    }

    Ok((current_core, current_cells))
}
