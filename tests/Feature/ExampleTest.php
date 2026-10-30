<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\HumanExaminations;

it('resolves the singleton', function () {
    expect(app(HumanExaminations::class))->toBeInstanceOf(HumanExaminations::class);
});

it('returns the same instance from the container', function () {
    expect(app(HumanExaminations::class))->toBe(app(HumanExaminations::class));
});

it('merges the package config', function () {
    expect(config('human-examinations.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('human-examinations::messages.placeholder'))->toBe('HumanExaminations placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('human-examinations::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('human-examinations:placeholder')
        ->expectsOutputToContain('HumanExaminations placeholder command executed.')
        ->assertSuccessful();
});
