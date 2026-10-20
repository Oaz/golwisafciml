<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core\Tests;

use PHPUnit\Framework\TestCase;
use GameOfLife\Classic\Core\{
    Position,
    Cells,
    Core,
    Program,
    ReturnProgram,
    ShowCellsProgram,
    DisplayMessageProgram,
    HideMessageProgram,
    GetPatternCellsProgram,
    CoreOperations,
    Life,
    Pattern,
};

/**
 * Program tests for Program structure and core operations.
 * Tests cover groups: I (I1-I4, I8-I9)
 */
final class ProgramTest extends TestCase
{
    // ==================== Group I - Programs and protocol ====================

    /**
     * I1: instruction identity - all 5 opcodes are distinguishable
     */
    public function testInstructionIdentity(): void
    {
        $core = Core::initial();
        
        // Return program
        $return = CoreOperations::ret($core);
        $this->assertInstanceOf(ReturnProgram::class, $return);
        $this->assertInstanceOf(Program::class, $return);
        
        // ShowCells program
        $showCells = CoreOperations::showCells(Cells::empty(), $return);
        $this->assertInstanceOf(ShowCellsProgram::class, $showCells);
        $this->assertInstanceOf(Program::class, $showCells);
        
        // DisplayMessage program
        $display = CoreOperations::displayMessage('', $return);
        $this->assertInstanceOf(DisplayMessageProgram::class, $display);
        $this->assertInstanceOf(Program::class, $display);
        
        // HideMessage program
        $hide = CoreOperations::hideMessage($return);
        $this->assertInstanceOf(HideMessageProgram::class, $hide);
        $this->assertInstanceOf(Program::class, $hide);
        
        // GetPatternCells program
        $getPattern = CoreOperations::getPatternCells('test', fn(Cells $c) => $return);
        $this->assertInstanceOf(GetPatternCellsProgram::class, $getPattern);
        $this->assertInstanceOf(Program::class, $getPattern);
        
        // All are distinct types
        $this->assertNotInstanceOf(ShowCellsProgram::class, $return);
        $this->assertNotInstanceOf(DisplayMessageProgram::class, $return);
        $this->assertNotInstanceOf(HideMessageProgram::class, $return);
        $this->assertNotInstanceOf(GetPatternCellsProgram::class, $return);
        
        $this->assertNotInstanceOf(ReturnProgram::class, $showCells);
        $this->assertNotInstanceOf(DisplayMessageProgram::class, $showCells);
        $this->assertNotInstanceOf(HideMessageProgram::class, $showCells);
        $this->assertNotInstanceOf(GetPatternCellsProgram::class, $showCells);
    }

    /**
     * I2: nextGenerationProgram shape
     * Verify emitted instructions are in correct order
     */
    public function testNextGenerationProgramShape(): void
    {
        $cells = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(1, 1) // Block still life
        );
        $core = new Core($cells, true);
        
        $program = CoreOperations::nextGenerationProgram($core);
        
        // Should start with DisplayMessage (since status is visible)
        $this->assertInstanceOf(DisplayMessageProgram::class, $program);
        
        $next = $program->getNext();
        $this->assertInstanceOf(ShowCellsProgram::class, $next);
        
        $next = $next->getNext();
        $this->assertInstanceOf(ReturnProgram::class, $next);
        
        // Verify it ends with Return
        $this->assertTrue($next->isReturn());
    }

    /**
     * I2: nextGenerationProgram shape without status visible
     */
    public function testNextGenerationProgramShapeNoStatus(): void
    {
        $cells = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(1, 1)
        );
        $core = new Core($cells, false);
        
        $program = CoreOperations::nextGenerationProgram($core);
        
        // Should start with ShowCells (no status message)
        $this->assertInstanceOf(ShowCellsProgram::class, $program);
        
        $next = $program->getNext();
        $this->assertInstanceOf(ReturnProgram::class, $next);
        
        $this->assertTrue($next->isReturn());
    }

    /**
     * I3: switchStatusVisibilityProgram shape - enable
     */
    public function testSwitchStatusVisibilityProgramEnable(): void
    {
        $core = Core::initial(); // statusVisible = false
        
        $program = CoreOperations::switchStatusVisibilityProgram($core);
        
        // Should start with DisplayMessage (enabling status)
        $this->assertInstanceOf(DisplayMessageProgram::class, $program);
        
        $next = $program->getNext();
        $this->assertInstanceOf(ReturnProgram::class, $next);
        
        // Verify the returned core has status enabled
        $returnedCore = $next->getCore();
        $this->assertTrue($returnedCore->statusVisibleOf());
    }

    /**
     * I3: switchStatusVisibilityProgram shape - disable
     */
    public function testSwitchStatusVisibilityProgramDisable(): void
    {
        $core = new Core(Cells::empty(), true); // statusVisible = true
        
        $program = CoreOperations::switchStatusVisibilityProgram($core);
        
        // Should start with HideMessage (disabling status)
        $this->assertInstanceOf(HideMessageProgram::class, $program);
        
        $next = $program->getNext();
        $this->assertInstanceOf(ReturnProgram::class, $next);
        
        // Verify the returned core has status disabled
        $returnedCore = $next->getCore();
        $this->assertFalse($returnedCore->statusVisibleOf());
    }

    /**
     * I4: addPatternProgram shape
     */
    public function testAddPatternProgramShape(): void
    {
        $core = Core::initial();
        $reference = Position::create(10, 20);
        
        $program = CoreOperations::addPatternProgram($core, 'block', $reference);
        
        // Should start with GetPatternCells
        $this->assertInstanceOf(GetPatternCellsProgram::class, $program);
        $this->assertSame('block', $program->getPatternName());
        
        // Get the continuation and call it with test data
        $continuation = $program->getContinuation();
        
        // Simulate pattern store returning a block pattern
        $blockPattern = new Cells(
            Position::create(0, 0),
            Position::create(1, 0),
            Position::create(0, 1),
            Position::create(1, 1)
        );
        
        $result = $continuation($blockPattern);
        
        // Should produce ShowCells -> Return structure
        // Since initial core has statusVisible = false, no message
        $this->assertInstanceOf(ShowCellsProgram::class, $result);
        
        // The cells should be the translated block
        $expectedCells = Pattern::translate($blockPattern, $reference);
        $this->assertTrue($result->getCells()->equals($expectedCells));
        
        $next = $result->getNext();
        $this->assertInstanceOf(ReturnProgram::class, $next);
        $this->assertTrue($next->isReturn());
    }

    /**
     * I4: addPatternProgram shape with status visible
     */
    public function testAddPatternProgramShapeWithStatus(): void
    {
        $core = new Core(Cells::empty(), true);
        $reference = Position::create(5, 5);
        
        $program = CoreOperations::addPatternProgram($core, 'test', $reference);
        
        // Get continuation and call with empty cells
        $continuation = $program->getContinuation();
        $result = $continuation(Cells::empty());
        
        // Should start with DisplayMessage (status is visible)
        $this->assertInstanceOf(DisplayMessageProgram::class, $result);
        
        $next = $result->getNext();
        $this->assertInstanceOf(ShowCellsProgram::class, $next);
        
        $next = $next->getNext();
        $this->assertInstanceOf(ReturnProgram::class, $next);
        $this->assertTrue($next->isReturn());
    }

    /**
     * I8: program well-formedness - every operation's program ends with Return
     */
    public function testProgramWellFormedness(): void
    {
        $core = Core::initial();
        
        // nextGenerationProgram
        $program = CoreOperations::nextGenerationProgram($core);
        $this->assertProgramEndsWithReturn($program);
        
        // switchStatusVisibilityProgram
        $program = CoreOperations::switchStatusVisibilityProgram($core);
        $this->assertProgramEndsWithReturn($program);
        
        // addPatternProgram
        $program = CoreOperations::addPatternProgram($core, 'test', Position::create(0, 0));
        // For GetPatternCells, we need to follow the continuation
        $continuation = $program->getContinuation();
        $result = $continuation(Cells::empty());
        $this->assertProgramEndsWithReturn($result);
    }

    /**
     * Helper to verify a program chain ends with Return
     */
    private function assertProgramEndsWithReturn(Program $program): void
    {
        $current = $program;
        while (!$current->isReturn()) {
            $this->assertNotNull($current->getNext(), 'Program chain ended without Return');
            $current = $current->getNext();
        }
        $this->assertInstanceOf(ReturnProgram::class, $current);
    }

    /**
     * I9: unknown pattern - continuation handles empty Cells correctly
     */
    public function testUnknownPatternContinuation(): void
    {
        $core = Core::initial();
        $program = CoreOperations::addPatternProgram(
            $core,
            'nonexistent',
            Position::create(0, 0)
        );
        
        $this->assertInstanceOf(GetPatternCellsProgram::class, $program);
        
        // Get the continuation
        $continuation = $program->getContinuation();
        
        // Call continuation with empty cells (simulating unknown pattern)
        $result = $continuation(Cells::empty());
        
        // Verify result shows unchanged cells
        $this->assertInstanceOf(ShowCellsProgram::class, $result);
        $this->assertTrue($result->getCells()->equals($core->cellsOf()));
        
        $next = $result->getNext();
        $this->assertInstanceOf(ReturnProgram::class, $next);
        
        // The returned core should equal the original
        $returnedCore = $next->getCore();
        $this->assertTrue($returnedCore->equals($core));
    }

    /**
     * Test that all programs have proper isReturn() values
     */
    public function testIsReturnValues(): void
    {
        $core = Core::initial();
        
        $return = CoreOperations::ret($core);
        $this->assertTrue($return->isReturn());
        
        $showCells = CoreOperations::showCells(Cells::empty(), $return);
        $this->assertFalse($showCells->isReturn());
        
        $display = CoreOperations::displayMessage('', $return);
        $this->assertFalse($display->isReturn());
        
        $hide = CoreOperations::hideMessage($return);
        $this->assertFalse($hide->isReturn());
        
        $getPattern = CoreOperations::getPatternCells('test', fn(Cells $c) => $return);
        $this->assertFalse($getPattern->isReturn());
    }

    /**
     * Test Program visitor pattern
     */
    public function testProgramVisitor(): void
    {
        $core = Core::initial();
        
        // Create a mock visitor
        $visitor = new class implements \GameOfLife\Classic\Core\ProgramVisitor {
            public array $visited = [];
            
            public function visitReturn(ReturnProgram $program): mixed
            {
                $this->visited[] = 'Return';
                return null;
            }
            
            public function visitShowCells(ShowCellsProgram $program): mixed
            {
                $this->visited[] = 'ShowCells';
                return null;
            }
            
            public function visitDisplayMessage(DisplayMessageProgram $program): mixed
            {
                $this->visited[] = 'DisplayMessage';
                return null;
            }
            
            public function visitHideMessage(HideMessageProgram $program): mixed
            {
                $this->visited[] = 'HideMessage';
                return null;
            }
            
            public function visitGetPatternCells(GetPatternCellsProgram $program): mixed
            {
                $this->visited[] = 'GetPatternCells';
                return null;
            }
        };
        
        // Test each program type
        $return = CoreOperations::ret($core);
        $return->accept($visitor);
        $this->assertSame(['Return'], $visitor->visited);
        
        $showCells = CoreOperations::showCells(Cells::empty(), $return);
        $showCells->accept($visitor);
        $this->assertSame(['Return', 'ShowCells'], $visitor->visited);
    }

    /**
     * Test nextGenerationProgram with different cell configurations
     */
    public function testNextGenerationProgramVariousStates(): void
    {
        // Empty core
        $core = Core::initial();
        $program = CoreOperations::nextGenerationProgram($core);
        $this->assertInstanceOf(ShowCellsProgram::class, $program);
        
        // Core with cells, no status
        $core = new Core(
            new Cells(Position::create(0, 0)),
            false
        );
        $program = CoreOperations::nextGenerationProgram($core);
        $this->assertInstanceOf(ShowCellsProgram::class, $program);
        
        // Core with cells, with status
        $core = new Core(
            new Cells(Position::create(0, 0)),
            true
        );
        $program = CoreOperations::nextGenerationProgram($core);
        $this->assertInstanceOf(DisplayMessageProgram::class, $program);
    }
}
