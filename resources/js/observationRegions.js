import catalogue from '../body-regions.json' with {type: 'json'};
import {getObservationLocation} from './observationLocations.js';

export const bodyRegions = Object.freeze(catalogue);

const mirror = (polygon) => polygon.map(([x, y]) => [1 - x, y]);
const box = (left, top, right, bottom) => [[left, top], [right, top], [right, bottom], [left, bottom]];

// Coarse suggestion zones traced against the original SVG view boxes. Gaps are
// intentional: white space and uncertain boundaries should not invent anatomy.
const shoulder = [[0.24, 0.19], [0.3, 0.175], [0.34, 0.195], [0.3, 0.23], [0.2, 0.23]];
const upperArm = [[0.19, 0.235], [0.265, 0.235], [0.29, 0.32], [0.275, 0.36], [0.175, 0.36]];
const forearm = [[0.175, 0.365], [0.265, 0.365], [0.235, 0.43], [0.19, 0.505], [0.12, 0.505], [0.13, 0.43]];
const hand = [[0.12, 0.51], [0.19, 0.51], [0.2, 0.56], [0.18, 0.6], [0.065, 0.6], [0.035, 0.56]];
const thigh = [[0.29, 0.51], [0.455, 0.51], [0.43, 0.6], [0.395, 0.68], [0.285, 0.68]];
const knee = [[0.285, 0.685], [0.395, 0.685], [0.405, 0.735], [0.285, 0.735]];
const lowerLeg = [[0.28, 0.74], [0.4, 0.74], [0.4, 0.8], [0.39, 0.9], [0.33, 0.9], [0.28, 0.81]];
const foot = [[0.325, 0.905], [0.395, 0.905], [0.455, 0.98], [0.43, 0.99], [0.31, 0.99]];

function bodyMap(front) {
    const imageLeft = front ? 'Right' : 'Left';
    const imageRight = front ? 'Left' : 'Right';
    return [
        ['Head', [[0.45, 0.015], [0.56, 0.015], [0.61, 0.06], [0.6, 0.095], [0.55, 0.12], [0.45, 0.12], [0.36, 0.08], [0.39, 0.04]]],
        ['Neck', [[0.435, 0.125], [0.56, 0.125], [0.59, 0.16], [0.58, 0.18], [0.41, 0.18], [0.42, 0.16]]],
        ...[
            ['Elbow', box(0.175, 0.34, 0.28, 0.39)],
            ['Wrist', box(0.12, 0.485, 0.21, 0.53)],
            ['Hip', box(0.30, 0.43, 0.385, 0.495)],
            ['Flank', box(0.325, 0.335, 0.405, 0.42)],
            ['Ankle', [[0.325, 0.885], [0.4, 0.885], [0.41, 0.93], [0.32, 0.93]]],
        ].flatMap(([region, polygon]) => [
            [imageLeft + region, polygon], [imageRight + region, mirror(polygon)],
        ]),
        ...(front ? [
            ['RightChest', [[0.31, 0.235], [0.495, 0.235], [0.495, 0.315], [0.32, 0.315]]],
            ['LeftChest', [[0.505, 0.235], [0.69, 0.235], [0.68, 0.315], [0.505, 0.315]]],
            ['UpperAbdomen', box(0.34, 0.32, 0.66, 0.36)],
            ['LowerAbdomen', box(0.34, 0.36, 0.66, 0.405)],
            ['Abdomen', [[0.32, 0.32], [0.68, 0.32], [0.67, 0.405], [0.33, 0.405]]],
            ['Pelvis', [[0.33, 0.41], [0.67, 0.41], [0.66, 0.495], [0.52, 0.515], [0.48, 0.515], [0.34, 0.495]]],
        ] : [
            ['LeftShoulderBlade', box(0.32, 0.235, 0.465, 0.325)],
            ['RightShoulderBlade', box(0.535, 0.235, 0.68, 0.325)],
            ['UpperBack', [[0.3, 0.235], [0.7, 0.235], [0.675, 0.33], [0.325, 0.33]]],
            ['LowerBack', [[0.325, 0.335], [0.675, 0.335], [0.67, 0.42], [0.33, 0.42]]],
            ['LeftButtock', [[0.34, 0.425], [0.495, 0.425], [0.495, 0.505], [0.34, 0.49]]],
            ['RightButtock', [[0.505, 0.425], [0.66, 0.425], [0.66, 0.49], [0.505, 0.505]]],
            ['Buttocks', [[0.33, 0.425], [0.67, 0.425], [0.66, 0.495], [0.5, 0.51], [0.34, 0.495]]],
        ]),
        ...[
            ['Shoulder', shoulder], ['UpperArm', upperArm], ['Forearm', forearm],
            ['Hand', hand], ['Thigh', thigh], ['Knee', knee], ['LowerLeg', lowerLeg], ['Foot', foot],
        ].flatMap(([region, polygon]) => [
            [imageLeft + region, polygon], [imageRight + region, mirror(polygon)],
        ]),
    ];
}

const headFront = [[0.5, 0.035], [0.73, 0.07], [0.87, 0.2], [0.9, 0.4], [0.96, 0.42], [0.91, 0.57], [0.82, 0.6], [0.77, 0.71], [0.62, 0.79], [0.5, 0.8], [0.38, 0.77], [0.23, 0.69], [0.18, 0.6], [0.1, 0.58], [0.05, 0.42], [0.1, 0.4], [0.12, 0.22], [0.2, 0.12], [0.34, 0.06]];
const headBack = [[0.5, 0.03], [0.75, 0.1], [0.9, 0.25], [0.94, 0.5], [0.88, 0.62], [0.81, 0.66], [0.8, 0.7], [0.2, 0.7], [0.17, 0.65], [0.1, 0.6], [0.04, 0.42], [0.09, 0.4], [0.11, 0.23], [0.23, 0.1]];
const profile = [[0.15, 0.3], [0.17, 0.22], [0.27, 0.09], [0.44, 0.035], [0.64, 0.04], [0.81, 0.1], [0.92, 0.2], [0.96, 0.36], [0.96, 0.48], [0.87, 0.6], [0.76, 0.7], [0.71, 0.79], [0.29, 0.79], [0.17, 0.77], [0.12, 0.72], [0.12, 0.66], [0.08, 0.63], [0.09, 0.6], [0.07, 0.57], [0.11, 0.54], [0.04, 0.52], [0.035, 0.48], [0.13, 0.38]];
const scalp = [[0.5, 0.03], [0.6, 0.08], [0.76, 0.1], [0.88, 0.22], [0.93, 0.35], [0.96, 0.47], [0.88, 0.62], [0.77, 0.73], [0.61, 0.81], [0.4, 0.83], [0.24, 0.78], [0.13, 0.66], [0.06, 0.52], [0.07, 0.36], [0.14, 0.2], [0.25, 0.1], [0.4, 0.08]];
const palm = [[0.29, 0.205], [0.73, 0.205], [0.78, 0.27], [0.93, 0.35], [0.94, 0.47], [0.96, 0.62], [0.94, 0.66], [0.89, 0.66], [0.85, 0.62], [0.8, 0.47], [0.75, 0.46], [0.71, 0.87], [0.7, 0.94], [0.64, 0.96], [0.6, 0.94], [0.59, 0.61], [0.55, 0.9], [0.52, 0.97], [0.47, 0.98], [0.41, 0.96], [0.4, 0.91], [0.43, 0.61], [0.39, 0.6], [0.35, 0.9], [0.31, 0.94], [0.25, 0.92], [0.23, 0.88], [0.26, 0.58], [0.23, 0.58], [0.12, 0.77], [0.07, 0.8], [0.04, 0.78], [0.05, 0.74], [0.15, 0.58], [0.17, 0.5], [0.2, 0.35], [0.24, 0.26]];
const dorsalHand = [[0.39, 0.205], [0.82, 0.205], [0.87, 0.32], [0.9, 0.52], [0.95, 0.71], [0.93, 0.77], [0.88, 0.77], [0.84, 0.57], [0.8, 0.54], [0.79, 0.86], [0.77, 0.93], [0.73, 0.94], [0.69, 0.9], [0.68, 0.63], [0.65, 0.59], [0.63, 0.95], [0.61, 0.98], [0.56, 0.98], [0.54, 0.94], [0.52, 0.61], [0.49, 0.65], [0.45, 0.9], [0.41, 0.94], [0.37, 0.91], [0.36, 0.86], [0.39, 0.52], [0.36, 0.48], [0.32, 0.53], [0.22, 0.61], [0.14, 0.64], [0.07, 0.63], [0.04, 0.6], [0.1, 0.53], [0.2, 0.4], [0.25, 0.29], [0.33, 0.24]];
const dorsalFoot = [[0.3, 0.055], [0.5, 0.03], [0.66, 0.04], [0.8, 0.08], [0.84, 0.13], [0.84, 0.2], [0.87, 0.23], [0.84, 0.3], [0.88, 0.42], [0.92, 0.61], [0.9, 0.73], [0.92, 0.8], [0.88, 0.83], [0.81, 0.83], [0.81, 0.88], [0.74, 0.89], [0.72, 0.93], [0.65, 0.94], [0.59, 0.95], [0.51, 0.96], [0.43, 0.98], [0.32, 0.97], [0.22, 0.91], [0.13, 0.79], [0.1, 0.7], [0.13, 0.63], [0.12, 0.55], [0.16, 0.43], [0.2, 0.29], [0.23, 0.22], [0.23, 0.13], [0.25, 0.08]];
const plantarFoot = [[0.3, 0.045], [0.53, 0.03], [0.71, 0.05], [0.78, 0.08], [0.8, 0.18], [0.8, 0.31], [0.83, 0.43], [0.94, 0.63], [0.93, 0.72], [0.93, 0.82], [0.9, 0.89], [0.86, 0.94], [0.76, 0.98], [0.64, 0.98], [0.59, 0.94], [0.52, 0.96], [0.45, 0.95], [0.38, 0.93], [0.32, 0.92], [0.27, 0.88], [0.2, 0.86], [0.13, 0.82], [0.1, 0.76], [0.1, 0.65], [0.07, 0.62], [0.07, 0.56], [0.12, 0.44], [0.14, 0.35], [0.14, 0.16], [0.18, 0.08]];

// Clip detailed suggestions to their broader surface so finger/toe gaps do not
// acquire labels. The placement component separately checks the exact SVG fill.
function surfaceDetails(surface, regions) {
    return regions.map(([region, polygon]) => [region, polygon, surface]);
}

function handMap(side, palmar) {
    const orient = side === 'Right' ? mirror : (polygon) => polygon;
    const surface = orient(palmar ? palm : dorsalHand);
    const fingers = palmar ? [
        ['Thumb', box(0.76, 0.42, 0.98, 0.68)],
        ['IndexFinger', box(0.59, 0.57, 0.75, 0.97)],
        ['MiddleFinger', box(0.4, 0.6, 0.58, 0.99)],
        ['RingFinger', box(0.22, 0.58, 0.395, 0.95)],
        ['LittleFinger', box(0.03, 0.58, 0.22, 0.82)],
        ['Palm', box(0.24, 0.25, 0.76, 0.57)],
    ] : [
        ['Thumb', box(0.03, 0.39, 0.36, 0.66)],
        ['IndexFinger', box(0.35, 0.58, 0.5, 0.95)],
        ['MiddleFinger', box(0.51, 0.61, 0.66, 0.99)],
        ['RingFinger', box(0.67, 0.58, 0.81, 0.95)],
        ['LittleFinger', box(0.82, 0.53, 0.98, 0.8)],
    ];
    return [
        [side + 'Wrist', orient(palmar ? box(0.29, 0.165, 0.76, 0.245) : box(0.39, 0.15, 0.83, 0.255))],
        [side + 'Forearm', orient(palmar ? box(0.32, 0.03, 0.72, 0.165) : box(0.43, 0.03, 0.81, 0.15))],
        ...surfaceDetails(surface, fingers.map(([region, polygon]) => [side + region, orient(polygon)])),
        [side + (palmar ? 'HandPalmar' : 'HandDorsal'), surface],
    ];
}

function footMap(side, plantar) {
    const orient = side === 'Right' ? mirror : (polygon) => polygon;
    const surface = orient(plantar ? plantarFoot : dorsalFoot);
    const toes = plantar ? [
        ['BigToe', box(0.6, 0.78, 0.96, 0.99)],
        ['SecondToe', box(0.43, 0.8, 0.6, 0.97)],
        ['ThirdToe', box(0.33, 0.78, 0.43, 0.95)],
        ['FourthToe', box(0.24, 0.75, 0.33, 0.92)],
        ['LittleToe', box(0.1, 0.7, 0.24, 0.87)],
        ['Heel', box(0.2, 0.04, 0.78, 0.29)],
        ['Sole', box(0.17, 0.29, 0.82, 0.76)],
    ] : [
        ['BigToe', box(0.2, 0.82, 0.43, 0.99)],
        ['SecondToe', box(0.43, 0.83, 0.59, 0.98)],
        ['ThirdToe', box(0.59, 0.81, 0.72, 0.96)],
        ['FourthToe', box(0.72, 0.77, 0.83, 0.91)],
        ['LittleToe', box(0.83, 0.73, 0.95, 0.86)],
    ];
    return [
        ...surfaceDetails(surface, toes.map(([region, polygon]) => [side + region, orient(polygon)])),
        [side + (plantar ? 'FootPlantar' : 'FootDorsal'), surface],
    ];
}

const frontFeatures = surfaceDetails(headFront, [
    ['Forehead', box(0.25, 0.12, 0.75, 0.3)],
    ['RightEye', box(0.19, 0.36, 0.42, 0.45)],
    ['LeftEye', box(0.58, 0.36, 0.81, 0.45)],
    ['RightEar', box(0.04, 0.42, 0.15, 0.6)],
    ['LeftEar', box(0.85, 0.42, 0.96, 0.6)],
    ['RightCheek', box(0.18, 0.5, 0.38, 0.66)],
    ['LeftCheek', box(0.62, 0.5, 0.82, 0.66)],
    ['Nose', box(0.43, 0.46, 0.57, 0.62)],
    ['Mouth', box(0.35, 0.64, 0.65, 0.71)],
    ['Chin', box(0.39, 0.73, 0.61, 0.8)],
]);

function profileMap(side) {
    const orient = side === 'Right' ? mirror : (polygon) => polygon;
    const surface = orient(profile);
    return [
        ...surfaceDetails(surface, [
            [side + 'Ear', orient(box(0.52, 0.37, 0.68, 0.61))],
            ['Forehead', orient(box(0.18, 0.14, 0.4, 0.31))],
            ['Nose', orient(box(0.035, 0.43, 0.16, 0.55))],
            ['Mouth', orient(box(0.09, 0.56, 0.21, 0.66))],
            ['Chin', orient(box(0.15, 0.69, 0.32, 0.79))],
        ]),
        [side + 'SideOfHead', surface],
        ['Neck', orient(box(0.31, 0.8, 0.7, 0.96))],
    ];
}

const maps = {
    'body-front': bodyMap(true),
    'body-back': bodyMap(false),
    'head-front': [...frontFeatures, ['Face', headFront], ['Neck', [[0.24, 0.8], [0.75, 0.8], [0.8, 0.95], [0.14, 0.95]]]],
    'head-back': [...surfaceDetails(headBack, [['LeftEar', box(0.05, 0.4, 0.2, 0.65)], ['RightEar', box(0.8, 0.4, 0.95, 0.65)]]), ['BackOfHead', headBack], ['Neck', [[0.25, 0.71], [0.79, 0.71], [0.87, 0.96], [0.13, 0.96]]]],
    'head-left-profile': profileMap('Left'),
    'head-right-profile': profileMap('Right'),
    'head-top': [['Scalp', scalp]],
    'hand-left-palmar': handMap('Left', true),
    'hand-right-palmar': handMap('Right', true),
    'hand-left-dorsal': handMap('Left', false),
    'hand-right-dorsal': handMap('Right', false),
    'foot-left-dorsal': footMap('Left', false),
    'foot-right-dorsal': footMap('Right', false),
    'foot-left-plantar': footMap('Left', true),
    'foot-right-plantar': footMap('Right', true),
};

function contains(polygon, x, y) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const [xi, yi] = polygon[i];
        const [xj, yj] = polygon[j];
        if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) inside = !inside;
    }
    return inside;
}

export function suggestRegion(location) {
    const point = getObservationLocation(location);
    if (!point || !Object.hasOwn(maps, point.sketch_id)) return null;
    const match = maps[point.sketch_id].find(([, polygon, surface]) => contains(polygon, point.x, point.y)
        && (!surface || contains(surface, point.x, point.y)));
    return match ? bodyRegions[match[0]] : null;
}
