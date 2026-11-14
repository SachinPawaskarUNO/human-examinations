<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Enums;

enum ObservationType: string
{
    case Unspecified = 'Unspecified';
    case Scar = 'Scar';
    case Tattoo = 'Tattoo';
    case Birthmark = 'Birthmark';
    case Injury = 'Injury';
    case GunshotWound = 'Gunshot wound';
    case StabWound = 'Stab wound';
    case Bruise = 'Bruise';
    case Abrasion = 'Abrasion';
    case Other = 'Other';
}
