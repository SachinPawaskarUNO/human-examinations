<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Enums\ExaminationBodyRegion;

it('provides standard region labels matching the frontend suggestion catalog', function (): void {
    $catalog = json_decode(file_get_contents(__DIR__.'/../../resources/body-regions.json'), true, flags: JSON_THROW_ON_ERROR);
    $regions = [];

    foreach (ExaminationBodyRegion::cases() as $region) {
        $regions[$region->name] = $region->value;
    }

    expect($catalog)->toBe($regions)
        ->and(ExaminationBodyRegion::RightUpperArm->value)->toBe('Right upper arm');
});

it('offers specific sided regions while retaining the broader labels', function (): void {
    expect(ExaminationBodyRegion::LeftButtock->value)->toBe('Left buttock')
        ->and(ExaminationBodyRegion::RightButtock->value)->toBe('Right buttock')
        ->and(ExaminationBodyRegion::LeftThumb->value)->toBe('Left thumb')
        ->and(ExaminationBodyRegion::RightRingFinger->value)->toBe('Right ring finger')
        ->and(ExaminationBodyRegion::LeftBigToe->value)->toBe('Left big toe')
        ->and(ExaminationBodyRegion::RightLittleToe->value)->toBe('Right little toe')
        ->and(ExaminationBodyRegion::LeftWrist->value)->toBe('Left wrist')
        ->and(ExaminationBodyRegion::RightHeel->value)->toBe('Right heel')
        ->and(ExaminationBodyRegion::Forehead->value)->toBe('Forehead')
        ->and(ExaminationBodyRegion::Groin->value)->toBe('Groin')
        ->and(ExaminationBodyRegion::LeftInnerThigh->value)->toBe('Left inner thigh')
        ->and(ExaminationBodyRegion::RightInnerThigh->value)->toBe('Right inner thigh')
        ->and(ExaminationBodyRegion::LeftNipple->value)->toBe('Left nipple')
        ->and(ExaminationBodyRegion::RightNipple->value)->toBe('Right nipple')
        ->and(ExaminationBodyRegion::UpperChest->value)->toBe('Upper chest')
        ->and(ExaminationBodyRegion::LeftCollarbone->value)->toBe('Left collarbone')
        ->and(ExaminationBodyRegion::RightArmpit->value)->toBe('Right armpit')
        ->and(ExaminationBodyRegion::LeftHandPalmar->value)->toBe('Left hand (palmar)')
        ->and(ExaminationBodyRegion::Face->value)->toBe('Face');
});
