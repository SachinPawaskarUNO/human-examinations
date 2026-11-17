import assert from 'node:assert/strict';
import {test} from 'node:test';
import {clampCamera, fitDiagram, panCamera, wheelZoomFactor, zoomCamera} from '../../resources/js/diagramViewport.js';
import {pointToLocation} from '../../resources/js/observationLocations.js';

const viewport = {width: 400, height: 600};
const image = {width: 240, height: 600};

test('diagrams fit the available viewport without changing their aspect ratio', () => {
    assert.deepEqual(fitDiagram(viewport, 0.4), image);
    assert.deepEqual(fitDiagram({width: 180, height: 600}, 0.4), {width: 180, height: 450});
    const front = fitDiagram(viewport, 0.4, 0.5);
    const back = fitDiagram(viewport, 0.5, 0.5);
    assert.equal(front.height, back.height, 'paired drawings retain the same height');
});

test('zoom keeps the point under the cursor fixed within the image', () => {
    const previous = {zoom: 2, x: 0, y: -40};
    const anchor = {x: 20, y: -90};
    const next = zoomCamera(previous, 3, anchor, viewport, image);
    for (const axis of ['x', 'y']) {
        assert.equal((anchor[axis] - previous[axis]) / previous.zoom, (anchor[axis] - next[axis]) / next.zoom);
    }
});

test('panning is bounded and resetting or shrinking zoom brings the image back into view', () => {
    assert.deepEqual(panCamera({zoom: 2, x: 0, y: 0}, 1000, -1000, viewport, image), {zoom: 2, x: 40, y: -300});
    assert.deepEqual(clampCamera({zoom: 1, x: 400, y: -500}, viewport, image), {zoom: 1, x: 0, y: 0});
    assert.equal(clampCamera({zoom: 20, x: 0, y: 0}, viewport, image).zoom, 5);
    assert.equal(clampCamera({zoom: 0.1, x: 0, y: 0}, viewport, image).zoom, 1);
    const resized = clampCamera({zoom: 2, x: 200, y: 200}, {width: 800, height: 900}, image);
    assert.deepEqual(resized, {zoom: 2, x: 0, y: 150});
});

test('wheel pixels, lines, and pages zoom in the same direction with bounded steps', () => {
    assert.ok(wheelZoomFactor(-40, 0, 600) > 1);
    assert.ok(wheelZoomFactor(40, 0, 600) < 1);
    assert.equal(wheelZoomFactor(2, 1, 600), wheelZoomFactor(32, 0, 600));
    assert.equal(wheelZoomFactor(1, 2, 600), wheelZoomFactor(10000, 0, 600));
    assert.equal(wheelZoomFactor(0, 0, 600), 1);
});

test('the same anatomical point retains its normalized location after zoom, pan, and resize', () => {
    const point = {x: 0.35, y: 0.6};
    for (const [size, camera] of [[viewport, {zoom: 1, x: 0, y: 0}], [viewport, {zoom: 3, x: 60, y: -200}], [{width: 220, height: 420}, {zoom: 2, x: -50, y: 40}]]) {
        const fitted = fitDiagram(size, 0.4);
        const bounds = {width: fitted.width * camera.zoom, height: fitted.height * camera.zoom};
        bounds.left = 100 + size.width / 2 + camera.x - bounds.width / 2;
        bounds.top = 50 + size.height / 2 + camera.y - bounds.height / 2;
        assert.deepEqual(pointToLocation('body-front', bounds.left + point.x * bounds.width, bounds.top + point.y * bounds.height, bounds), {sketch_id: 'body-front', ...point});
    }
});
