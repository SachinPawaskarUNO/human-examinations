<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Tests\TestCase;

// Different provider sets must not rewrite a shared service manifest in parallel.
$_ENV['APP_SERVICES_CACHE'] = 'bootstrap/cache/services-test-'.getmypid().'.php';
$_SERVER['APP_SERVICES_CACHE'] = $_ENV['APP_SERVICES_CACHE'];

uses(TestCase::class)->in(__DIR__);
