const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

export function fitDiagram(viewport, ratio, fitRatio = ratio) {
    const height = Math.min(viewport.height, viewport.width / fitRatio);
    return {width: height * ratio, height};
}

export function clampCamera(camera, viewport, image) {
    const zoom = clamp(camera.zoom, 1, 5);
    const limit = (value, extent) => extent > 0 ? clamp(value, -extent, extent) : 0;
    return {
        zoom,
        x: limit(camera.x, (image.width * zoom - viewport.width) / 2),
        y: limit(camera.y, (image.height * zoom - viewport.height) / 2),
    };
}

export function zoomCamera(camera, zoom, anchor, viewport, image) {
    const nextZoom = clamp(zoom, 1, 5);
    const factor = nextZoom / camera.zoom;
    return clampCamera({
        zoom: nextZoom,
        x: anchor.x - (anchor.x - camera.x) * factor,
        y: anchor.y - (anchor.y - camera.y) * factor,
    }, viewport, image);
}

export function panCamera(camera, dx, dy, viewport, image) {
    return clampCamera({...camera, x: camera.x + dx, y: camera.y + dy}, viewport, image);
}

export function wheelZoomFactor(delta, mode, height) {
    const unit = mode === 1 ? 16 : mode === 2 ? height : 1;
    return Math.exp(-clamp(delta * unit, -100, 100) * 0.0025);
}
