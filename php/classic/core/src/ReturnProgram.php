<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * Return instruction - terminates the program and commits the core.
 */
final class ReturnProgram extends Program
{
    public function __construct(public Core $core) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitReturn($this);
    }

    public function isReturn(): bool
    {
        return true;
    }

    public function getCore(): Core
    {
        return $this->core;
    }
}
