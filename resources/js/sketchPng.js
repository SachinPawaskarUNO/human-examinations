import {getObservationLocation} from './observationLocations.js';
import {observationStyle, observationType} from './observationTypes.js';
import {pngPrintResolution} from './pngResolution.js';

// US Letter. Sizes are in points (72 per inch).
const page = {width: 612, height: 792, margin: 36, dpi: 300};
const contentWidth = page.width - 2 * page.margin;
const legendColumns = 3;
const legendRowHeight = 12;
const credit = 'Artwork: INTERPOL DVI Form 2023, physical PDF page 14, Appendix 2 (835 Body Sketch). Anatomical left and right refer to the subject. Extracted with permission.';

export function sketchMarkers(sketch, observations, markerColors = {}) {
    return observations.flatMap((observation) => {
        const location = getObservationLocation(observation);
        const number = Number(observation.number);
        if (location?.sketch_id !== sketch.id || !Number.isInteger(number) || number < 1) return [];

        return [{
            ...location, number,
            type: observationType(observation.type),
            colors: observationStyle(observation.type, markerColors),
        }];
    });
}

function wrapText(text, maxWidth, measure) {
    const lines = [];
    let line = '';
    for (const word of text.trim().split(/\s+/)) {
        const candidate = line ? `${line} ${word}` : word;
        if (measure(candidate) <= maxWidth) {
            line = candidate;
            continue;
        }
        if (line) lines.push(line);
        line = '';
        // Break words that are too long for a line of their own.
        for (const character of word) {
            if (line && measure(line + character) > maxWidth) {
                lines.push(line);
                line = '';
            }
            line += character;
        }
    }
    if (line) lines.push(line);
    return lines;
}

export function exportHeader(sketch, examination, exportedAt, measure) {
    const fields = [
        ['Person', examination.person_name],
        ['Reference', examination.reference],
        ['Examiner', examination.examiner],
        ['Examination date', examination.date],
        ['Exported at (UTC)', exportedAt],
    ]
        .map(([label, value]) => [String(label ?? '').trim(), String(value ?? '').trim()])
        .filter(([label, value]) => label && value);
    const rows = [
        {text: sketch.label, font: 'bold 14px sans-serif', lineHeight: 19},
        ...fields.map(([label, value]) => ({text: `${label}: ${value}`, font: '9px sans-serif', lineHeight: 12})),
    ];

    const lines = [];
    let height = 0;
    for (const {text, font, lineHeight} of rows) {
        for (const part of wrapText(text, contentWidth, (value) => measure(value, font))) {
            lines.push({text: part, font, y: height + lineHeight / 2});
            height += lineHeight;
        }
    }
    return {lines, height};
}

// Fits the sketch between the header and the legend.
export function exportLayout(sketch, headerHeight, legendCount) {
    const sketchTop = page.margin + headerHeight + 20;
    const legendRows = Math.ceil(legendCount / legendColumns);
    const legendTop = page.height - page.margin - 18 - legendRows * legendRowHeight;
    const space = legendTop - 14 - sketchTop;
    const scale = Math.min(contentWidth / sketch.width, space / sketch.height);
    const width = sketch.width * scale;
    const height = sketch.height * scale;

    return {
        width, height, legendTop,
        left: (page.width - width) / 2,
        top: sketchTop + (space - height) / 2,
        sidesY: sketchTop - 8,
    };
}

export async function renderSketchPng(sketch, observations, {examination = {}, markerColors = {}} = {}) {
    const markers = sketchMarkers(sketch, observations, markerColors);
    const legend = [...new Map(markers.map(({type, colors}) => [type, colors]))]
        .sort(([a], [b]) => a.localeCompare(b));

    const image = new Image();
    image.crossOrigin = 'anonymous'; // Sketches may be served by Vite or a CDN.
    image.src = sketch.src;
    try {
        await image.decode();
    } catch {
        throw new Error(`The ${sketch.label} sketch could not be loaded.`);
    }

    const pixelsPerPoint = page.dpi / 72;
    const canvas = document.createElement('canvas');
    canvas.width = page.width * pixelsPerPoint;
    canvas.height = page.height * pixelsPerPoint;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Could not create the export canvas.');
    ctx.scale(pixelsPerPoint, pixelsPerPoint);

    const exportedAt = new Date().toISOString().slice(0, 16).replace('T', ' ');
    const header = exportHeader(sketch, examination, exportedAt, (text, font) => {
        ctx.font = font;
        return ctx.measureText(text).width;
    });
    const layout = exportLayout(sketch, header.height, legend.length);

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, page.width, page.height);
    ctx.drawImage(image, layout.left, layout.top, layout.width, layout.height);
    ctx.textBaseline = 'middle';

    // Header
    ctx.fillStyle = '#171717';
    for (const {text, font, y} of header.lines) {
        ctx.font = font;
        ctx.fillText(text, page.margin, page.margin + y);
    }

    // Side labels and pins
    ctx.textAlign = 'center';
    if (sketch.sides) {
        ctx.font = '9px sans-serif';
        ctx.fillText(sketch.sides[0], layout.left, layout.sidesY);
        ctx.fillText(sketch.sides[1], layout.left + layout.width, layout.sidesY);
    }
    ctx.font = 'bold 8px sans-serif';
    ctx.strokeStyle = '#fff';
    for (const {x, y, number, colors} of markers) {
        const pinX = layout.left + x * layout.width;
        const pinY = layout.top + y * layout.height;
        ctx.beginPath();
        ctx.arc(pinX, pinY, 7, 0, Math.PI * 2);
        ctx.fillStyle = colors['--he-observation-color'];
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = colors['--he-observation-foreground'];
        ctx.fillText(String(number), pinX, pinY, 11);
    }

    // Legend and credit
    ctx.textAlign = 'left';
    ctx.font = '8px sans-serif';
    const columnWidth = contentWidth / legendColumns;
    for (const [index, [type, colors]] of legend.entries()) {
        const x = page.margin + (index % legendColumns) * columnWidth;
        const y = layout.legendTop + Math.floor(index / legendColumns) * legendRowHeight;
        ctx.fillStyle = colors['--he-observation-color'];
        ctx.fillRect(x, y - 3.5, 7, 7);
        ctx.fillStyle = '#171717';
        ctx.fillText(type, x + 11, y, columnWidth - 16);
    }
    ctx.fillStyle = '#525252';
    ctx.font = '7px sans-serif';
    ctx.fillText(credit, page.margin, page.height - page.margin, contentWidth);

    const png = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (!png) throw new Error('Could not generate the PNG.');
    return pngPrintResolution(png, page.dpi);
}
