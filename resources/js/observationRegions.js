import catalogue from '../body-regions.json' with {type: 'json'};
import tracedSurfaces from '../region-surfaces.json' with {type: 'json'};
import {getObservationLocation} from './observationLocations.js';

export const bodyRegions = Object.freeze(catalogue);

const mirror = (polygon) => polygon.map(([x, y]) => [1 - x, y]);
const box = (left, top, right, bottom) => [[left, top], [right, top], [right, bottom], [left, bottom]];
const ellipse = ({x, y, rx, ry}) => Array.from({length: 64}, (_, i) => {
    const angle = i / 64 * Math.PI * 2;
    return [x + Math.cos(angle) * rx, y + Math.sin(angle) * ry];
});

// Feature zones are normalized to the original SVG view boxes. Every view is
// bounded by its SVG fill; broader fallbacks cover the spaces between features.
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
    const headSurface = tracedSurfaces.outlines[front ? 'body-front' : 'body-back'];
    return [
        ...surfaceDetails(headSurface, [
            [imageLeft + 'Ear', front ? box(0.35, 0.06, 0.389, 0.092) : box(0.365, 0.07, 0.402, 0.098)],
            [imageRight + 'Ear', front ? box(0.583, 0.056, 0.62, 0.09) : box(0.578, 0.069, 0.61, 0.095)],
        ]),
        ['Head', box(0, 0, 1, 0.12)],
        ...(front ? [
            ...Object.entries(tracedSurfaces.landmarks['body-front']).map(([name, bounds]) => [name, ellipse(bounds)]),
            ['RightCollarbone', box(0.365, 0.16, 0.485, 0.195)],
            ['LeftCollarbone', box(0.515, 0.16, 0.635, 0.195)],
            ['Groin', [[0.415, 0.45], [0.585, 0.45], [0.60, 0.485], [0.54, 0.53], [0.50, 0.555], [0.46, 0.53], [0.40, 0.485]]],
        ] : [['Groin', box(0.485, 0.425, 0.515, 0.53)]]),
        ['Neck', [[0.435, 0.125], [0.56, 0.125], [0.59, 0.16], [0.58, 0.18], [0.41, 0.18], [0.42, 0.16]]],
        ...[
            ['Armpit', ellipse({x: 0.30, y: 0.26, rx: 0.025, ry: 0.038})],
            ['InnerThigh', [[0.425, 0.52], [0.5, 0.52], [0.5, 0.675], [0.39, 0.675], [0.41, 0.60]]],
        ].flatMap(([region, polygon]) => [
            [imageLeft + region, polygon], [imageRight + region, mirror(polygon)],
        ]),
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
            ['UpperChest', box(0.34, 0.18, 0.66, 0.235)],
            ['RightChest', [[0.31, 0.235], [0.495, 0.235], [0.495, 0.315], [0.32, 0.315]]],
            ['LeftChest', [[0.505, 0.235], [0.69, 0.235], [0.68, 0.315], [0.505, 0.315]]],
            ['UpperAbdomen', box(0.34, 0.32, 0.66, 0.36)],
            ['LowerAbdomen', box(0.34, 0.36, 0.66, 0.405)],
            ['Abdomen', [[0.32, 0.32], [0.68, 0.32], [0.67, 0.405], [0.33, 0.405]]],
            ['Pelvis', [[0.33, 0.41], [0.67, 0.41], [0.66, 0.495], [0.52, 0.515], [0.48, 0.515], [0.34, 0.495]]],
        ] : [
            ['LeftShoulderBlade', box(0.32, 0.205, 0.465, 0.325)],
            ['RightShoulderBlade', box(0.535, 0.205, 0.68, 0.325)],
            ['UpperBack', [[0.3, 0.235], [0.7, 0.235], [0.675, 0.33], [0.325, 0.33]]],
            ['LowerBack', [[0.325, 0.335], [0.675, 0.335], [0.67, 0.42], [0.33, 0.42]]],
            ['LeftButtock', [[0.34, 0.425], [0.495, 0.425], [0.495, 0.505], [0.34, 0.49]]],
            ['RightButtock', [[0.505, 0.425], [0.66, 0.425], [0.66, 0.49], [0.505, 0.505]]],
            ['Groin', [[0.33, 0.425], [0.67, 0.425], [0.66, 0.495], [0.5, 0.51], [0.34, 0.495]]],
        ]),
        ...[
            ['Shoulder', shoulder], ['UpperArm', upperArm], ['Forearm', forearm],
            ['Hand', hand], ['Thigh', thigh], ['Knee', knee], ['LowerLeg', lowerLeg], ['Foot', foot],
        ].flatMap(([region, polygon]) => [
            [imageLeft + region, polygon], [imageRight + region, mirror(polygon)],
        ]),
    ];
}

function bodyFallback({sketch_id, x, y}) {
    const front = sketch_id === 'body-front';
    const side = x < 0.5 ? (front ? 'Right' : 'Left') : (front ? 'Left' : 'Right');
    if (y < 0.12) return 'Head';
    if (y < 0.18 && x > 0.4 && x < 0.6) return 'Neck';
    if (y < 0.235) return x > 0.36 && x < 0.64 ? (front ? 'UpperChest' : 'UpperBack') : side + 'Shoulder';
    const outsideTorso = y < 0.43 ? x < 0.315 || x > 0.685 : x < 0.29 || x > 0.71;
    if (y < 0.61 && outsideTorso) {
        if (y < 0.34) return side + 'UpperArm';
        if (y < 0.39) return side + 'Elbow';
        if (y < 0.485) return side + 'Forearm';
        if (y < 0.53) return side + 'Wrist';
        return side + 'Hand';
    }
    if (y < 0.32) return front ? side + 'Chest' : 'UpperBack';
    if (y < 0.405) return front ? (y < 0.36 ? 'UpperAbdomen' : 'LowerAbdomen') : 'LowerBack';
    if (y < 0.52) return front ? 'Pelvis' : side + 'Buttock';
    if (y < 0.685) return side + 'Thigh';
    if (y < 0.74) return side + 'Knee';
    if (y < 0.895) return side + 'LowerLeg';
    if (y < 0.935) return side + 'Ankle';
    return side + 'Foot';
}


// Clip detailed suggestions to their broader surface so finger/toe gaps do not
// acquire labels. The placement component separately checks the exact SVG fill.
function surfaceDetails(surface, regions) {
    return regions.map(([region, polygon]) => [region, polygon, surface]);
}

function handMap(side, palmar) {
    const orient = side === 'Right' ? mirror : (polygon) => polygon;
    const surface = tracedSurfaces.outlines[`hand-${side.toLowerCase()}-${palmar ? 'palmar' : 'dorsal'}`];
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
    const surface = tracedSurfaces.outlines[`foot-${side.toLowerCase()}-${plantar ? 'plantar' : 'dorsal'}`];
    const toes = plantar ? [
        ['BigToe', box(0.60, 0.78, 1, 1)],
        ['SecondToe', box(0.43, 0.8, 0.60, 1)],
        ['ThirdToe', box(0.32, 0.78, 0.43, 0.98)],
        ['FourthToe', box(0.24, 0.75, 0.33, 0.96)],
        ['LittleToe', box(0.07, 0.7, 0.24, 0.94)],
        ['Heel', box(0.2, 0.04, 0.78, 0.29)],
        ['Sole', box(0.17, 0.29, 0.82, 0.76)],
    ] : [
        ['BigToe', box(0.05, 0.80, 0.43, 1)],
        ['SecondToe', box(0.40, 0.82, 0.59, 0.99)],
        ['ThirdToe', box(0.575, 0.80, 0.73, 0.97)],
        ['FourthToe', box(0.715, 0.765, 0.84, 0.93)],
        ['LittleToe', box(0.815, 0.72, 0.97, 0.86)],
    ];
    return [
        ...surfaceDetails(surface, toes.map(([region, polygon]) => [side + region, orient(polygon)])),
        [side + (plantar ? 'FootPlantar' : 'FootDorsal'), surface],
    ];
}

const frontFeatures = surfaceDetails(tracedSurfaces.outlines['head-front'], [
    ['Forehead', box(0.25, 0.12, 0.75, 0.3)],
    ['RightEye', box(0.19, 0.36, 0.42, 0.45)],
    ['LeftEye', box(0.58, 0.36, 0.81, 0.45)],
    ['RightEar', box(0.02, 0.395, 0.17, 0.63)],
    ['LeftEar', box(0.82, 0.395, 0.98, 0.63)],
    ['RightCheek', box(0.18, 0.5, 0.38, 0.66)],
    ['LeftCheek', box(0.62, 0.5, 0.82, 0.66)],
    ['Nose', box(0.37, 0.46, 0.62, 0.625)],
    ['Mouth', box(0.35, 0.64, 0.65, 0.71)],
    ['Chin', box(0.39, 0.73, 0.61, 0.8)],
]);

function profileMap(side) {
    const orient = side === 'Right' ? mirror : (polygon) => polygon;
    const surface = tracedSurfaces.outlines[`head-${side.toLowerCase()}-profile`];
    return [
        ...surfaceDetails(surface, [
            [side + 'Ear', orient(box(0.505, 0.36, 0.685, 0.66))],
            ['Forehead', orient(box(0.18, 0.14, 0.4, 0.31))],
            [side + 'Eye', orient(box(0.14, 0.345, 0.39, 0.405))],
            ['Nose', orient(box(0.025, 0.415, 0.18, 0.555))],
            ['Mouth', orient(box(0.06, 0.56, 0.22, 0.67))],
            ['Chin', orient(box(0.15, 0.69, 0.32, 0.79))],
            [side + 'SideOfHead', box(0, 0, 1, 0.8)],
            ['Neck', box(0, 0.8, 1, 1)],
        ]),
    ];
}

const maps = {
    'body-front': bodyMap(true),
    'body-back': bodyMap(false),
    'head-front': [...frontFeatures, ...surfaceDetails(tracedSurfaces.outlines['head-front'], [
        ['Face', box(0, 0, 1, 0.805)], ['Neck', box(0, 0.805, 1, 1)],
    ])],
    'head-back': surfaceDetails(tracedSurfaces.outlines['head-back'], [
        ['LeftEar', box(0.02, 0.385, 0.21, 0.67)], ['RightEar', box(0.82, 0.38, 0.98, 0.67)],
        ['BackOfHead', box(0, 0, 1, 0.71)], ['Neck', box(0, 0.71, 1, 1)],
    ]),
    'head-left-profile': profileMap('Left'),
    'head-right-profile': profileMap('Right'),
    // The nose points upward in this plate: image-left is anatomical left.
    'head-top': [...surfaceDetails(tracedSurfaces.outlines['head-top'], [
        ['LeftEar', box(0.015, 0.355, 0.135, 0.57)], ['RightEar', box(0.86, 0.355, 0.99, 0.57)],
        ['Nose', box(0.40, 0.015, 0.60, 0.09)],
    ]), ['Scalp', tracedSurfaces.outlines['head-top']]],
    'hand-left-palmar': handMap('Left', true),
    'hand-right-palmar': handMap('Right', true),
    'hand-left-dorsal': handMap('Left', false),
    'hand-right-dorsal': handMap('Right', false),
    'foot-left-dorsal': footMap('Left', false),
    'foot-right-dorsal': footMap('Right', false),
    'foot-left-plantar': footMap('Left', true),
    'foot-right-plantar': footMap('Right', true),
};

function contains(polygon, x, y, tolerance = 0) {
    // Sampled surfaces need their simplification tolerance at the rim. Placement
    // still checks the original SVG fill before accepting any marker.
    if (Array.isArray(polygon[0]?.[0])) return polygon.some((outline) => contains(outline, x, y, 0.0003));
    if (polygon.length < 3) return false;
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const [xi, yi] = polygon[i];
        const [xj, yj] = polygon[j];
        const dx = xj - xi, dy = yj - yi;
        const lengthSquared = dx * dx + dy * dy;
        if (tolerance && lengthSquared) {
            const along = Math.max(0, Math.min(1, ((x - xi) * dx + (y - yi) * dy) / lengthSquared));
            if ((x - xi - along * dx) ** 2 + (y - yi - along * dy) ** 2 <= tolerance ** 2) return true;
        }
        const cross = (x - xi) * (yj - yi) - (y - yi) * (xj - xi);
        if (Math.abs(cross) < 1e-12 && x >= Math.min(xi, xj) && x <= Math.max(xi, xj) && y >= Math.min(yi, yj) && y <= Math.max(yi, yj)) return true;
        if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) inside = !inside;
    }
    return inside;
}

export function suggestRegion(location) {
    const point = getObservationLocation(location);
    if (!point || !Object.hasOwn(maps, point.sketch_id)) return null;
    if (!contains(tracedSurfaces.outlines[point.sketch_id], point.x, point.y)) return null;
    const match = maps[point.sketch_id].find(([, polygon, surface]) => contains(polygon, point.x, point.y)
        && (!surface || contains(surface, point.x, point.y)));
    if (match) return bodyRegions[match[0]];
    if (point.sketch_id.startsWith('body-')) return bodyRegions[bodyFallback(point)];
    if (point.sketch_id.startsWith('hand-')) {
        const side = point.sketch_id.includes('-left-') ? 'Left' : 'Right';
        const region = point.y < 0.18 ? 'Forearm' : point.y < 0.25 ? 'Wrist'
            : point.sketch_id.endsWith('-palmar') ? 'HandPalmar' : 'HandDorsal';
        return bodyRegions[side + region];
    }
    return null;
}
