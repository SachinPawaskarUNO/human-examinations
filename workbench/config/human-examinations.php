<?php

declare(strict_types=1);

// The demo's copy of the published package config, as a host app would have it.

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Actions\DeleteObservation;
use HumanExaminations\HumanExaminations\Actions\RestoreObservation;
use HumanExaminations\HumanExaminations\Actions\UpdateExamination;
use HumanExaminations\HumanExaminations\Actions\UpdateObservation;
use HumanExaminations\HumanExaminations\Enums\ObservationType;
use Workbench\App\Support\DemoPersonName;

return [

    /*
    |--------------------------------------------------------------------------
    | Observation Type Enum
    |--------------------------------------------------------------------------
    |
    | The string-backed enum used to cast an observation's `type`. Point this at
    | your own enum to use a different list of types. It must include a case
    | with the value 'Unspecified'.
    |
    */

    'observation_type' => ObservationType::class,

    // Where each examination's person name comes from:
    //   null      no names (default)
    //   'typed'   users type them in the workspace, and they are saved
    //   a class   implementing ResolvesPersonName, read from your app; shown read-only
    // The demo reads names through its Person relationship. Try 'typed' to enter them instead.
    'person_name' => DemoPersonName::class,

    /*
    |--------------------------------------------------------------------------
    | Reference Format
    |--------------------------------------------------------------------------
    |
    | Generates every new examination's reference. Leave it null to let users
    | type their own. Placeholders:
    |
    |   {id}         the examination's ID, e.g. 42
    |   {id:4}       the ID padded with zeros to 4 digits, e.g. 0042
    |   {year}       2026     {month}  11     {day}  19
    |                (from the examination date, or the creation date if blank)
    |   {random:6}   6 random uppercase letters and digits, e.g. K7Q2ZP
    |
    | Example: 'EX-{year}-{id:4}' gives EX-2026-0042. When set, references
    | are always generated and cannot be typed or changed.
    |
    */

    'reference_format' => 'EX-{year}-{id:4}',

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    |
    | The classes that do the package's work. To change how something is done,
    | extend the package's action, override `handle()`, and point the entry
    | here at your class.
    |
    */

    'actions' => [
        'create_examination' => CreateExamination::class,
        'update_examination' => UpdateExamination::class,
        'create_observation' => CreateObservation::class,
        'update_observation' => UpdateObservation::class,
        'delete_observation' => DeleteObservation::class,
        'restore_observation' => RestoreObservation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Routes
    |--------------------------------------------------------------------------
    |
    | Enable the optional examination and observation write routes after
    | registering the host app's Examination policy. Middleware replaces the default list, while
    | route model binding is always retained.
    | The name prefix includes its trailing dot, or may be an empty string.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'human-examinations',
        'name_prefix' => 'human-examinations.',
        // The standalone demo has no login, so the routes skip the `auth` middleware.
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Route
    |--------------------------------------------------------------------------
    |
    | The name of your app's route that shows the workspace for one examination,
    | After a new examination is created, the user is sent there. Leave it null to send
    | them back to the page they came from.
    |
    */

    'workspace_route' => 'workbench.examinations.show',

];
