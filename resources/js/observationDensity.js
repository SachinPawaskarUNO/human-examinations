import {getObservationLocation} from './observationLocations.js';

// A fixed image-space kernel keeps the density independent of zoom and viewport size.
export function buildHeatmaps(sketches, observations) {
    const maps = Object.fromEntries(sketches.map((sketch) => {
        const ratio = sketch.width / sketch.height;
        const width = Math.max(1, Math.round(ratio <= 1 ? 256 * ratio : 256));
        const height = Math.max(1, Math.round(ratio <= 1 ? 256 : 256 / ratio));
        return [sketch.id, {width, height, values: new Float32Array(width * height), peak: 0, count: 0}];
    }));
    let peak = 0;
    let count = 0;
    for (const observation of observations) {
        const location = getObservationLocation(observation);
        const map = location && maps[location.sketch_id];
        if (!map || !Object.hasOwn(maps, location.sketch_id)) continue;
        map.count++;
        count++;
        const radius = Math.max(2, Math.min(map.width, map.height) * 0.12);
        const cx = location.x * (map.width - 1), cy = location.y * (map.height - 1);
        for (let y = Math.max(0, Math.floor(cy - radius)); y <= Math.min(map.height - 1, Math.ceil(cy + radius)); y++) {
            for (let x = Math.max(0, Math.floor(cx - radius)); x <= Math.min(map.width - 1, Math.ceil(cx + radius)); x++) {
                const distance = ((x - cx) ** 2 + (y - cy) ** 2) / radius ** 2;
                if (distance > 1) continue;
                const index = y * map.width + x;
                map.values[index] += Math.exp(-4.5 * distance);
                map.peak = Math.max(map.peak, map.values[index]);
            }
        }
        peak = Math.max(peak, map.peak);
    }
    return {maps, peak, count};
}

export function densityColor(value, peak) {
    if (!(value > 0) || !(peak > 0)) return [0, 0, 0, 0];
    const intensity = Math.min(1, value / peak);
    const stops = [[37, 99, 235], [6, 182, 212], [250, 204, 21], [249, 115, 22], [220, 38, 38]];
    const position = intensity * (stops.length - 1);
    const index = Math.min(stops.length - 2, Math.floor(position));
    const fraction = position - index;
    return [...stops[index].map((channel, i) => Math.round(channel + (stops[index + 1][i] - channel) * fraction)), Math.round(210 * Math.sqrt(intensity))];
}
