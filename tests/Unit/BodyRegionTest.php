<?php

declare(strict_types=1);

use HumanExaminations\HumanExaminations\Enums\BodyRegion;

it('provides standard region labels matching the frontend suggestion catalogue', function (): void {
    $catalogue = json_decode(file_get_contents(__DIR__.'/../../resources/body-regions.json'), true, flags: JSON_THROW_ON_ERROR);
    $regions = [];

    foreach (BodyRegion::cases() as $region) {
        $regions[$region->name] = $region->value;
    }

    expect($catalogue)->toBe($regions)
        ->and(BodyRegion::RightUpperArm->value)->toBe('Right upper arm');
});

it('offers specific sided regions while retaining the broader labels', function (): void {
    expect(BodyRegion::LeftButtock->value)->toBe('Left buttock')
        ->and(BodyRegion::RightButtock->value)->toBe('Right buttock')
        ->and(BodyRegion::LeftThumb->value)->toBe('Left thumb')
        ->and(BodyRegion::RightRingFinger->value)->toBe('Right ring finger')
        ->and(BodyRegion::LeftBigToe->value)->toBe('Left big toe')
        ->and(BodyRegion::RightLittleToe->value)->toBe('Right little toe')
        ->and(BodyRegion::LeftWrist->value)->toBe('Left wrist')
        ->and(BodyRegion::RightHeel->value)->toBe('Right heel')
        ->and(BodyRegion::Forehead->value)->toBe('Forehead')
        ->and(BodyRegion::Groin->value)->toBe('Groin')
        ->and(BodyRegion::LeftInnerThigh->value)->toBe('Left inner thigh')
        ->and(BodyRegion::RightInnerThigh->value)->toBe('Right inner thigh')
        ->and(BodyRegion::LeftNipple->value)->toBe('Left nipple')
        ->and(BodyRegion::RightNipple->value)->toBe('Right nipple')
        ->and(BodyRegion::UpperChest->value)->toBe('Upper chest')
        ->and(BodyRegion::LeftCollarbone->value)->toBe('Left collarbone')
        ->and(BodyRegion::RightArmpit->value)->toBe('Right armpit')
        ->and(BodyRegion::LeftHandPalmar->value)->toBe('Left hand (palmar)')
        ->and(BodyRegion::Face->value)->toBe('Face');
});
