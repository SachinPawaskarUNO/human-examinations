<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Actions\CreateExamination;
use HumanExaminations\HumanExaminations\Actions\UpdateExamination;
use HumanExaminations\HumanExaminations\Enums\ObservationType;

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

    'workspace_route' => null,

];
