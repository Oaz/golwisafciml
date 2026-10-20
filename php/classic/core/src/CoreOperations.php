<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Core operations for building Programs.
 * All functions are pure - they only depend on their inputs and return new data.
 */
final class CoreOperations
{
    /**
     * Create a Return program.
     */
    public static function ret(Core $core): Program
    {
        return new ReturnProgram($core);
    }

    /**
     * Create a ShowCells program.
     */
    public static function showCells(Cells $cells, Program $next): Program
    {
        return new ShowCellsProgram($cells, $next);
    }

    /**
     * Create a DisplayMessage program.
     */
    public static function displayMessage(string $message, Program $next): Program
    {
        return new DisplayMessageProgram($message, $next);
    }

    /**
     * Create a HideMessage program.
     */
    public static function hideMessage(Program $next): Program
    {
        return new HideMessageProgram($next);
    }

    /**
     * Create a GetPatternCells program.
     * The continuation receives the cells and returns the next program.
     *
     * @param callable(Cells): Program $continuation
     */
    public static function getPatternCells(
        string $name,
        callable $continuation
    ): Program {
        return new GetPatternCellsProgram($name, $continuation);
    }

    /**
     * Build the nextGeneration program.
     * Pure function: given a Core, returns a Program.
     *
     * If status is visible, displays the status message before showing cells.
     * Always ends with Return containing the new Core.
     */
    public static function nextGenerationProgram(Core $core): Program
    {
        $newCells = Life::nextGenerationCells($core->cellsOf());
        $newCore = $core->withCells($newCells);
        
        $program = self::showCells($newCells, self::ret($newCore));
        
        if ($core->statusVisibleOf()) {
            $status = Pattern::statusMessage($newCells);
            $program = self::displayMessage($status, $program);
        }
        
        return $program;
    }

    /**
     * Build the addPattern program.
     * Pure function: given Core, pattern name, and reference position, returns Program.
     *
     * Fetches the pattern cells, translates them by the reference position,
     * unions with existing cells, and returns a program that shows the result.
     * If status is visible, displays the status message.
     */
    public static function addPatternProgram(
        Core $core,
        string $patternName,
        Position $reference
    ): Program {
        return self::getPatternCells(
            $patternName,
            function(Cells $patternCells) use ($core, $reference): Program {
                $translated = Pattern::translate($patternCells, $reference);
                $newCells = $core->cellsOf()->union($translated);
                $newCore = $core->withCells($newCells);
                
                $program = self::showCells($newCells, self::ret($newCore));
                
                if ($core->statusVisibleOf()) {
                    $status = Pattern::statusMessage($newCells);
                    $program = self::displayMessage($status, $program);
                }
                
                return $program;
            }
        );
    }

    /**
     * Build the switchStatusVisibility program.
     *
     * Toggles the status visibility flag. If enabling, displays current status.
     * If disabling, hides the message.
     */
    public static function switchStatusVisibilityProgram(Core $core): Program
    {
        if ($core->statusVisibleOf()) {
            $newCore = $core->withStatusVisible(false);
            return self::hideMessage(self::ret($newCore));
        } else {
            $status = Pattern::statusMessage($core->cellsOf());
            $newCore = $core->withStatusVisible(true);
            return self::displayMessage($status, self::ret($newCore));
        }
    }
}
