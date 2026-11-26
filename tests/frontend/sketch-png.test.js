import assert from 'node:assert/strict';
import {test} from 'node:test';
import {sketchMarkers} from '../../resources/js/sketchPng.js';

const sketch = {id: 'body-front', label: 'Body · Front', width: 100, height: 200};

test('only pins on the exported sketch are drawn', () => {
    const markers = sketchMarkers(sketch, [
        {number: 1, sketch_id: 'body-front', x: 0.25, y: 0.5},
        {number: 2, sketch_id: 'body-back', x: 0.1, y: 0.1},
        {number: 3, sketch_id: null, x: null, y: null},
    ]);

    assert.deepEqual(markers.map((marker) => marker.number), [1]);
});
