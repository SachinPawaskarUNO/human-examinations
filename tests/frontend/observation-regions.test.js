import assert from 'node:assert/strict';
import {test} from 'node:test';
import {bodyRegions, suggestRegion} from '../../resources/js/observationRegions.js';

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

test('sided buttocks and joints respect the front/back orientation', () => {
    for (const [sketch_id, x, y, expected] of [
        ['body-back', 0.43, 0.46, 'Left buttock'], ['body-back', 0.57, 0.46, 'Right buttock'],
        ['body-back', 0.5, 0.46, 'Buttocks'],
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
