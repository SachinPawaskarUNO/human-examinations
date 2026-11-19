import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { sketchGroups, sketches } from '../../resources/js/sketches.js';

test('the catalog provides stable IDs for all fifteen anatomical views', () => {
    assert.deepEqual(sketchGroups.map((group) => group.id), ['body', 'head', 'hands', 'feet']);
    assert.deepEqual(sketches.map((sketch) => sketch.id), [
        'body-front', 'body-back',
        'head-front', 'head-back', 'head-left-profile', 'head-right-profile', 'head-top',
        'hand-left-palmar', 'hand-right-palmar', 'hand-left-dorsal', 'hand-right-dorsal',
        'foot-left-dorsal', 'foot-right-dorsal', 'foot-left-plantar', 'foot-right-plantar',
    ]);
    assert.equal(new Set(sketches.map((sketch) => sketch.id)).size, 15);
});

test('every catalog entry resolves to an attributed SVG with matching dimensions', async () => {
    for (const sketch of sketches) {
        const svg = await readFile(new URL(sketch.src), 'utf8');
        const root = svg.match(/<svg\b[^>]*>/)[0];
        const viewBox = root.match(/viewBox="([^"]+)"/)[1].split(/\s+/).map(Number);

        assert.deepEqual(viewBox, [0, 0, sketch.width, sketch.height], sketch.id);
        assert.ok(sketch.width > 0 && sketch.height > 0, sketch.id);
        assert.match(svg, /<title>[^<]+<\/title>/);
        assert.match(svg, /<desc>[^<]*INTERPOL DVI Form 2023[^<]*Extracted with permission\.<\/desc>/);
        assert.doesNotMatch(svg, /<(?:script|foreignObject|image)\b|\son\w+\s*=/i);
        assert.doesNotMatch(svg, /(?:href|url)\s*(?:=\s*["']|\(\s*["']?)\s*(?:https?:|\/\/|data:)/i);
    }
});

test('front and back body views label anatomical sides from the subject perspective', () => {
    assert.deepEqual(sketches.find((sketch) => sketch.id === 'body-front').sides, ['Right', 'Left']);
    assert.deepEqual(sketches.find((sketch) => sketch.id === 'body-back').sides, ['Left', 'Right']);
});
