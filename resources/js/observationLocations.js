const roundedCoordinate = (value) => Number(Math.max(0, Math.min(1, value)).toFixed(6));

export function getObservationLocation(observation) {
    if (!observation || typeof observation.sketch_id !== 'string' || !observation.sketch_id.trim()) {
        return null;
    }

    const valid = [observation.x, observation.y].every((value) =>
        (typeof value === 'number' || (typeof value === 'string' && value.trim() !== ''))
        && Number.isFinite(Number(value)) && Number(value) >= 0 && Number(value) <= 1,
    );

    return valid ? {sketch_id: observation.sketch_id, x: Number(observation.x), y: Number(observation.y)} : null;
}

export function pointToLocation(sketchId, clientX, clientY, bounds) {
    if (!bounds || typeof sketchId !== 'string' || !sketchId.trim()
        || ![clientX, clientY, bounds.left, bounds.top, bounds.width, bounds.height].every(Number.isFinite)
        || bounds.width <= 0 || bounds.height <= 0) {
        return null;
    }

    return {
        sketch_id: sketchId,
        x: roundedCoordinate((clientX - bounds.left) / bounds.width),
        y: roundedCoordinate((clientY - bounds.top) / bounds.height),
    };
}

export function offsetLocation(location, dx, dy) {
    const current = getObservationLocation(location);
    if (!current || !Number.isFinite(dx) || !Number.isFinite(dy)) return null;

    return {
        ...current,
        x: roundedCoordinate(current.x + dx),
        y: roundedCoordinate(current.y + dy),
    };
}
