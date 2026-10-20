<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * HideMessage instruction - hide the status message.
 */
final class HideMessageProgram extends Program
{
    public function __construct(public Program $next) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitHideMessage($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getNext(): Program
    {
        return $this->next;
    }
}
