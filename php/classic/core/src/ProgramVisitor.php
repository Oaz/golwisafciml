<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Visitor interface for pattern matching on Program types.
 * This is the interpretation mechanism - the shell implements this.
 */
interface ProgramVisitor
{
    /**
     * Visit a Return program.
     */
    public function visitReturn(ReturnProgram $program): mixed;

    /**
     * Visit a ShowCells program.
     */
    public function visitShowCells(ShowCellsProgram $program): mixed;

    /**
     * Visit a DisplayMessage program.
     */
    public function visitDisplayMessage(DisplayMessageProgram $program): mixed;

    /**
     * Visit a HideMessage program.
     */
    public function visitHideMessage(HideMessageProgram $program): mixed;

    /**
     * Visit a GetPatternCells program.
     */
    public function visitGetPatternCells(GetPatternCellsProgram $program): mixed;
}
