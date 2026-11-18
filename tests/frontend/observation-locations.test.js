import assert from 'node:assert/strict';
import {test} from 'node:test';
import {getObservationLocation, pointToLocation, offsetLocation} from '../../resources/js/observationLocations.js';

test('saved decimal coordinates, including the image edges, become numeric locations', () => {
    assert.deepEqual(getObservationLocation({sketch_id: 'body-back', x: '0.250000', y: '0.750000'}), {
        sketch_id: 'body-back', x: 0.25, y: 0.75,
    });
    assert.deepEqual(getObservationLocation({sketch_id: 'body-front', x: 0, y: 1}), {
        sketch_id: 'body-front', x: 0, y: 1,
    });
});

test('incomplete, nonfinite, and out-of-range locations do not produce markers', () => {
    for (const location of [
        null, {}, {sketch_id: null, x: 0, y: 0}, {sketch_id: ' ', x: 0, y: 0},
        {sketch_id: 'body-front', x: null, y: 0}, {sketch_id: 'body-front', x: '', y: 0},
        {sketch_id: 'body-front', x: false, y: 0}, {sketch_id: 'body-front', x: 0.5},
        {sketch_id: 'body-front', x: NaN, y: 0}, {sketch_id: 'body-front', x: 0, y: Infinity},
        {sketch_id: 'body-front', x: -0.1, y: 0}, {sketch_id: 'body-front', x: 0, y: 1.01},
    ]) assert.equal(getObservationLocation(location), null);
});

test('pointer coordinates use image bounds and remain stable across image sizes', () => {
    const first = pointToLocation('body-front', 150, 250, {left: 100, top: 50, width: 200, height: 400});
    const resized = pointToLocation('body-front', 325, 425, {left: 300, top: 325, width: 100, height: 200});
    assert.deepEqual(first, {sketch_id: 'body-front', x: 0.25, y: 0.5});
    assert.deepEqual(resized, first);
    assert.deepEqual(pointToLocation('body-back', -10, 1000, {left: 0, top: 0, width: 100, height: 100}), {
        sketch_id: 'body-back', x: 0, y: 1,
    });
    assert.equal(pointToLocation('body-front', 1, 1, {left: 0, top: 0, width: 0, height: 100}), null);
});

test('pointer placement and keyboard refinement round to six decimals and clamp at the edges', () => {
    assert.deepEqual(pointToLocation('head-front', 1, 2, {left: 0, top: 0, width: 3, height: 3}), {
        sketch_id: 'head-front', x: 0.333333, y: 0.666667,
    });
    assert.deepEqual(offsetLocation({sketch_id: 'body-front', x: '0.999000', y: '0.001000'}, 0.005, -0.005), {
        sketch_id: 'body-front', x: 1, y: 0,
    });
});
