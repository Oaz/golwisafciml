<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * GetPatternCells instruction - fetch pattern cells from store.
 * Has a continuation that receives the cells.
 */
final class GetPatternCellsProgram extends Program
{
    /**
     * @var callable(Cells): Program
     */
    private $continuation;

    /**
     * @param callable(Cells): Program $continuation
     */
    public function __construct(
        public string $patternName,
        callable $continuation
    ) {
        $this->continuation = $continuation;
    }

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitGetPatternCells($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getPatternName(): string
    {
        return $this->patternName;
    }

    /**
     * @return callable(Cells): Program
     */
    public function getContinuation(): callable
    {
        return $this->continuation;
    }
}
