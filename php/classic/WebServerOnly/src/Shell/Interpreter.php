<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell;

use GameOfLife\Classic\Core\Core;
use GameOfLife\Classic\Core\Program;
use GameOfLife\Classic\Core\ReturnProgram;
use GameOfLife\Classic\Core\ShowCellsProgram;
use GameOfLife\Classic\Core\DisplayMessageProgram;
use GameOfLife\Classic\Core\HideMessageProgram;
use GameOfLife\Classic\Core\GetPatternCellsProgram;
use GameOfLife\Classic\Core\ProgramVisitor;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\DisplayPort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\PatternStorePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\StatePort;

/**
 * Program interpreter for the WebServerOnly shell.
 * Implements ProgramVisitor to dispatch on Program types.
 * Executes instructions and manages the mutable Core state.
 */
final class Interpreter implements ProgramVisitor
{
    private Core $currentCore;

    public function __construct(
        private DisplayPort $display,
        private PatternStorePort $patternStore,
        private StatePort $state
    ) {
        $this->currentCore = $this->state->loadCore();
    }

    /**
     * Interpret a complete program.
     * Executes all instructions in order, threading responses through continuations.
     *
     * @param Program $program The program to interpret
     */
    public function interpret(Program $program): void
    {
        $this->interpretProgram($program);
    }

    /**
     * Recursively interpret a program by dispatching on its type.
     *
     * @param Program $program
     */
    private function interpretProgram(Program $program): void
    {
        $program->accept($this);
    }

    public function visitReturn(ReturnProgram $program): mixed
    {
        $this->currentCore = $program->getCore();
        $this->state->saveCore($this->currentCore);
        return null;
    }

    public function visitShowCells(ShowCellsProgram $program): mixed
    {
        $this->display->render($program->getCells());
        $this->interpretProgram($program->getNext());
        return null;
    }

    public function visitDisplayMessage(DisplayMessageProgram $program): mixed
    {
        $this->display->show($program->getMessage());
        $this->interpretProgram($program->getNext());
        return null;
    }

    public function visitHideMessage(HideMessageProgram $program): mixed
    {
        $this->display->hide();
        $this->interpretProgram($program->getNext());
        return null;
    }

    public function visitGetPatternCells(GetPatternCellsProgram $program): mixed
    {
        $patternName = $program->getPatternName();
        $patternCells = $this->patternStore->get($patternName);
        $continuation = $program->getContinuation();
        $nextProgram = $continuation($patternCells);
        $this->interpretProgram($nextProgram);
        return null;
    }
}
