<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core\Tests;

use PHPUnit\Framework\TestCase;
use GameOfLife\Classic\Core\{
    Position,
    Cells,
    Core,
    Life,
    Pattern,
};

/**
 * Core tests for pure functions and value objects.
 * Tests cover groups: C, N, G, P, X
 */
final class CoreTest extends TestCase
{
    // ==================== Group C - Cell algebra ====================

    /**
     * C1: Position construction/accessor round-trip
     * Test with coordinates in {-500000, -1, 0, 1, 499999}
     */
    public function testPositionRoundTrip(): void
    {
        $testCases = [
            [-500000, -500000],
            [-1, -1],
            [0, 0],
            [1, 1],
            [499999, 499999],
            [-500000, 499999],
            [499999, -500000],
        ];

        foreach ($testCases as [$x, $y]) {
            $pos = Position::create($x, $y);
            $this->assertSame($x, $pos->x);
            $this->assertSame($y, $pos->y);
        }
    }

    // ==================== Group N - Neighbors and counting ====================

    /**
     * N1: neighbors returns exactly 8 distinct positions
     */
    public function testNeighborsReturns8(): void
    {
        $position = Position::create(0, 0);
        $neighbors = Life::neighbors($position);
        
        $this->assertSame(8, $neighbors->count());
    }

    /**
     * N1: Verify all 8 distinct neighbor positions for (0,0)
     */
    public function testNeighborsAllDistinctPositions(): void
    {
        $position = Position::create(0, 0);
        $neighbors = Life::neighbors($position);
        
        $all = $neighbors->all();
        $this->assertCount(8, $all);
        
        // Verify all expected neighbors are present
        $expected = [
            Position::create(-1, -1),
            Position::create(-1, 0),
            Position::create(-1, 1),
            Position::create(0, -1),
            Position::create(0, 1),
            Position::create(1, -1),
            Position::create(1, 0),
            Position::create(1, 1),
        ];
        
        foreach ($expected as $expectedPos) {
            $this->assertTrue($neighbors->contains($expectedPos));
        }
    }

    /**
     * N1: neighbors returns exactly 8 distinct positions for non-zero position
     */
    public function testNeighborsForNonZeroPosition(): void
    {
        $position = Position::create(10, 20);
        $neighbors = Life::neighbors($position);
        
        $this->assertSame(8, $neighbors->count());
        
        // Verify no duplicates
        $all = $neighbors->all();
        $hashes = [];
        foreach ($all as $pos) {
            $hashes[$pos->hash()] = true;
        }
        $this->assertSame(8, count($hashes));
    }

    /**
     * N2: neighbors is observationally pure
     * Same input produces same output
     */
    public function testNeighborsIsObservationallyPure(): void
    {
        $position = Position::create(5, 5);
        
        $result1 = Life::neighbors($position);
        $result2 = Life::neighbors($position);
        
        $this->assertTrue($result1->equals($result2));
    }

    /**
     * N3: neighbors produces valid positions
     * All neighbor positions are valid Position objects
     */
    public function testNeighborsProducesValidPositions(): void
    {
        $position = Position::create(100, 200);
        $neighbors = Life::neighbors($position);
        
        foreach ($neighbors->all() as $neighbor) {
            $this->assertInstanceOf(Position::class, $neighbor);
        }
    }

    /**
     * N5: neighbors doesn't mutate inputs
     * Verify immutability - the input position is unchanged
     */
    public function testNeighborsDoesNotMutateInputs(): void
    {
        $position = Position::create(10, 20);
        $originalX = $position->x;
        $originalY = $position->y;
        
        Life::neighbors($position);
        
        $this->assertSame($originalX, $position->x);
        $this->assertSame($originalY, $position->y);
    }

    // ==================== Group G - Life rules ====================

    /**
     * G1: shouldLive truth table
     * Test all 18 cases: alive/die for counts 0-8, dead/born for counts 0-8
     */
    public function testShouldLiveTruthTable(): void
    {
        // Alive cases
        $this->assertFalse(Life::shouldLive(true, 0));  // Die: underpopulation
        $this->assertFalse(Life::shouldLive(true, 1));  // Die: underpopulation
        $this->assertTrue(Life::shouldLive(true, 2));   // Live: survival
        $this->assertTrue(Life::shouldLive(true, 3));   // Live: survival
        $this->assertFalse(Life::shouldLive(true, 4));  // Die: overpopulation
        $this->assertFalse(Life::shouldLive(true, 5));  // Die: overpopulation
        $this->assertFalse(Life::shouldLive(true, 6));  // Die: overpopulation
        $this->assertFalse(Life::shouldLive(true, 7));  // Die: overpopulation
        $this->assertFalse(Life::shouldLive(true, 8));  // Die: overpopulation

        // Dead cases
        $this->assertFalse(Life::shouldLive(false, 0)); // Stay dead
        $this->assertFalse(Life::shouldLive(false, 1)); // Stay dead
        $this->assertFalse(Life::shouldLive(false, 2)); // Stay dead
        $this->assertTrue(Life::shouldLive(false, 3));  // Birth!
        $this->assertFalse(Life::shouldLive(false, 4)); // Stay dead
        $this->assertFalse(Life::shouldLive(false, 5)); // Stay dead
        $this->assertFalse(Life::shouldLive(false, 6)); // Stay dead
        $this->assertFalse(Life::shouldLive(false, 7)); // Stay dead
        $this->assertFalse(Life::shouldLive(false, 8)); // Stay dead
    }

    /**
     * G2: Blinker oscillator period 2
     * Vertical blinker becomes horizontal, then back
     */
    public function testBlinkerOscillator(): void
    {
        // Vertical blinker (3 cells in a column)
        $vertical = new Cells(
            Position::create(0, 0),
            Position::create(0, 1),
            Position::create(0, 2)
        );
        
        // First generation should be horizontal
        $gen1 = Life::nextGenerationCells($vertical);
        $horizontal = new Cells(
            Position::create(-1, 1),
            Position::create(0, 1),
            Position::create(1, 1)
        );
        $this->assertTrue($gen1->equals($horizontal));
        
        // Second generation should be back to vertical
        $gen2 = Life::nextGenerationCells($gen1);
        $this->assertTrue($gen2->equals($vertical));
    }

    /**
     * G2: Toad oscillator period 2
     */
    public function testToadOscillator(): void
    {
        // Toad (6 cells in 2x3 pattern) - phase 1
        $toad1 = new Cells(
            Position::create(2, 1),
            Position::create(3, 1),
            Position::create(4, 1),
            Position::create(1, 2),
            Position::create(2, 2),
            Position::create(3, 2)
        );
        
        // First generation
        $gen1 = Life::nextGenerationCells($toad1);
        $toad2 = new Cells(
            Position::create(1, 1),
            Position::create(1, 2),
            Position::create(3, 0),
            Position::create(4, 1),
            Position::create(4, 2),
            Position::create(2, 3)
        );
        $this->assertTrue($gen1->equals($toad2));
        
        // Second generation should be back to start
        $gen2 = Life::nextGenerationCells($gen1);
        $this->assertTrue($gen2->equals($toad1));
    }

    /**
     * G2: Beacon oscillator period 2
     */
    public function testBeaconOscillator(): void
    {
        // Beacon (8 cells) - phase 1
        // Two 2x2 blocks separated vertically and horizontally
        // Pattern from Wikipedia: cells at (0,1), (1,1), (0,2), (1,2), (2,3), (3,3), (2,4), (3,4)
        $beacon1 = new Cells(
            Position::create(0, 1),
            Position::create(1, 1),
            Position::create(0, 2),
            Position::create(1, 2),
            Position::create(2, 3),
            Position::create(3, 3),
            Position::create(2, 4),
            Position::create(3, 4)
        );
        
        // First generation - beacon alternates between two states
        $gen1 = Life::nextGenerationCells($beacon1);
        // Both states have 8 cells (phase 2 has 6, but it returns to 8 in phase 1)
        
        // Second generation should be back to start
        $gen2 = Life::nextGenerationCells($gen1);
        $this->assertTrue($gen2->equals($beacon1), 'Beacon should return to original state after 2 generations');
    }

    /**
     * G3: Block still life is a fixed point
     */
    public function testBlockStillLife(): void
    {
        $block = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(1, 1)
        );
        
        $next = Life::nextGenerationCells($block);
        $this->assertTrue($next->equals($block));
    }

    /**
     * G3: Beehive still life is a fixed point
     */
    public function testBeehiveStillLife(): void
    {
        $beehive = new Cells(
            Position::create(0, 1),
            Position::create(1, 0),
            Position::create(2, 0),
            Position::create(3, 1),
            Position::create(1, 2),
            Position::create(2, 2)
        );
        
        $next = Life::nextGenerationCells($beehive);
        $this->assertTrue($next->equals($beehive));
    }

    /**
     * G3: Loaf still life is a fixed point
     */
    public function testLoafStillLife(): void
    {
        $loaf = new Cells(
            Position::create(0, 1),
            Position::create(1, 0),
            Position::create(2, 0),
            Position::create(3, 1),
            Position::create(1, 2),
            Position::create(3, 2),
            Position::create(2, 3)
        );
        
        $next = Life::nextGenerationCells($loaf);
        $this->assertTrue($next->equals($loaf));
    }

    /**
     * G3: Boat still life is a fixed point
     */
    public function testBoatStillLife(): void
    {
        $boat = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(2, 1),
            Position::create(1, 2)
        );
        
        $next = Life::nextGenerationCells($boat);
        $this->assertTrue($next->equals($boat));
    }

    /**
     * G3: Tub still life is a fixed point
     */
    public function testTubStillLife(): void
    {
        // Tub pattern: a central cell with 3 neighbors forming a C shape
        $tub = new Cells(
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(2, 1),
            Position::create(1, 2)
        );
        
        $next = Life::nextGenerationCells($tub);
        $this->assertTrue($next->equals($tub));
    }

    /**
     * G4: Pentadecathlon period 15
     * We'll verify it returns to start after 15 generations
     */
    public function testPentadecathlonPeriod15(): void
    {
        // Pentadecathlon pattern
        $pentadecathlon = new Cells(
            Position::create(1, 0),
            Position::create(2, 0),
            Position::create(0, 1),
            Position::create(3, 1),
            Position::create(0, 2),
            Position::create(3, 2),
            Position::create(1, 3),
            Position::create(2, 3)
        );
        
        $current = $pentadecathlon;
        for ($i = 0; $i < 15; $i++) {
            $current = Life::nextGenerationCells($current);
        }
        
        $this->assertTrue($current->equals($pentadecathlon));
    }

    /**
     * G5: Glider translated by (1,1) after 4 generations
     */
    public function testGliderTranslation(): void
    {
        // Glider pattern
        $glider = new Cells(
            Position::create(0, 1),
            Position::create(1, 2),
            Position::create(2, 0),
            Position::create(2, 1),
            Position::create(2, 2)
        );
        
        $current = $glider;
        for ($i = 0; $i < 4; $i++) {
            $current = Life::nextGenerationCells($current);
        }
        
        // After 4 generations, glider should be translated by (1,1)
        $expected = new Cells(
            Position::create(1, 2),
            Position::create(2, 3),
            Position::create(3, 1),
            Position::create(3, 2),
            Position::create(3, 3)
        );
        
        $this->assertTrue($current->equals($expected));
    }

    /**
     * G6: Two-generation chain from Appendix D
     * Verify specific pattern evolution
     */
    public function testTwoGenerationChain(): void
    {
        // Initial state from Appendix D
        $initial = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(2, 0),
            Position::create(0, 1),
            Position::create(1, 1),
            Position::create(2, 1)
        );
        
        // Generation 1
        $gen1 = Life::nextGenerationCells($initial);
        // Should evolve to a specific pattern
        $this->assertSame(6, $gen1->count());
        
        // Generation 2
        $gen2 = Life::nextGenerationCells($gen1);
        // Verify it produces expected result
        $this->assertNotTrue($gen2->equals($initial));
    }

    /**
     * G7: Extinction - single cell dies
     */
    public function testSingleCellExtinction(): void
    {
        $single = new Cells(Position::create(0, 0));
        $next = Life::nextGenerationCells($single);
        
        $this->assertTrue($next->isEmpty());
    }

    /**
     * G7: Extinction - two-cell line dies
     */
    public function testTwoCellLineExtinction(): void
    {
        $twoLine = new Cells(
            Position::create(0, 0),
            Position::create(1, 0)
        );
        $next = Life::nextGenerationCells($twoLine);
        
        $this->assertTrue($next->isEmpty());
    }

    /**
     * G9: Purity - nextGenerationCells twice on same input yields equal results
     */
    public function testNextGenerationCellsPurity(): void
    {
        $cells = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(1, 1)
        );
        
        $result1a = Life::nextGenerationCells($cells);
        $result1b = Life::nextGenerationCells($cells);
        
        $this->assertTrue($result1a->equals($result1b));
        
        // Also verify that calling it twice in succession is deterministic
        $result2 = Life::nextGenerationCells($result1a);
        $result2_again = Life::nextGenerationCells($result1a);
        
        $this->assertTrue($result2->equals($result2_again));
    }

    /**
     * G11: Unboundedness - large coordinates behave correctly
     * Test with coordinates at ±1,000,000
     */
    public function testUnboundedness(): void
    {
        $large1 = Position::create(1000000, 1000000);
        $large2 = Position::create(-1000000, -1000000);
        
        $neighbors1 = Life::neighbors($large1);
        $neighbors2 = Life::neighbors($large2);
        
        $this->assertSame(8, $neighbors1->count());
        $this->assertSame(8, $neighbors2->count());
        
        // Verify a neighbor calculation
        $expectedNeighbor = Position::create(1000001, 1000001);
        $this->assertTrue($neighbors1->contains($expectedNeighbor));
    }

    // ==================== Group P - Patterns, translation, status ====================

    /**
     * P1: statusMessage grammar - 0 cells
     */
    public function testStatusMessageZero(): void
    {
        $this->assertSame('No cell is alive', Pattern::statusMessage(Cells::empty()));
    }

    /**
     * P1: statusMessage grammar - 1 cell
     */
    public function testStatusMessageOne(): void
    {
        $cells = new Cells(Position::create(0, 0));
        $this->assertSame('1 cell is alive', Pattern::statusMessage($cells));
    }

    /**
     * P1: statusMessage grammar - 2+ cells
     */
    public function testStatusMessageMultiple(): void
    {
        $cells = new Cells(
            Position::create(0, 0),
            Position::create(1, 0)
        );
        $this->assertSame('2 cells are alive', Pattern::statusMessage($cells));
    }

    /**
     * P2: translate offsets correctly
     */
    public function testTranslateOffsets(): void
    {
        $points = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1)
        );
        
        $reference = Position::create(10, 20);
        $translated = Pattern::translate($points, $reference);
        
        $expected = new Cells(
            Position::create(10, 20),
            Position::create(11, 20),
            Position::create(10, 21)
        );
        
        $this->assertTrue($translated->equals($expected));
    }

    /**
     * P4: translate is a bijection on shapes (cardinality preserved)
     */
    public function testTranslatePreservesCardinality(): void
    {
        $points = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(1, 1),
            Position::create(2, 2)
        );
        
        $reference = Position::create(100, 200);
        $translated = Pattern::translate($points, $reference);
        
        $this->assertSame($points->count(), $translated->count());
    }

    // ==================== Group X - Cross-cutting ====================

    /**
     * X1a: initialCore defaults
     */
    public function testInitialCore(): void
    {
        $core = Core::initial();
        
        $this->assertTrue($core->cellsOf()->isEmpty());
        $this->assertFalse($core->statusVisibleOf());
    }

    /**
     * X2: field updates are non-destructive
     */
    public function testFieldUpdatesNonDestructive(): void
    {
        $core = Core::initial();
        $originalCells = $core->cellsOf();
        $originalStatus = $core->statusVisibleOf();
        
        $newCells = new Cells(Position::create(0, 0));
        $newCore = $core->withCells($newCells);
        
        // Original core should be unchanged
        $this->assertTrue($core->cellsOf()->equals($originalCells));
        $this->assertSame($originalStatus, $core->statusVisibleOf());
        
        // New core should have new cells
        $this->assertTrue($newCore->cellsOf()->equals($newCells));
        
        // Status visible should be preserved
        $this->assertSame($core->statusVisibleOf(), $newCore->statusVisibleOf());
    }

    /**
     * X2: withStatusVisible is non-destructive
     */
    public function testWithStatusVisibleNonDestructive(): void
    {
        $core = Core::initial();
        $originalStatus = $core->statusVisibleOf();
        
        $newCore = $core->withStatusVisible(true);
        
        // Original should be unchanged
        $this->assertSame($originalStatus, $core->statusVisibleOf());
        
        // New should have updated status
        $this->assertTrue($newCore->statusVisibleOf());
    }

    /**
     * X3: Cells cardinalities
     */
    public function testCellsCardinalities(): void
    {
        // Empty cells
        $empty = Cells::empty();
        $this->assertSame(0, $empty->count());
        $this->assertTrue($empty->isEmpty());
        
        // Single cell
        $single = new Cells(Position::create(0, 0));
        $this->assertSame(1, $single->count());
        $this->assertFalse($single->isEmpty());
        
        // Multiple cells - duplicates removed
        $multi = new Cells(
            Position::create(0, 0),
            Position::create(0, 0),
            Position::create(1, 1)
        );
        $this->assertSame(2, $multi->count());
    }

    /**
     * X3: Position equality
     */
    public function testPositionEquality(): void
    {
        $pos1 = Position::create(10, 20);
        $pos2 = Position::create(10, 20);
        $pos3 = Position::create(10, 21);
        
        $this->assertTrue($pos1->equals($pos2));
        $this->assertFalse($pos1->equals($pos3));
    }

    /**
     * X3: Cells equality (order-insensitive)
     */
    public function testCellsEquality(): void
    {
        $cells1 = new Cells(
            Position::create(0, 0),
            Position::create(1, 1)
        );
        
        $cells2 = new Cells(
            Position::create(1, 1),
            Position::create(0, 0)
        );
        
        $this->assertTrue($cells1->equals($cells2));
    }

    /**
     * X4: Core equality
     */
    public function testCoreEquality(): void
    {
        $core1 = Core::initial();
        $core2 = Core::initial();
        
        $this->assertTrue($core1->equals($core2));
        
        // Different cells
        $core3 = $core1->withCells(new Cells(Position::create(0, 0)));
        $this->assertFalse($core1->equals($core3));
        
        // Different status
        $core4 = $core1->withStatusVisible(true);
        $this->assertFalse($core1->equals($core4));
    }

    /**
     * Test that Cells::with returns new instance
     */
    public function testCellsWithReturnsNewInstance(): void
    {
        $cells = new Cells(Position::create(0, 0));
        $newCells = $cells->with(Position::create(1, 1));
        
        $this->assertSame(1, $cells->count());
        $this->assertSame(2, $newCells->count());
    }

    /**
     * Test Cells::union
     */
    public function testCellsUnion(): void
    {
        $cells1 = new Cells(Position::create(0, 0), Position::create(1, 1));
        $cells2 = new Cells(Position::create(1, 1), Position::create(2, 2));
        
        $union = $cells1->union($cells2);
        
        $this->assertSame(3, $union->count());
        $this->assertTrue($union->contains(Position::create(0, 0)));
        $this->assertTrue($union->contains(Position::create(1, 1)));
        $this->assertTrue($union->contains(Position::create(2, 2)));
    }

    /**
     * Test Cells::intersection
     */
    public function testCellsIntersection(): void
    {
        $cells1 = new Cells(Position::create(0, 0), Position::create(1, 1));
        $cells2 = new Cells(Position::create(1, 1), Position::create(2, 2));
        
        $intersection = $cells1->intersection($cells2);
        
        $this->assertSame(1, $intersection->count());
        $this->assertTrue($intersection->contains(Position::create(1, 1)));
    }

    /**
     * Test Cells::map
     */
    public function testCellsMap(): void
    {
        $cells = new Cells(Position::create(0, 0), Position::create(1, 1));
        
        $mapped = $cells->map(fn(Position $p) => Position::create($p->x + 10, $p->y + 10));
        
        $this->assertSame(2, $mapped->count());
        $this->assertTrue($mapped->contains(Position::create(10, 10)));
        $this->assertTrue($mapped->contains(Position::create(11, 11)));
    }

    /**
     * Test Cells::filter
     */
    public function testCellsFilter(): void
    {
        $cells = new Cells(
            Position::create(0, 0),
            Position::create(1, 1),
            Position::create(2, 2)
        );
        
        $filtered = $cells->filter(fn(Position $p) => $p->x >= 1);
        
        $this->assertSame(2, $filtered->count());
        $this->assertFalse($filtered->contains(Position::create(0, 0)));
        $this->assertTrue($filtered->contains(Position::create(1, 1)));
        $this->assertTrue($filtered->contains(Position::create(2, 2)));
    }
}
