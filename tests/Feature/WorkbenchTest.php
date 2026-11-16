<?php

declare(strict_types=1);

use Orchestra\Testbench\Concerns\WithWorkbench;

uses(WithWorkbench::class);

it('renders the workbench as an Inertia page', function () {
    config()->set('app.key', str_repeat('w', 32));
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('data-page=', false)
        ->assertSee('Workspace')
        ->assertDontSee('@inertia', false);
});
