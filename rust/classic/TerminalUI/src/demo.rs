//! Demo scene seeding from Appendix C
//!
//! Applies all 13 stamps from Appendix C to the core.

use crate::store::PatternStore;
use anyhow::Result;
use gol_classic_core::{Core, Position};

/// A stamp to apply: pattern name, reference position (x, y)
#[derive(Clone, Copy)]
struct Stamp {
    pattern_name: &'static str,
    reference_x: i32,
    reference_y: i32,
}

/// The 13 stamps from Appendix C
/// Note: In v1, we ignore the breed column (monochrome)
const DEMO_STAMPS: &[Stamp] = &[
    // 1: hwss at (33, 20)
    Stamp {
        pattern_name: "hwss",
        reference_x: 33,
        reference_y: 20,
    },
    // 2: rpentomino at (35, 10)
    Stamp {
        pattern_name: "rpentomino",
        reference_x: 35,
        reference_y: 10,
    },
    // 3: pentadecathlon at (25, 30)
    Stamp {
        pattern_name: "pentadecathlon",
        reference_x: 25,
        reference_y: 30,
    },
    // 4: blinker at (25, 10)
    Stamp {
        pattern_name: "blinker",
        reference_x: 25,
        reference_y: 10,
    },
    // 5: pentadecathlon at (20, 15)
    Stamp {
        pattern_name: "pentadecathlon",
        reference_x: 20,
        reference_y: 15,
    },
    // 6: mwss at (40, 15)
    Stamp {
        pattern_name: "mwss",
        reference_x: 40,
        reference_y: 15,
    },
    // 7: glider at (13, 13)
    Stamp {
        pattern_name: "glider",
        reference_x: 13,
        reference_y: 13,
    },
    // 8: toad at (30, 15)
    Stamp {
        pattern_name: "toad",
        reference_x: 30,
        reference_y: 15,
    },
    // 9: gosperGun at (12, 10)
    Stamp {
        pattern_name: "gosperGun",
        reference_x: 12,
        reference_y: 10,
    },
    // 10: pulsar at (13, 24)
    Stamp {
        pattern_name: "pulsar",
        reference_x: 13,
        reference_y: 24,
    },
    // 11: acorn at (25, 25)
    Stamp {
        pattern_name: "acorn",
        reference_x: 25,
        reference_y: 25,
    },
    // 12: beacon at (25, 20)
    Stamp {
        pattern_name: "beacon",
        reference_x: 25,
        reference_y: 20,
    },
    // 13: pulsar at (36, 30)
    Stamp {
        pattern_name: "pulsar",
        reference_x: 36,
        reference_y: 30,
    },
];

/// Seeds the demo scene by applying all 13 stamps to the initial core
/// Returns the final core and cells after applying all stamps
/// This uses a simplified approach for startup without using the full interpreter
pub fn seed_demo_scene(initial_core: Core, store: &PatternStore) -> Result<(Core, gol_classic_core::Cells)> {
    use gol_classic_core::{Cells, translate};
    
    let mut current_core = initial_core;
    let mut current_cells = Cells::new();

    for stamp in DEMO_STAMPS {
        let reference = Position::new(stamp.reference_x, stamp.reference_y);
        
        // Get the pattern cells from the store
        let pattern_cells = store.get(stamp.pattern_name)?;
        
        // Translate and merge cells directly (simplified for startup)
        let translated = translate(&pattern_cells, &reference);
        current_cells.extend(translated.clone());
        current_core = current_core.with_cells(current_cells.clone());
    }

    Ok((current_core, current_cells))
}
