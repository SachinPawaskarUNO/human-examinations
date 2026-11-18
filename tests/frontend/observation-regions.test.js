import assert from 'node:assert/strict';
import {test} from 'node:test';
import {bodyRegions, suggestRegion} from '../../resources/js/observationRegions.js';
import surfaces from '../../resources/region-surfaces.json' with {type: 'json'};
import {readFile} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import {sketches} from '../../resources/js/sketches.js';

test('body regions respect anatomical sides in front and back views', () => {
    for (const [sketch_id, x, y, expected] of [
        ['body-front', 0.23, 0.28, 'Right upper arm'],
        ['body-front', 0.77, 0.28, 'Left upper arm'],
        ['body-back', 0.23, 0.28, 'Left upper arm'],
        ['body-back', 0.77, 0.28, 'Right upper arm'],
        ['body-front', 0.4, 0.27, 'Right chest'],
        ['body-front', 0.6, 0.27, 'Left chest'],
        ['body-front', 0.5, 0.37, 'Lower abdomen'],
        ['body-back', 0.5, 0.27, 'Upper back'],
        ['body-back', 0.5, 0.38, 'Lower back'],
        ['body-front', 0.36, 0.63, 'Right thigh'],
        ['body-back', 0.36, 0.63, 'Left thigh'],
        ['body-front', 0.65, 0.81, 'Left lower leg'],
    ]) assert.equal(suggestRegion({sketch_id, x, y}), expected, `${sketch_id} ${x},${y}`);
});

test('each detailed sketch suggests its own anatomical region', () => {
    const examples = {
        'head-front': 'Nose', 'head-back': 'Back of head', 'head-top': 'Scalp',
        'head-left-profile': 'Left side of head', 'head-right-profile': 'Right side of head',
        'hand-left-palmar': 'Left palm', 'hand-right-palmar': 'Right palm',
        'hand-left-dorsal': 'Left hand (dorsal)', 'hand-right-dorsal': 'Right hand (dorsal)',
        'foot-left-dorsal': 'Left foot (dorsal)', 'foot-right-dorsal': 'Right foot (dorsal)',
        'foot-left-plantar': 'Left sole', 'foot-right-plantar': 'Right sole',
    };
    for (const [sketch_id, expected] of Object.entries(examples)) {
        assert.equal(suggestRegion({sketch_id, x: '0.500000', y: '0.500000'}), expected, sketch_id);
        assert.ok(Object.values(bodyRegions).includes(expected));
    }
    assert.equal(suggestRegion({sketch_id: 'head-front', x: 0.5, y: 0.88}), 'Neck');
    assert.equal(suggestRegion({sketch_id: 'hand-left-palmar', x: 0.5, y: 0.1}), 'Left forearm');
});

test('full-body sketches identify ears before the general head region', () => {
    for (const [sketch_id, x, y, expected] of [
        ['body-front', 0.605, 0.074, 'Left ear'],
        ['body-front', 0.604, 0.062, 'Left ear'],
        ['body-front', 0.372, 0.075, 'Right ear'],
        ['body-front', 0.365, 0.064, 'Right ear'],
        ['body-back', 0.388, 0.082, 'Left ear'],
        ['body-back', 0.38, 0.074, 'Left ear'],
        ['body-back', 0.597, 0.079, 'Right ear'],
        ['body-back', 0.595, 0.072, 'Right ear'],
        ['body-front', 0.5, 0.05, 'Head'], ['body-back', 0.5, 0.05, 'Head'],
        ['body-front', 0.5, 0.011, 'Head'], ['body-back', 0.5, 0.01, 'Head'],
        ['body-front', 0.65, 0.07, null], ['body-back', 0.34, 0.08, null],
    ]) assert.equal(suggestRegion({sketch_id, x, y}), expected, `${sketch_id} ${x},${y}`);
});

test('sided buttocks and joints respect the front/back orientation', () => {
    for (const [sketch_id, x, y, expected] of [
        ['body-back', 0.43, 0.46, 'Left buttock'], ['body-back', 0.57, 0.46, 'Right buttock'],
        ['body-back', 0.5, 0.46, 'Groin'],
        ['body-front', 0.23, 0.355, 'Right elbow'], ['body-back', 0.23, 0.355, 'Left elbow'],
        ['body-front', 0.15, 0.5, 'Right wrist'], ['body-back', 0.15, 0.5, 'Left wrist'],
        ['body-front', 0.35, 0.445, 'Right hip'], ['body-back', 0.35, 0.445, 'Left hip'],
        ['body-front', 0.36, 0.91, 'Right ankle'], ['body-back', 0.36, 0.91, 'Left ankle'],
        ['body-back', 0.4, 0.27, 'Left shoulder blade'], ['body-back', 0.6, 0.27, 'Right shoulder blade'],
    ]) assert.equal(suggestRegion({sketch_id, x, y}), expected, `${sketch_id} ${x},${y}`);
});

test('each finger is identified in both hands and on both surfaces', () => {
    const examples = {
        palmar: [['thumb', 0.9, 0.58], ['index finger', 0.65, 0.82], ['middle finger', 0.48, 0.87], ['ring finger', 0.29, 0.83], ['little finger', 0.1, 0.74]],
        dorsal: [['thumb', 0.18, 0.58], ['index finger', 0.42, 0.82], ['middle finger', 0.58, 0.87], ['ring finger', 0.74, 0.82], ['little finger', 0.9, 0.68]],
    };
    for (const [surface, fingers] of Object.entries(examples)) {
        for (const [finger, x, y] of fingers) {
            assert.equal(suggestRegion({sketch_id: `hand-left-${surface}`, x, y}), `Left ${finger}`);
            assert.equal(suggestRegion({sketch_id: `hand-right-${surface}`, x: 1 - x, y}), `Right ${finger}`);
        }
    }
});

test('each toe and heel is identified on the matching foot surface', () => {
    const examples = {
        dorsal: [['big toe', 0.32, 0.92], ['second toe', 0.51, 0.92], ['third toe', 0.65, 0.9], ['fourth toe', 0.76, 0.86], ['little toe', 0.85, 0.81]],
        plantar: [['big toe', 0.73, 0.9], ['second toe', 0.49, 0.91], ['third toe', 0.38, 0.89], ['fourth toe', 0.28, 0.86], ['little toe', 0.2, 0.78]],
    };
    for (const [surface, toes] of Object.entries(examples)) {
        for (const [toe, x, y] of toes) {
            assert.equal(suggestRegion({sketch_id: `foot-left-${surface}`, x, y}), `Left ${toe}`);
            assert.equal(suggestRegion({sketch_id: `foot-right-${surface}`, x: 1 - x, y}), `Right ${toe}`);
        }
    }
    assert.equal(suggestRegion({sketch_id: 'foot-left-plantar', x: 0.5, y: 0.15}), 'Left heel');
    assert.equal(suggestRegion({sketch_id: 'foot-right-plantar', x: 0.5, y: 0.15}), 'Right heel');
});

test('facial suggestions distinguish features and anatomical sides', () => {
    for (const [sketch_id, x, y, expected] of [
        ['head-front', 0.5, 0.2, 'Forehead'], ['head-front', 0.3, 0.4, 'Right eye'],
        ['head-front', 0.7, 0.4, 'Left eye'], ['head-front', 0.5, 0.55, 'Nose'],
        ['head-front', 0.5, 0.675, 'Mouth'], ['head-front', 0.5, 0.76, 'Chin'],
        ['head-front', 0.25, 0.58, 'Right cheek'], ['head-front', 0.75, 0.58, 'Left cheek'],
        ['head-back', 0.12, 0.5, 'Left ear'], ['head-back', 0.88, 0.5, 'Right ear'],
        ['head-left-profile', 0.6, 0.47, 'Left ear'], ['head-right-profile', 0.4, 0.47, 'Right ear'],
    ]) assert.equal(suggestRegion({sketch_id, x, y}), expected, `${sketch_id} ${x},${y}`);
});

test('unrecognized areas and invalid locations do not invent a region', () => {
    for (const location of [
        null, {}, {sketch_id: 'unknown', x: 0.5, y: 0.5},
        {sketch_id: '__proto__', x: 0.5, y: 0.5},
        {sketch_id: 'body-front', x: 0.5, y: null},
        {sketch_id: 'body-front', x: -0.1, y: 0.5},
        {sketch_id: 'body-front', x: 0.05, y: 0.2},
        {sketch_id: 'body-back', x: 0.05, y: 0.7},
        {sketch_id: 'body-front', x: 0.5, y: 0.7},
        {sketch_id: 'head-top', x: 0.02, y: 0.02},
        {sketch_id: 'hand-right-dorsal', x: 0.95, y: 0.9},
        {sketch_id: 'foot-left-plantar', x: 0.02, y: 0.5},
    ]) assert.equal(suggestRegion(location), null, JSON.stringify(location));
});

test('head features include ear rims, lobes, nostril wings, and top-view ears', () => {
    for (const [sketch_id, x, y, expected] of [
        ['head-front', .935, .412, 'Left ear'], ['head-front', .06, .415, 'Right ear'],
        ['head-front', .875, .598, 'Left ear'], ['head-front', .135, .60, 'Right ear'],
        ['head-front', .415, .578, 'Nose'], ['head-front', .565, .58, 'Nose'],
        ['head-back', .075, .41, 'Left ear'], ['head-back', .936, .403, 'Right ear'],
        ['head-top', .094, .39, 'Left ear'], ['head-top', .916, .399, 'Right ear'],
        ['head-top', .915842, .398225, 'Right ear'],
        ['head-top', .07, .54, 'Left ear'], ['head-top', .956, .54, 'Right ear'],
        ['head-top', .51, .05, 'Nose'],
        ['head-left-profile', .56, .62, 'Left ear'], ['head-right-profile', .44, .62, 'Right ear'],
        ['head-left-profile', .11, .61, 'Mouth'], ['head-right-profile', .89, .61, 'Mouth'],
    ]) assert.equal(suggestRegion({sketch_id, x, y}), expected, `${sketch_id} ${x},${y}`);
});

test('toe edge suggestions retain sided toe labels on dorsal and plantar views', () => {
    for (const [surface, examples] of [
        ['dorsal', [['big toe', .17, .92], ['big toe', .355, .968], ['second toe', .48, .944], ['third toe', .645, .922], ['fourth toe', .752, .883], ['little toe', .85, .813]]],
        ['plantar', [['big toe', .88, .90], ['big toe', .76, .966], ['second toe', .50, .942], ['third toe', .38, .916], ['fourth toe', .29, .884], ['little toe', .21, .85]]],
    ]) for (const [toe, x, y] of examples) {
        assert.equal(suggestRegion({sketch_id: `foot-left-${surface}`, x, y}), `Left ${toe}`, `${surface} ${x},${y}`);
        assert.equal(suggestRegion({sketch_id: `foot-right-${surface}`, x: 1 - x, y}), `Right ${toe}`, `${surface} mirrored ${x},${y}`);
    }
    for (const location of [
        {sketch_id: 'foot-left-dorsal', x: .406, y: .86},
        {sketch_id: 'foot-right-dorsal', x: .594, y: .86},
        {sketch_id: 'head-front', x: .02, y: .40},
        {sketch_id: 'head-top', x: .025, y: .35},
    ]) assert.equal(suggestRegion(location), null, JSON.stringify(location));
});

test('region outlines retain provenance for the bundled SVG artwork', async () => {
    for (const [id, hash] of Object.entries(surfaces.sources)) {
        const source = sketches.find(s => s.id === id);
        assert.equal(createHash('sha256').update(await readFile(new URL(source.src))).digest('hex'), hash, `${id}: retrace region surfaces after changing the artwork`);
    }
});

test('groin, inner thighs, upper chest, and limb edges fill the full-body gaps', () => {
    for (const [sketch_id, x, y, expected] of [
        ['body-front', 0.5, 0.48, 'Groin'], ['body-front', 0.45, 0.50, 'Groin'],
        ['body-back', 0.5, 0.50, 'Groin'], ['body-back', 0.40, 0.50, 'Groin'],
        ['body-front', 0.44, 0.59, 'Right inner thigh'], ['body-back', 0.425, 0.59, 'Left inner thigh'],
        ['body-front', 0.56, 0.59, 'Left inner thigh'], ['body-back', 0.575, 0.59, 'Right inner thigh'],
        ['body-front', 0.5, 0.215, 'Upper chest'], ['body-back', 0.5, 0.215, 'Upper back'],
        ['body-front', 0.44, 0.17, 'Right collarbone'], ['body-front', 0.56, 0.17, 'Left collarbone'],
        ['body-front', 0.30, 0.26, 'Right armpit'], ['body-back', 0.30, 0.26, 'Left armpit'],
    ]) assert.equal(suggestRegion({sketch_id, x, y}), expected, `${sketch_id} ${x},${y}`);
    assert.equal(bodyRegions.Groin, 'Groin');
    assert.equal(Object.hasOwn(bodyRegions, 'Buttocks'), false);
});

test('nipple zones follow the SVG circles with an enlarged radius', () => {
    for (const [name, bounds] of Object.entries(surfaces.landmarks['body-front'])) {
        const {x, y, rx, ry} = bounds;
        for (const [dx, dy] of [[0, 0], [rx * 0.9, 0], [0, ry * 0.9]]) {
            assert.equal(suggestRegion({sketch_id: 'body-front', x: x + dx, y: y + dy}), bodyRegions[name]);
        }
        assert.equal(suggestRegion({sketch_id: 'body-front', x: x + rx * 1.2, y}), name === 'LeftNipple' ? 'Left chest' : 'Right chest');
    }
});

test('every traced anatomical boundary has a standard region suggestion', () => {
    assert.equal(Object.keys(surfaces.outlines).length, sketches.length);
    for (const [sketch_id, outlines] of Object.entries(surfaces.outlines)) {
        for (const outline of outlines.filter(polygon => polygon.length >= 3)) {
            for (const [x, y] of outline) {
                const label = suggestRegion({sketch_id, x, y});
                assert.ok(Object.values(bodyRegions).includes(label), `${sketch_id} ${x},${y}: ${label}`);
            }
        }
    }
});

test('full-body feet include the outer toe and sole edges on both sides', () => {
    for (const sketch_id of ['body-front', 'body-back']) {
        const seen = new Set();
        for (const outline of surfaces.outlines[sketch_id]) {
            for (const [x, y] of outline.filter(([, y]) => y > 0.96)) {
                const side = x < 0.5 ? (sketch_id === 'body-front' ? 'Right' : 'Left') : (sketch_id === 'body-front' ? 'Left' : 'Right');
                assert.equal(suggestRegion({sketch_id, x, y}), `${side} foot`, `${sketch_id} ${x},${y}`);
                seen.add(side);
            }
        }
        assert.equal(seen.size, 2);
    }
});
