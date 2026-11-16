import {getObservationLocation} from './observationLocations.js';

const cache = new Map();

export function locationOnSketch(location, area) {
    const point = getObservationLocation(location);
    return point && area?.sketchId === point.sketch_id && area.contains(point.x, point.y)
        ? point : null;
}

export function initialSketchLocation(area) {
    if (!area) return null;
    for (const [x, y] of [[0.5, 0.5], [0.5, 0.35], [0.5, 0.25], [0.35, 0.5], [0.65, 0.5], [0.5, 0.65]]) {
        const point = locationOnSketch({sketch_id: area.sketchId, x, y}, area);
        if (point) return point;
    }
    return null;
}

function inverseTransform(path, root) {
    let matrix = new DOMMatrix();
    for (let element = path; element && element !== root.parentElement; element = element.parentElement) {
        const transform = element.transform?.baseVal.consolidate()?.matrix;
        if (transform) {
            matrix = new DOMMatrix([
                transform.a, transform.b, transform.c, transform.d, transform.e, transform.f,
            ]).multiply(matrix);
        }
    }
    return matrix.inverse();
}

async function readHitArea(sketch) {
    const response = await fetch(sketch.src);
    if (!response.ok) throw new Error('The anatomical outline could not be loaded.');
    const document = new DOMParser().parseFromString(await response.text(), 'image/svg+xml');
    const root = document.documentElement;
    if (root.localName !== 'svg' || document.querySelector('parsererror')) {
        throw new Error('The anatomical outline is not a valid SVG.');
    }
    const viewBox = root.getAttribute('viewBox')?.trim().split(/[\s,]+/).map(Number);
    if (viewBox?.length !== 4 || !viewBox.every(Number.isFinite) || viewBox[2] <= 0 || viewBox[3] <= 0) {
        throw new Error('The anatomical outline has no valid view box.');
    }
    // The original drawings fill the anatomy in white. Use those filled paths
    // rather than the black detail strokes or the coarse region suggestion zones.
    const paths = [...root.querySelectorAll('path[fill]')]
        .filter((path) => !path.closest('defs') && /^(#fff(?:fff)?|white)$/i.test(path.getAttribute('fill')))
        .map((path) => ({
            outline: new Path2D(path.getAttribute('d')),
            inverse: inverseTransform(path, root),
            rule: path.closest('[fill-rule]')?.getAttribute('fill-rule') === 'evenodd' ? 'evenodd' : 'nonzero',
        }));
    const context = globalThis.document.createElement('canvas').getContext('2d');
    if (!context || !paths.length) throw new Error('The anatomical outline is unavailable.');
    return {
        sketchId: sketch.id,
        contains(x, y) {
            const svgX = viewBox[0] + x * viewBox[2];
            const svgY = viewBox[1] + y * viewBox[3];
            return paths.some(({outline, inverse, rule}) => context.isPointInPath(
                outline,
                inverse.a * svgX + inverse.c * svgY + inverse.e,
                inverse.b * svgX + inverse.d * svgY + inverse.f,
                rule,
            ));
        },
    };
}

export function loadSketchHitArea(sketch) {
    const key = `${sketch.id}:${sketch.src}`;
    if (!cache.has(key)) {
        cache.set(key, readHitArea(sketch).catch((error) => {
            cache.delete(key);
            throw error;
        }));
    }
    return cache.get(key);
}
