<?php

namespace App\Exceptions;

use App\Enums\Stage;
use DomainException;

class InvalidStageTransition extends DomainException
{
    public static function between(Stage $from, Stage $to): self
    {
        return new self("「{$from->label()}」から「{$to->label()}」へは変更できません。");
    }
}
