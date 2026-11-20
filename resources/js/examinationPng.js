import {downloadFile} from './examinationTransfer.js';
import {getObservationLocation} from './observationLocations.js';
import {observationStyle} from './observationTypes.js';

export async function exportSketchPng(sketch, observations, markerColors = {}) {
    const markers = observations.flatMap(observation => {
        const location = getObservationLocation(observation);
        const number = Number(observation.number);
        if (location?.sketch_id !== sketch.id || !Number.isInteger(number) || number < 1) return [];

        return [{...location, number, colors: observationStyle(observation.type, markerColors)}];
    });

    const image = new Image();
    image.src = sketch.src;
    await image.decode();

    const canvas = document.createElement('canvas');
    canvas.width = 2550;
    canvas.height = 3300;

    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Could not create the export canvas.');

    const margin = 150;
    const scale = Math.min(
        (canvas.width - 2 * margin) / sketch.width,
        (canvas.height - 2 * margin) / sketch.height,
    );
    const width = sketch.width * scale;
    const height = sketch.height * scale;
    const left = (canvas.width - width) / 2;
    const top = (canvas.height - height) / 2;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(image, left, top, width, height);
    ctx.font = 'bold 32px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    for (const {x, y, number, colors} of markers) {
        const pixelX = left + x * width;
        const pixelY = top + y * height;
        ctx.beginPath();
        ctx.arc(pixelX, pixelY, 30, 0, Math.PI * 2);
        ctx.fillStyle = colors['--he-observation-color'];
        ctx.fill();
        ctx.fillStyle = colors['--he-observation-foreground'];
        ctx.fillText(String(number), pixelX, pixelY, 48);
    }

    const png = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
    if (!png) throw new Error('Could not generate the PNG.');

    downloadFile(png, `human-examination-${sketch.id}.png`);
}
