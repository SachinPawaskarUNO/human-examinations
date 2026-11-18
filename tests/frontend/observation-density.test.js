import assert from 'node:assert/strict';
import {test} from 'node:test';
import {buildHeatmaps, densityColor} from '../../resources/js/observationDensity.js';

const views = [{id: 'front', width: 100, height: 200}, {id: 'back', width: 100, height: 200}];
const point = (sketch_id, x = 0.5, y = 0.5) => ({sketch_id, x, y});

test('density is scoped to sketches and ignores missing or invalid locations', () => {
    const result = buildHeatmaps(views, [point('front'), point('back'), point('elsewhere'), {}, point('front', NaN), point('front', 2)]);
    assert.equal(result.maps.front.count, 1);
    assert.equal(result.maps.back.count, 1);
    assert.equal(result.count, 2);
    assert.equal(result.maps.front.width / result.maps.front.height, 0.5);
});

test('overlapping findings produce stronger density on a shared scale', () => {
    const result = buildHeatmaps(views, [point('front'), point('front'), point('back')]);
    assert.ok(Math.abs(result.maps.front.peak / result.maps.back.peak - 2) < 0.001);
    assert.equal(result.peak, result.maps.front.peak);
    const center = Math.floor(result.maps.front.height / 2) * result.maps.front.width + Math.floor(result.maps.front.width / 2);
    assert.ok(result.maps.front.values[center] > 0);
    assert.equal(result.maps.front.values[0], 0);
});

test('empty maps are transparent and image-edge findings are retained', () => {
    const empty = buildHeatmaps(views, []);
    assert.equal(empty.count, 0);
    assert.equal(empty.peak, 0);
    assert.deepEqual(densityColor(0, 0), [0, 0, 0, 0]);
    const edges = buildHeatmaps(views, [point('front', 0, 0), point('front', 1, 1)]);
    assert.equal(edges.maps.front.count, 2);
    assert.ok(edges.maps.front.values[0] > 0);
    assert.ok(edges.maps.front.values.at(-1) > 0);
    assert.notDeepEqual(densityColor(edges.peak, edges.peak), densityColor(edges.peak / 2, edges.peak));
});
