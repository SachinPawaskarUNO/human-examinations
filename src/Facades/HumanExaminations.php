<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \HumanExaminations\HumanExaminations\HumanExaminations
 */
class HumanExaminations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HumanExaminations\HumanExaminations\HumanExaminations::class;
    }
}
