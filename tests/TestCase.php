<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Tests;

use HumanExaminations\HumanExaminations\HumanExaminationsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            HumanExaminationsServiceProvider::class,
        ];
    }
}
