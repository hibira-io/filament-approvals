<?php

namespace Hibira\FilamentApprovals\Exceptions;

use RuntimeException;

class CannotResume extends RuntimeException
{
    public static function missingAgent(string $agent): self
    {
        return new self("The agent {$agent} no longer exists, so its paused turn can't be resumed.");
    }

    public static function notRemembered(string $agent): self
    {
        return new self("{$agent} doesn't remember conversations with continue(). Resume it yourself with HibiraApprovals::resumeUsing().");
    }
}
