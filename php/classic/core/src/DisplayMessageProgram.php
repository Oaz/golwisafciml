<?php

declare(strict_types=1);

namespace GameOfLife\Classic\Core;

/**
 * DisplayMessage instruction - show this message.
 */
final class DisplayMessageProgram extends Program
{
    public function __construct(
        public string $message,
        public Program $next
    ) {}

    public function accept(ProgramVisitor $visitor): mixed
    {
        return $visitor->visitDisplayMessage($this);
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getNext(): Program
    {
        return $this->next;
    }
}
