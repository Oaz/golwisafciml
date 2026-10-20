<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * ShowCells instruction - render these cells.
 */
final class ShowCellsProgram extends Program
{
    public function __construct(
        public Cells $cells,
        public Program $next
    ) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitShowCells($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getCells(): Cells
    {
        return $this->cells;
    }

    public function getNext(): Program
    {
        return $this->next;
    }
}
