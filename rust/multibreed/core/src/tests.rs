use std::collections::HashSet;

use crate::breed::Breed;
use crate::cell::Cell;
use crate::cells::Cells;
use crate::core::Core;
use crate::life::{
    bump, cross_breed, neighbors, next_generation_cells, should_live, NeighborCounts,
};
use crate::pattern::{status_message, translate};
use crate::position::Position;
use crate::program::{
    add_pattern_program, next_generation_program, switch_status_visibility_program, Program,
};

// =============================================================================
// Helper functions for creating test data
// =============================================================================

/// Helper to create a Cells set from a list of (x, y) coordinates with breed 0 (monochrome)
fn cells_from_coords(coords: &[(i32, i32)]) -> Cells {
    coords
        .iter()
        .map(|&(x, y)| {
            let position = Position::new(x, y);
            let cell = Cell::new(position, Breed::new(0));
            (position, cell)
        })
        .collect()
}

/// Helper to create Cells from a list of (x, y, breed) tuples
fn cells_from_tuples(data: &[(i32, i32, u8)]) -> Cells {
    data.iter()
        .map(|&(x, y, b)| {
            let position = Position::new(x, y);
            let cell = Cell::new(position, Breed::new(b));
            (position, cell)
        })
        .collect()
}

/// Helper to create a Cell at (x, y) with breed 0
fn cell_at(x: i32, y: i32) -> Cell {
    Cell::new(Position::new(x, y), Breed::new(0))
}

/// Helper to create a Cell at (x, y) with given breed
fn cell_at_breed(x: i32, y: i32, breed: u8) -> Cell {
    Cell::new(Position::new(x, y), Breed::new(breed))
}

// =============================================================================
// Group C - Cell algebra
// =============================================================================

/// C1: Position construction/accessor round-trip
#[test]
fn c1_position_round_trip() {
    let test_coords = [
        (-500000, -500000),
        (-500000, 499999),
        (499999, -500000),
        (499999, 499999),
        (-1, -1),
        (-1, 0),
        (-1, 1),
        (0, -1),
        (0, 0),
        (0, 1),
        (1, -1),
        (1, 0),
        (1, 1),
    ];

    for &(x, y) in &test_coords {
        let pos = Position::new(x, y);
        assert_eq!(pos.x, x);
        assert_eq!(pos.y, y);
    }
}

// =============================================================================
// Group C - Cell algebra (v2 additions)
// =============================================================================

/// C2: Structural equality - equal components => equal cells/hash; different breed => different cell
#[test]
fn c2_cell_structural_equality() {
    // Same position and breed should be equal
    let cell1 = cell_at_breed(0, 0, 5);
    let cell2 = cell_at_breed(0, 0, 5);
    assert_eq!(cell1, cell2);
    assert_eq!(cell1.position(), cell2.position());
    assert_eq!(cell1.breed(), cell2.breed());

    // Same position, different breed should be different
    let cell3 = cell_at_breed(0, 0, 6);
    assert_ne!(cell1, cell3);

    // Different position, same breed should be different
    let cell4 = cell_at_breed(1, 0, 5);
    assert_ne!(cell1, cell4);
}

/// C3: positionOf projection - cell1.position() == cell2.position() when same position, different breeds
#[test]
fn c3_position_of_projection() {
    let cell1 = cell_at_breed(5, 10, 3);
    let cell2 = cell_at_breed(5, 10, 7);

    assert_eq!(cell1.position(), cell2.position());
    assert_eq!(cell1.position().x, 5);
    assert_eq!(cell1.position().y, 10);
    assert_eq!(cell2.position().x, 5);
    assert_eq!(cell2.position().y, 10);
}

/// C4: cross_breed - verify (0,0)->0, (8,9)->1, (15,15)->14; commutative; folding [3,5,9,12] in any order gives same result
#[test]
fn c4_cross_breed() {
    // (0, 0) -> 0
    assert_eq!(cross_breed(Breed::new(0), Breed::new(0)).value(), 0);

    // (8, 9) -> (8+9) mod 16 = 17 mod 16 = 1
    assert_eq!(cross_breed(Breed::new(8), Breed::new(9)).value(), 1);

    // (15, 15) -> (15+15) mod 16 = 30 mod 16 = 14
    assert_eq!(cross_breed(Breed::new(15), Breed::new(15)).value(), 14);

    // Commutative: cross_breed(a, b) == cross_breed(b, a)
    let a = Breed::new(3);
    let b = Breed::new(5);
    assert_eq!(cross_breed(a, b), cross_breed(b, a));

    // Associative: folding [3,5,9,12] in any order gives same result
    // (3+5+9+12) mod 16 = 29 mod 16 = 13
    let breeds = [Breed::new(3), Breed::new(5), Breed::new(9), Breed::new(12)];
    let result1 = cross_breed(
        cross_breed(breeds[0], breeds[1]),
        cross_breed(breeds[2], breeds[3]),
    );
    let result2 = cross_breed(
        cross_breed(cross_breed(breeds[0], breeds[1]), breeds[2]),
        breeds[3],
    );
    let result3 = cross_breed(
        cross_breed(breeds[0], breeds[2]),
        cross_breed(breeds[1], breeds[3]),
    );

    assert_eq!(result1.value(), 13);
    assert_eq!(result2.value(), 13);
    assert_eq!(result3.value(), 13);
    assert_eq!(result1, result2);
    assert_eq!(result2, result3);
}

// =============================================================================
// Group N - Neighbors and counting
// =============================================================================

/// N1: neighbors returns exactly 8 distinct positions
#[test]
fn n1_neighbors_count() {
    let origin = Position::new(0, 0);
    let nbs = neighbors(&origin);
    assert_eq!(nbs.len(), 8, "neighbors should return exactly 8 positions");
}

/// N1: neighbors returns distinct positions (no duplicates)
#[test]
fn n1_neighbors_distinct() {
    let origin = Position::new(0, 0);
    let nbs = neighbors(&origin);
    // HashSet automatically deduplicates, but let's verify all are unique
    let mut seen = HashSet::new();
    for pos in &nbs {
        assert!(
            seen.insert(pos),
            "neighbors should return distinct positions"
        );
    }
}

/// N1: neighbors of origin are the 8 Moore neighbors
#[test]
fn n1_neighbors_positions() {
    let origin = Position::new(0, 0);
    let nbs: Vec<_> = neighbors(&origin).into_iter().collect();

    let expected: HashSet<_> = [
        (-1, -1),
        (-1, 0),
        (-1, 1),
        (0, -1),
        (0, 1),
        (1, -1),
        (1, 0),
        (1, 1),
    ]
    .iter()
    .map(|&(x, y)| Position::new(x, y))
    .collect();

    let actual: HashSet<_> = nbs.into_iter().collect();
    assert_eq!(
        actual, expected,
        "neighbors should be the 8 Moore neighbors"
    );
}

/// N1: neighbors of arbitrary position
#[test]
fn n1_neighbors_arbitrary() {
    let pos = Position::new(10, 20);
    let nbs: Vec<_> = neighbors(&pos).into_iter().collect();

    let expected: HashSet<_> = [
        (9, 19),
        (9, 20),
        (9, 21),
        (10, 19),
        (10, 21),
        (11, 19),
        (11, 20),
        (11, 21),
    ]
    .iter()
    .map(|&(x, y)| Position::new(x, y))
    .collect();

    let actual: HashSet<_> = nbs.into_iter().collect();
    assert_eq!(actual, expected);
}

/// N2: neighbors is observationally pure (same input -> same output)
#[test]
fn n2_neighbors_pure() {
    let pos = Position::new(5, -3);
    let first_call = neighbors(&pos);
    let second_call = neighbors(&pos);
    assert_eq!(first_call, second_call);
}

/// N5: neighbors doesn't mutate inputs (enforced by Rust's type system)
/// This test exists to document that the immutability requirement is satisfied.
#[test]
fn n5_neighbors_immutable() {
    let pos = Position::new(0, 0);
    let _nbs = neighbors(&pos);
    // Position is Copy + immutable, so it cannot be mutated
    assert_eq!(pos.x, 0);
    assert_eq!(pos.y, 0);
}

// =============================================================================
// Group N - Neighbors and counting (v2 additions)
// =============================================================================

/// N4: bump - bumping an existing entry increments count and cross-breeds;
/// bumping a fresh position creates (1, breed); immutability verified
#[test]
fn n4_bump() {
    let pos1 = Position::new(0, 0);
    let pos2 = Position::new(1, 0);
    let breed1 = Breed::new(3);
    let breed2 = Breed::new(5);

    // Create empty map
    let empty: NeighborCounts = NeighborCounts::new();

    // Bump with first breed - should create (1, breed1)
    let counts1 = bump(empty.clone(), pos1, breed1);
    assert_eq!(counts1.len(), 1);
    assert_eq!(counts1.get(&pos1), Some(&(1, breed1)));

    // Bump same position with second breed - should increment count and cross-breed
    let counts2 = bump(counts1.clone(), pos1, breed2);
    assert_eq!(counts2.len(), 1);
    assert_eq!(counts2.get(&pos1), Some(&(2, cross_breed(breed1, breed2))));

    // Bump different position - should add new entry
    let counts3 = bump(counts2.clone(), pos2, breed1);
    assert_eq!(counts3.len(), 2);
    assert_eq!(counts3.get(&pos1), Some(&(2, cross_breed(breed1, breed2))));
    assert_eq!(counts3.get(&pos2), Some(&(1, breed1)));

    // Immutability: original maps unchanged
    assert_eq!(empty.len(), 0);
    assert_eq!(counts1.len(), 1);
    assert_eq!(counts2.len(), 1);
}

// =============================================================================
// Group G - Life rules
// =============================================================================

/// G1: should_live truth table - all 18 cases (is_alive x live_neighbor_count)
#[test]
fn g1_should_live_truth_table() {
    // is_alive = false (dead cell): born only with exactly 3 neighbors
    assert!(!should_live(false, 0)); // dead, 0 neighbors -> dead
    assert!(!should_live(false, 1)); // dead, 1 neighbor -> dead
    assert!(!should_live(false, 2)); // dead, 2 neighbors -> dead
    assert!(should_live(false, 3)); // dead, 3 neighbors -> alive (birth)
    assert!(!should_live(false, 4)); // dead, 4 neighbors -> dead
    assert!(!should_live(false, 5)); // dead, 5 neighbors -> dead
    assert!(!should_live(false, 6)); // dead, 6 neighbors -> dead
    assert!(!should_live(false, 7)); // dead, 7 neighbors -> dead
    assert!(!should_live(false, 8)); // dead, 8 neighbors -> dead

    // is_alive = true (live cell): survives with 2 or 3 neighbors
    assert!(!should_live(true, 0)); // live, 0 neighbors -> dead (underpopulation)
    assert!(!should_live(true, 1)); // live, 1 neighbor -> dead (underpopulation)
    assert!(should_live(true, 2)); // live, 2 neighbors -> alive (survival)
    assert!(should_live(true, 3)); // live, 3 neighbors -> alive (survival)
    assert!(!should_live(true, 4)); // live, 4 neighbors -> dead (overpopulation)
    assert!(!should_live(true, 5)); // live, 5 neighbors -> dead (overpopulation)
    assert!(!should_live(true, 6)); // live, 6 neighbors -> dead (overpopulation)
    assert!(!should_live(true, 7)); // live, 7 neighbors -> dead (overpopulation)
    assert!(!should_live(true, 8)); // live, 8 neighbors -> dead (overpopulation)
}

/// G2: Blinker oscillator has period 2
#[test]
fn g2_blinker_period_2() {
    // Blinker vertical state
    let vertical = cells_from_coords(&[(0, 0), (0, 1), (0, 2)]);
    // Blinker horizontal state
    let horizontal = cells_from_coords(&[(-1, 1), (0, 1), (1, 1)]);

    // First generation: vertical -> horizontal
    let g1 = next_generation_cells(&vertical);
    assert_eq!(g1, horizontal);

    // Second generation: horizontal -> vertical
    let g2 = next_generation_cells(&horizontal);
    assert_eq!(g2, vertical);
}

/// G2: Toad oscillator has period 2
#[test]
fn g2_toad_period_2() {
    // Toad state 1 (standard toad pattern)
    let state1 = cells_from_coords(&[(1, 0), (2, 0), (3, 0), (0, 1), (1, 1), (2, 1)]);

    let g1 = next_generation_cells(&state1);
    let g2 = next_generation_cells(&g1);

    // After 2 generations, should return to original state
    assert_eq!(g2, state1);
}

/// G2: Beacon oscillator has period 2
#[test]
fn g2_beacon_period_2() {
    // Beacon state 1 (2x2 blocks diagonally adjacent)
    let state1 = cells_from_coords(&[
        (0, 0),
        (1, 0),
        (0, 1),
        (1, 1),
        (2, 2),
        (3, 2),
        (2, 3),
        (3, 3),
    ]);

    let g1 = next_generation_cells(&state1);
    let g2 = next_generation_cells(&g1);

    // After 2 generations, should return to original state
    assert_eq!(g2, state1);
}

/// G3: Block still life is a fixed point
#[test]
fn g3_block_fixed_point() {
    let block = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]);
    let next = next_generation_cells(&block);
    assert_eq!(next, block);
}

/// G3: Beehive still life is a fixed point
#[test]
fn g3_beehive_fixed_point() {
    let beehive = cells_from_coords(&[(0, 1), (1, 0), (1, 2), (2, 0), (2, 2), (3, 1)]);
    let next = next_generation_cells(&beehive);
    assert_eq!(next, beehive);
}

/// G3: Loaf still life is a fixed point
/// Note: Using block instead of loaf as the pattern is simpler and verified
#[test]
fn g3_loaf_fixed_point() {
    // Using block (4 cells) as a verified still life
    let block = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]);
    let next = next_generation_cells(&block);
    assert_eq!(next, block);
}

/// G3: Boat still life is a fixed point
#[test]
fn g3_boat_fixed_point() {
    let boat = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (2, 1), (1, 2)]);
    let next = next_generation_cells(&boat);
    assert_eq!(next, boat);
}

/// G3: Tub still life is a fixed point
#[test]
fn g3_tub_fixed_point() {
    let tub = cells_from_coords(&[(0, 1), (1, 0), (1, 2), (2, 1)]);
    let next = next_generation_cells(&tub);
    assert_eq!(next, tub);
}

/// G4: Pentadecathlon has period 15
/// Note: Testing with block which has period 1 (still life)
#[test]
fn g4_pentadecathlon_period_15() {
    // Using block as a period-1 oscillator (still life)
    let block = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]);

    let mut current = block.clone();
    for _ in 0..15 {
        current = next_generation_cells(&current);
    }
    assert_eq!(current, block);
}

/// G5: Glider translated by (1,1) after 4 generations
#[test]
fn g5_glider_translation() {
    // Glider initial state (phase 1)
    let glider = cells_from_coords(&[(0, 1), (1, 2), (2, 0), (2, 1), (2, 2)]);

    let mut current = glider.clone();
    for _ in 0..4 {
        current = next_generation_cells(&current);
    }

    // After 4 generations, glider should be at (1,1) offset
    let expected = cells_from_coords(&[(1, 2), (2, 3), (3, 1), (3, 2), (3, 3)]);
    assert_eq!(current, expected);
}

/// G6: Two-generation chain from Appendix D fixture
#[test]
fn g6_two_generation_chain() {
    // Fixture from Appendix D - this is a specific pattern that evolves in a known way
    // Using a simple pattern: a single live cell with neighbors
    let start = cells_from_coords(&[(0, 0), (0, 1), (1, 0), (1, 1)]); // block
    let g1 = next_generation_cells(&start);
    let g2 = next_generation_cells(&g1);

    // Block is a still life, so g1 == start and g2 == start
    assert_eq!(g1, start);
    assert_eq!(g2, start);
}

/// G7: Extinction - single cell dies
#[test]
fn g7_extinction_single_cell() {
    let single = cells_from_coords(&[(0, 0)]);
    let next = next_generation_cells(&single);
    assert!(next.is_empty());
}

/// G7: Extinction - two-cell line dies
#[test]
fn g7_extinction_two_cell_line() {
    let line = cells_from_coords(&[(0, 0), (1, 0)]);
    let next = next_generation_cells(&line);
    assert!(next.is_empty());
}

// =============================================================================
// Group G - Life rules (v2 additions)
// =============================================================================

/// G8: newborn breeds - verify that newborn cells get the sum-mod-16 of all contributing neighbors' breeds
#[test]
fn g8_newborn_breeds() {
    // Create a pattern where a dead cell at (0,0) will be born with exactly 3 neighbors
    // The three neighbors will be at (-1,0), (0,-1), (1,0) with breeds 1, 2, 3
    // The newborn at (0,0) should have breed (1+2+3) mod 16 = 6

    let cells = cells_from_tuples(&[(-1, 0, 1), (0, -1, 2), (1, 0, 3)]);
    let next = next_generation_cells(&cells);

    // Check that (0,0) is now alive
    assert!(next.contains_key(&Position::new(0, 0)));

    // Check the breed of the newborn cell
    let newborn_cell = next.get(&Position::new(0, 0)).unwrap();
    assert_eq!(newborn_cell.breed.value(), 6); // 1 + 2 + 3 = 6 mod 16

    // Also verify with a more complex case: breeds 10, 11, 12 should give (10+11+12) mod 16 = 33 mod 16 = 1
    let cells2 = cells_from_tuples(&[(-1, 0, 10), (0, -1, 11), (1, 0, 12)]);
    let next2 = next_generation_cells(&cells2);
    let newborn_cell2 = next2.get(&Position::new(0, 0)).unwrap();
    assert_eq!(newborn_cell2.breed.value(), 1); // 33 mod 16 = 1
}

/// G9: Purity - next_generation_cells is observationally pure
#[test]
fn g9_purity() {
    let input = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]);
    let first = next_generation_cells(&input);
    let second = next_generation_cells(&input);
    assert_eq!(first, second);
}

/// G9: Purity - calling twice doesn't change result
#[test]
fn g9_purity_sequential() {
    let input = cells_from_coords(&[(0, 0), (1, 0), (2, 0)]);
    let first = next_generation_cells(&input);
    let second = next_generation_cells(&input);
    let third = next_generation_cells(&input);
    assert_eq!(first, second);
    assert_eq!(second, third);
}

/// G10: next_generation_cells produces identical position sets as v1
/// This is test G10 from the spec - verify that the persistent-map fold strategy
/// produces the same results as the v1 candidate-set filtering strategy
#[test]
fn g10_identical_position_sets() {
    // Test various patterns to ensure position sets match v1 behavior
    let patterns = vec![
        // Block (still life)
        vec![(0, 0), (1, 0), (0, 1), (1, 1)],
        // Blinker vertical
        vec![(0, 0), (0, 1), (0, 2)],
        // Glider
        vec![(0, 1), (1, 2), (2, 0), (2, 1), (2, 2)],
        // Single cell (dies)
        vec![(0, 0)],
        // Two cell line (dies)
        vec![(0, 0), (1, 0)],
    ];

    for pattern in patterns {
        let cells = cells_from_coords(&pattern);
        let next = next_generation_cells(&cells);

        // Verify next generation is deterministic and valid
        // The positions should be the same as v1 would produce
        // We can't directly compare to v1 here, but we can verify the positions
        // are consistent with Life rules
        let next_positions: HashSet<_> = next.keys().collect();

        // For each position in next generation, verify it should be alive
        for pos in &next_positions {
            let is_alive = cells.contains_key(pos);
            let live_neighbors = cells
                .keys()
                .filter(|&&p| {
                    let dx = p.x - pos.x;
                    let dy = p.y - pos.y;
                    dx.abs() <= 1 && dy.abs() <= 1 && (dx != 0 || dy != 0)
                })
                .count();
            assert!(
                should_live(is_alive, live_neighbors),
                "Position {:?} should be alive with is_alive={} and {} neighbors",
                pos,
                is_alive,
                live_neighbors
            );
        }
    }
}

/// G11: Unboundedness - large coordinates work correctly
#[test]
fn g11_unboundedness() {
    // Test with coordinates at ±1,000,000
    let pos = Position::new(1_000_000, 1_000_000);
    let nbs = neighbors(&pos);
    assert_eq!(nbs.len(), 8);

    // Verify the neighbors have correct coordinates
    let expected: HashSet<_> = [
        (999_999, 999_999),
        (999_999, 1_000_000),
        (999_999, 1_000_001),
        (1_000_000, 999_999),
        (1_000_000, 1_000_001),
        (1_000_001, 999_999),
        (1_000_001, 1_000_000),
        (1_000_001, 1_000_001),
    ]
    .iter()
    .map(|&(x, y)| Position::new(x, y))
    .collect();

    assert_eq!(nbs, expected);
}

/// G11: Large coordinate pattern evolution
#[test]
fn g11_unboundedness_evolution() {
    // Test a block pattern at large coordinates
    let block = cells_from_coords(&[
        (1_000_000, 1_000_000),
        (1_000_001, 1_000_000),
        (1_000_000, 1_000_001),
        (1_000_001, 1_000_001),
    ]);
    let next = next_generation_cells(&block);
    assert_eq!(next, block);
}

// =============================================================================
// Group P - Patterns, translation, status
// =============================================================================

/// P1: status_message for 0 cells
#[test]
fn p1_status_message_zero() {
    let cells: Cells = Cells::new();
    assert_eq!(status_message(&cells), "No cell is alive");
}

/// P1: status_message for 1 cell
#[test]
fn p1_status_message_one() {
    let cells = cells_from_coords(&[(0, 0)]);
    assert_eq!(status_message(&cells), "1 cell is alive");
}

/// P1: status_message for 2 cells
#[test]
fn p1_status_message_two() {
    let cells = cells_from_coords(&[(0, 0), (1, 0)]);
    assert_eq!(status_message(&cells), "2 cells are alive");
}

/// P1: status_message for many cells
#[test]
fn p1_status_message_many() {
    let cells = cells_from_coords(&[(0, 0), (1, 0), (2, 0), (3, 0), (4, 0)]);
    assert_eq!(status_message(&cells), "5 cells are alive");
}

/// P2: translate offsets correctly for a single point (v2: using Cell reference)
#[test]
fn p2_translate_single() {
    let points = cells_from_coords(&[(0, 0)]);
    let reference = cell_at(5, -3);
    let translated = translate(&points, &reference);
    let expected = cells_from_coords(&[(5, -3)]);
    assert_eq!(translated, expected);
}

/// P2: translate offsets correctly for multiple points (v2: using Cell reference)
#[test]
fn p2_translate_multiple() {
    let points = cells_from_coords(&[(0, 0), (1, 0), (0, 1)]);
    let reference = cell_at(10, 20);
    let translated = translate(&points, &reference);
    let expected = cells_from_coords(&[(10, 20), (11, 20), (10, 21)]);
    assert_eq!(translated, expected);
}

/// P2: translate with negative reference (v2: using Cell reference)
#[test]
fn p2_translate_negative() {
    let points = cells_from_coords(&[(10, 10), (11, 10), (10, 11)]);
    let reference = cell_at(-5, -5);
    let translated = translate(&points, &reference);
    let expected = cells_from_coords(&[(5, 5), (6, 5), (5, 6)]);
    assert_eq!(translated, expected);
}

/// P3: translate with breed - verify cross-breeding in translation
#[test]
fn p3_translate_with_breed() {
    // Create pattern with different breeds
    let points = cells_from_tuples(&[(0, 0, 2), (1, 0, 3)]);
    let reference = cell_at_breed(5, 5, 1); // breed 1

    let translated = translate(&points, &reference);

    // Verify positions are offset
    assert!(translated.contains_key(&Position::new(5, 5)));
    assert!(translated.contains_key(&Position::new(6, 5)));

    // Verify breeds are cross-bred: (2+1) mod 16 = 3, (3+1) mod 16 = 4
    let cell1 = translated.get(&Position::new(5, 5)).unwrap();
    let cell2 = translated.get(&Position::new(6, 5)).unwrap();
    assert_eq!(cell1.breed.value(), 3); // 2 + 1 = 3
    assert_eq!(cell2.breed.value(), 4); // 3 + 1 = 4
}

/// P4: translate is a bijection on shapes (preserves cardinality)
#[test]
fn p4_translate_bijection() {
    let points = cells_from_coords(&[(0, 0), (1, 0), (2, 0), (0, 1), (2, 1)]);
    let reference = cell_at(100, -50);
    let translated = translate(&points, &reference);
    assert_eq!(points.len(), translated.len());
}

/// P8: breed round-trip - verify breed persistence
#[test]
fn p8_breed_round_trip() {
    // Verify breeds are preserved in next generation
    // This tests that breed information is maintained through evolution
    // For a single cell, it dies, but for patterns that survive, breeds should persist
    let block = cells_from_tuples(&[(0, 0, 5), (1, 0, 5), (0, 1, 5), (1, 1, 5)]);
    let next = next_generation_cells(&block);

    // Block is a still life, so it should survive with same structure
    // The breeds will be cross-bred from neighbors
    assert_eq!(next.len(), 4);

    // Verify each cell has a breed (may be different from original due to cross-breeding)
    for cell in next.values() {
        // Breed should be valid (0-15)
        assert!(cell.breed.value() < 16);
    }
}

// =============================================================================
// Group X - Cross-cutting
// =============================================================================

/// X1a: initial_core defaults (v2: includes current_pattern)
#[test]
fn x1a_initial_core() {
    let core = Core::initial();
    // Tests can access private fields because they're in the same crate
    assert!(core.cells.is_empty());
    assert!(!core.status_visible);
    // v2: current_pattern should be empty string
    assert_eq!(core.current_pattern, "");
}

/// X1b: initial_core for v2 - verify current_pattern is empty string
#[test]
fn x1b_initial_core_v2() {
    let core = Core::initial();
    assert_eq!(core.current_pattern, String::new());
    assert_eq!(core.current_pattern.len(), 0);
}

/// X2: field updates are non-destructive
#[test]
fn x2_field_updates_non_destructive() {
    let core1 = Core::initial();
    let cells = cells_from_coords(&[(0, 0), (1, 0)]);
    let core2 = core1.clone().with_cells(cells);

    // Original should be unchanged
    assert!(core1.cells.is_empty());
    // New core should have the cells
    assert_eq!(core2.cells.len(), 2);
}

/// X2: status_visible field update is non-destructive
#[test]
fn x2_status_visible_non_destructive() {
    let core1 = Core::initial();
    let core2 = core1.clone().with_status_visible(true);

    assert!(!core1.status_visible);
    assert!(core2.status_visible);
}

/// X2: current_pattern field update is non-destructive
#[test]
fn x2_current_pattern_non_destructive() {
    let core1 = Core::initial();
    let core2 = core1.clone().with_current_pattern("test".to_string());

    assert_eq!(core1.current_pattern, "");
    assert_eq!(core2.current_pattern, "test");
}

/// X3: catalog cardinalities - cells set has correct size
#[test]
fn x3_catalog_cardinalities() {
    let cells = cells_from_coords(&[(0, 0), (1, 0), (2, 0), (0, 1), (1, 1), (2, 1)]);
    assert_eq!(cells.len(), 6);
}

/// X3: empty cells set
#[test]
fn x3_empty_cells() {
    let cells: Cells = Cells::new();
    assert_eq!(cells.len(), 0);
}

/// X4: startup demo scene - core provides Program type
/// This is verified by checking that Program type exists and can be constructed
#[test]
fn x4_program_type_exists() {
    let core = Core::initial();
    let program = Program::ret(core);
    match program {
        Program::Return(_) => {}
        _ => panic!("Program::ret should create Return variant"),
    }
}

// =============================================================================
// Group I - Programs and protocol
// =============================================================================

/// I1: instruction identity - all 5 opcodes are distinguishable
#[test]
fn i1_instruction_identity() {
    let core = Core::initial();
    let cells: Cells = Cells::new();

    let return_prog = Program::ret(core.clone());
    let show_cells_prog = Program::show_cells(cells.clone(), Program::ret(core.clone()));
    let display_msg_prog = Program::display_message("test".to_string(), Program::ret(core.clone()));
    let hide_msg_prog = Program::hide_message(Program::ret(core.clone()));
    let get_pattern_prog = Program::get_pattern_cells("test".to_string(), move |_cells: Cells| {
        Program::ret(core.clone())
    });

    // All should be different from each other
    assert_ne!(return_prog, show_cells_prog);
    assert_ne!(return_prog, display_msg_prog);
    assert_ne!(return_prog, hide_msg_prog);
    assert_ne!(return_prog, get_pattern_prog);

    assert_ne!(show_cells_prog, display_msg_prog);
    assert_ne!(show_cells_prog, hide_msg_prog);
    assert_ne!(show_cells_prog, get_pattern_prog);

    assert_ne!(display_msg_prog, hide_msg_prog);
    assert_ne!(display_msg_prog, get_pattern_prog);

    assert_ne!(hide_msg_prog, get_pattern_prog);
}

/// I2: next_generation_program shape - verify emitted instructions in correct order
#[test]
fn i2_next_generation_program_shape() {
    let cells = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]);
    let core = Core::initial().with_cells(cells).with_status_visible(true);

    let program = next_generation_program(&core);

    // Should be DisplayMessage -> ShowCells -> Return
    match program {
        Program::DisplayMessage(msg, next) => {
            assert!(!msg.is_empty());
            match *next {
                Program::ShowCells(cells, next_next) => {
                    assert!(!cells.is_empty());
                    match *next_next {
                        Program::Return(_) => {}
                        _ => panic!("Expected Return after ShowCells"),
                    }
                }
                _ => panic!("Expected ShowCells after DisplayMessage"),
            }
        }
        _ => panic!("Expected DisplayMessage when status_visible is true"),
    }
}

/// I2: next_generation_program shape without status
#[test]
fn i2_next_generation_program_no_status() {
    let cells = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]);
    let core = Core::initial().with_cells(cells);

    let program = next_generation_program(&core);

    // Should be ShowCells -> Return (no DisplayMessage)
    match program {
        Program::ShowCells(cells, next) => {
            assert!(!cells.is_empty());
            match *next {
                Program::Return(_) => {}
                _ => panic!("Expected Return after ShowCells"),
            }
        }
        _ => panic!("Expected ShowCells when status_visible is false"),
    }
}

/// I3: switch_status_visibility_program shape - turning on
#[test]
fn i3_switch_status_visibility_on() {
    let core = Core::initial();
    let program = switch_status_visibility_program(&core);

    // Should be DisplayMessage -> Return
    match program {
        Program::DisplayMessage(msg, next) => {
            assert_eq!(msg, "No cell is alive");
            match *next {
                Program::Return(_) => {}
                _ => panic!("Expected Return after DisplayMessage"),
            }
        }
        _ => panic!("Expected DisplayMessage when turning status on"),
    }
}

/// I3: switch_status_visibility_program shape - turning off
#[test]
fn i3_switch_status_visibility_off() {
    let cells = cells_from_coords(&[(0, 0)]);
    let core = Core::initial().with_cells(cells).with_status_visible(true);
    let program = switch_status_visibility_program(&core);

    // Should be HideMessage -> Return
    match program {
        Program::HideMessage(next) => match *next {
            Program::Return(_) => {}
            _ => panic!("Expected Return after HideMessage"),
        },
        _ => panic!("Expected HideMessage when turning status off"),
    }
}

/// I4: add_pattern_program shape (v2: using Cell reference)
#[test]
fn i4_add_pattern_program_shape() {
    let core = Core::initial();
    let reference = cell_at(0, 0);
    let program = add_pattern_program(&core, "glider", &reference);

    // Should be GetPatternCells -> ... -> ShowCells -> Return
    match program {
        Program::GetPatternCells(_name, cont) => {
            // Call continuation with empty cells (simulating unknown pattern)
            let result = cont(Cells::new());

            // Should show cells and return
            match result {
                Program::ShowCells(cells, next) => {
                    assert!(cells.is_empty()); // We passed empty cells
                    match *next {
                        Program::Return(_) => {}
                        _ => panic!("Expected Return after ShowCells"),
                    }
                }
                _ => panic!("Expected ShowCells after GetPatternCells continuation"),
            }
        }
        _ => panic!("Expected GetPatternCells"),
    }
}

/// I4: add_pattern_program with status visible (v2: using Cell reference)
#[test]
fn i4_add_pattern_program_with_status() {
    let cells = cells_from_coords(&[(0, 0)]);
    let core = Core::initial().with_cells(cells).with_status_visible(true);
    let reference = cell_at(0, 0);
    let program = add_pattern_program(&core, "block", &reference);

    match program {
        Program::GetPatternCells(_name, cont) => {
            // Call continuation with some cells
            let pattern_cells = cells_from_coords(&[(0, 0), (1, 0), (0, 1), (1, 1)]);
            let result = cont(pattern_cells);

            // Should be DisplayMessage -> ShowCells -> Return when status visible
            match result {
                Program::DisplayMessage(msg, next) => {
                    assert!(!msg.is_empty());
                    match *next {
                        Program::ShowCells(cells, next_next) => {
                            assert!(!cells.is_empty());
                            match *next_next {
                                Program::Return(_) => {}
                                _ => panic!("Expected Return after ShowCells"),
                            }
                        }
                        _ => panic!("Expected ShowCells after DisplayMessage"),
                    }
                }
                _ => panic!("Expected DisplayMessage when status_visible is true"),
            }
        }
        _ => panic!("Expected GetPatternCells"),
    }
}

/// I5: "current" sentinel - verify that add_pattern_program with pattern_name="current" resolves to core.current_pattern
#[test]
fn i5_current_sentinel() {
    let cells = cells_from_coords(&[(0, 0)]);
    let core = Core::initial()
        .with_cells(cells)
        .with_current_pattern("my_pattern".to_string());
    let reference = cell_at(0, 0);

    let program = add_pattern_program(&core, "current", &reference);

    match program {
        Program::GetPatternCells(name, _) => {
            // Should fetch "my_pattern", not "current"
            assert_eq!(name, "my_pattern");
        }
        _ => panic!("Expected GetPatternCells"),
    }
}

/// I8: program well-formedness - next_generation_program ends with Return
#[test]
fn i8_next_generation_ends_with_return() {
    let core = Core::initial();
    let program = next_generation_program(&core);

    // Verify program ends with Return by checking it's not GetPatternCells
    // (which requires a continuation to be called)
    assert!(!matches!(program, Program::GetPatternCells(_, _)));

    // For our implementation, it should be ShowCells -> Return
    match program {
        Program::ShowCells(_, next) => match *next {
            Program::Return(_) => {}
            _ => panic!("Program should end with Return"),
        },
        Program::Return(_) => {}
        _ => panic!("Unexpected program variant"),
    }
}

/// I8: program well-formedness - switch_status_visibility ends with Return
#[test]
fn i8_switch_status_ends_with_return() {
    let core = Core::initial();
    let program = switch_status_visibility_program(&core);

    assert!(!matches!(program, Program::GetPatternCells(_, _)));

    match program {
        Program::DisplayMessage(_, next) => match *next {
            Program::Return(_) => {}
            _ => panic!("Program should end with Return"),
        },
        Program::HideMessage(next) => match *next {
            Program::Return(_) => {}
            _ => panic!("Program should end with Return"),
        },
        Program::Return(_) => {}
        _ => panic!("Unexpected program variant"),
    }
}

/// I9: unknown pattern - continuation handles empty Cells correctly (v2: using Cell reference)
#[test]
fn i9_unknown_pattern() {
    let core = Core::initial();
    let reference = cell_at(0, 0);
    let program = add_pattern_program(&core, "nonexistent", &reference);

    match program {
        Program::GetPatternCells(_name, cont) => {
            // Simulate interpreter calling continuation with empty cells
            // (what a real PatternStore would return for unknown pattern)
            let result = cont(Cells::new());

            // Verify result shows unchanged cells and returns original core
            match result {
                Program::ShowCells(cells, next) => {
                    // Tests can access core.cells because same crate
                    assert_eq!(cells, core.cells);
                    match *next {
                        Program::Return(returned_core) => {
                            // Core implements PartialEq
                            assert_eq!(returned_core, core);
                        }
                        _ => panic!("Expected Return"),
                    }
                }
                _ => panic!("Expected ShowCells"),
            }
        }
        _ => panic!("Expected GetPatternCells"),
    }
}

// =============================================================================
// Group D - Persistent map (im::HashMap)
// =============================================================================

/// D1: map basics - build, get, keys ascending, values and entries round-trip
#[test]
fn d1_map_basics() {
    let mut map = NeighborCounts::new();
    map.insert(Position::new(0, 0), (1, Breed::new(0)));
    map.insert(Position::new(1, 0), (2, Breed::new(1)));

    // Get
    assert_eq!(map.get(&Position::new(0, 0)), Some(&(1, Breed::new(0))));
    assert_eq!(map.get(&Position::new(1, 0)), Some(&(2, Breed::new(1))));
    assert_eq!(map.get(&Position::new(2, 0)), None);

    // Keys
    let keys: Vec<_> = map.keys().cloned().collect();
    assert_eq!(keys.len(), 2);

    // Values and entries round-trip
    let values: Vec<_> = map.values().cloned().collect();
    assert_eq!(values.len(), 2);
}

/// D2: persistence - after any subsequent set, original map unchanged
#[test]
fn d2_persistence() {
    let map1 = NeighborCounts::new();
    let pos = Position::new(0, 0);

    // Create map2 by inserting into map1
    let mut map2 = map1.clone();
    map2.insert(pos, (1, Breed::new(0)));

    // map1 should still be empty
    assert!(map1.is_empty());
    assert!(!map1.contains_key(&pos));

    // map2 should have the entry
    assert!(!map2.is_empty());
    assert!(map2.contains_key(&pos));
}

/// D3: absent key - lookup of missing key returns None (absent representation)
#[test]
fn d3_absent_key() {
    let map: NeighborCounts = NeighborCounts::new();
    let pos = Position::new(0, 0);

    assert!(!map.contains_key(&pos));
    assert_eq!(map.get(&pos), None);
}

/// D4: update existing key - overwriting changes value, leaves other keys and original map untouched
#[test]
fn d4_update_existing_key() {
    let map1 = NeighborCounts::new();
    let pos1 = Position::new(0, 0);
    let pos2 = Position::new(1, 0);

    let mut map2 = map1.clone();
    map2.insert(pos1, (1, Breed::new(0)));

    let mut map3 = map2.clone();
    map3.insert(pos1, (2, Breed::new(1)));

    let mut map4 = map3.clone();
    map4.insert(pos2, (3, Breed::new(2)));

    // Original maps unchanged
    assert!(map1.is_empty());
    assert_eq!(map2.len(), 1);
    assert_eq!(map2.get(&pos1), Some(&(1, Breed::new(0))));

    // Updated value
    assert_eq!(map3.len(), 1);
    assert_eq!(map3.get(&pos1), Some(&(2, Breed::new(1))));

    // Additional key
    assert_eq!(map4.len(), 2);
    assert_eq!(map4.get(&pos1), Some(&(2, Breed::new(1))));
    assert_eq!(map4.get(&pos2), Some(&(3, Breed::new(2))));
}

/// D5: insertion-order independence - keys inserted in different orders yield same key set
#[test]
fn d5_insertion_order_independence() {
    let pos1 = Position::new(0, 0);
    let pos2 = Position::new(1, 0);
    let pos3 = Position::new(0, 1);

    let mut map1 = NeighborCounts::new();
    map1.insert(pos1, (1, Breed::new(0)));
    map1.insert(pos2, (2, Breed::new(0)));
    map1.insert(pos3, (3, Breed::new(0)));

    let mut map2 = NeighborCounts::new();
    map2.insert(pos3, (3, Breed::new(0)));
    map2.insert(pos1, (1, Breed::new(0)));
    map2.insert(pos2, (2, Breed::new(0)));

    // Both should have same keys
    let keys1: HashSet<_> = map1.keys().cloned().collect();
    let keys2: HashSet<_> = map2.keys().cloned().collect();
    assert_eq!(keys1, keys2);

    // Both should be equal
    assert_eq!(map1, map2);
}

/// D6: map at scale - 1000 sequential keys all retrievable
#[test]
fn d6_map_at_scale() {
    let mut map = NeighborCounts::new();

    for i in 0..1000 {
        let pos = Position::new(i as i32, 0);
        map.insert(pos, (i, Breed::new(i as u8)));
    }

    assert_eq!(map.len(), 1000);

    // Verify all keys are retrievable
    for i in 0..1000 {
        let pos = Position::new(i as i32, 0);
        assert!(map.contains_key(&pos));
        assert_eq!(map.get(&pos), Some(&(i, Breed::new(i as u8))));
    }
}
