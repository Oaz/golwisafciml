<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Abstract base class for Program.
 * Programs are pure data structures representing sequences of instructions.
 * 
 * Note: While we would prefer readonly classes, PHP doesn't allow readonly
 * properties with callable type, so we use final classes without readonly
 * and enforce immutability through discipline (no setters, properties set only in constructor).
 */
abstract class Program
{
    /**
     * Accept a visitor for pattern matching.
     * This enables the interpreter to dispatch on program type.
     */
    abstract public function accept(ProgramVisitor $visitor): mixed;

    /**
     * Check if this is a Return program (terminator).
     */
    abstract public function isReturn(): bool;

    /**
     * For ShowCells: get the cells.
     */
    public function getCells(): ?Cells
    {
        return null;
    }

    /**
     * For DisplayMessage: get the message.
     */
    public function getMessage(): ?string
    {
        return null;
    }

    /**
     * For GetPatternCells: get the pattern name.
     */
    public function getPatternName(): ?string
    {
        return null;
    }

    /**
     * For GetPatternCells: get the continuation.
     * @return callable(Cells): Program|null
     */
    public function getContinuation(): ?callable
    {
        return null;
    }

    /**
     * Get the next program (for chained instructions).
     */
    public function getNext(): ?self
    {
        return null;
    }

    /**
     * For Return: get the Core.
     */
    public function getCore(): ?Core
    {
        return null;
    }
}
