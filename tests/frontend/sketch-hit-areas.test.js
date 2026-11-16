import assert from 'node:assert/strict';
import {test} from 'node:test';
import {locationOnSketch, initialSketchLocation} from '../../resources/js/sketchHitAreas.js';

const area = {
    sketchId: 'body-front',
    contains: (x, y) => x >= 0.25 && x <= 0.75 && y >= 0.1 && y <= 0.45,
};

test('placement requires loaded geometry for the correct sketch', () => {
    const location = {sketch_id: 'body-front', x: '0.500000', y: '0.350000'};
    assert.deepEqual(locationOnSketch(location, area), {sketch_id: 'body-front', x: 0.5, y: 0.35});
    assert.equal(locationOnSketch(location, null), null);
    assert.equal(locationOnSketch({...location, sketch_id: 'body-back'}, area), null);
    assert.equal(locationOnSketch({...location, x: NaN}, area), null);
});

test('blank areas are rejected while valid locations remain unchanged', () => {
    for (const [x, y] of [[0.05, 0.3], [0.95, 0.3], [0.5, 0.7], [0.5, 0.05]]) {
        assert.equal(locationOnSketch({sketch_id: 'body-front', x, y}, area), null);
    }
    const location = {sketch_id: 'body-front', x: 0.3, y: 0.2};
    assert.deepEqual(locationOnSketch(location, area), location);
});

test('keyboard placement starts on the anatomy when the image centre is outside it', () => {
    assert.deepEqual(initialSketchLocation(area), {sketch_id: 'body-front', x: 0.5, y: 0.35});
    assert.equal(initialSketchLocation(null), null);
    assert.equal(initialSketchLocation({...area, contains: () => false}), null);
    assert.deepEqual(initialSketchLocation({...area, contains: () => true}), {
        sketch_id: 'body-front', x: 0.5, y: 0.5,
    });
});
