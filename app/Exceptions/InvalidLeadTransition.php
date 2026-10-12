<?php

namespace App\Exceptions;

use App\Enums\LeadStatus;
use DomainException;

class InvalidLeadTransition extends DomainException
{
    public static function between(LeadStatus $from, LeadStatus $to): self
    {
        return new self("A lead cannot move from \"{$from->label()}\" to \"{$to->label()}\".");
    }
}
