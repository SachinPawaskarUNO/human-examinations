export const examinationFormat = 'spawaskar/human-examinations';
export const examinationVersion = 1;
const metadataFields = ['reference', 'examiner', 'date', 'notes'];
const observationFields = ['number', 'type', 'label', 'region', 'description', 'length_mm', 'width_mm', 'sketch_id', 'x', 'y'];
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const fail = message => { throw new Error(message); };
function keys(value, allowed, name) {
    if (!object(value) || Object.keys(value).some(key => !allowed.includes(key))) fail(`${name} contains unsupported fields.`);
}
function text(value, name, max = Infinity) {
    if (value == null) return null;
    if (typeof value !== 'string' || Array.from(value).length > max) fail(`${name} must be text${Number.isFinite(max) ? ` of at most ${max} characters` : ''}.`);
    return value;
}
function decimal(value, name, max, places) {
    if (value == null) return null;
    const validSyntax = typeof value === 'number'
        || (typeof value === 'string' && /^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim()));
    const number = validSyntax ? Number(value) : NaN;
    if (!Number.isFinite(number) || number < 0 || number > max) fail(`${name} must be a number between 0 and ${max}.`);
    return number.toFixed(places);
}

export function parseExaminationDocument(value, {types = [], maxObservations = 10000} = {}) {
    keys(value, ['format', 'version', 'examination', 'observations'], 'The document');
    if (value.format !== examinationFormat || value.version !== examinationVersion) fail('This examination format or version is not supported.');
    keys(value.examination, [...metadataFields, 'person_name'], 'Examination details');
    if (!Array.isArray(value.observations) || value.observations.length > maxObservations) fail(`The file must contain at most ${maxObservations} observations.`);
    const examination = Object.fromEntries(metadataFields.map(field => [field, text(value.examination[field], field, ['reference', 'examiner'].includes(field) ? 255 : Infinity)]));
    if (value.examination.person_name !== undefined) examination.person_name = text(value.examination.person_name, 'person_name', 255);
    if (examination.date != null) {
        const date = new Date(examination.date + 'T00:00:00Z');
        if (!/^\d{4}-\d{2}-\d{2}$/.test(examination.date) || !Number.isFinite(date.getTime()) || date.toISOString().slice(0, 10) !== examination.date) fail('The examination date must be a valid YYYY-MM-DD date.');
    }
    const used = new Set();
    const observations = value.observations.map((row, index) => {
        const name = `Observation ${index + 1}`;
        keys(row, observationFields, name);
        if (!Number.isInteger(row.number) || row.number < 1 || row.number > 4294967295 || used.has(row.number)) fail(`${name} needs a unique positive integer number.`);
        used.add(row.number);
        if (typeof row.type !== 'string' || !row.type.trim() || (types.length && !types.includes(row.type))) fail(`${name} has an unsupported observation type.`);
        const result = {
            number: row.number, type: row.type,
            label: text(row.label, `${name} label`, 255), region: text(row.region, `${name} region`, 255),
            description: text(row.description, `${name} description`),
            length_mm: decimal(row.length_mm, `${name} length`, 99999999.99, 2),
            width_mm: decimal(row.width_mm, `${name} width`, 99999999.99, 2),
            sketch_id: text(row.sketch_id, `${name} sketch identifier`, 255),
            x: decimal(row.x, `${name} x coordinate`, 1, 6), y: decimal(row.y, `${name} y coordinate`, 1, 6),
        };
        if ([result.sketch_id, result.x, result.y].some(v => v !== null)
            && (!result.sketch_id?.trim() || result.x === null || result.y === null)) fail(`${name} needs a sketch identifier and both coordinates, or no location.`);
        return result;
    });
    return {format: examinationFormat, version: examinationVersion, examination, observations};
}

export function createExaminationDocument(examination, observations) {
    return parseExaminationDocument({
        format: examinationFormat, version: examinationVersion,
        examination: {
            ...Object.fromEntries(metadataFields.map(field => [field, examination?.[field] ?? null])),
            // Exported when the examination has a person name field, i.e. person names are on.
            ...(examination && 'person_name' in examination ? {person_name: examination.person_name ?? null} : {}),
        },
        observations: observations.map(row => Object.fromEntries(observationFields.map(field => [field, field === 'number' ? Number(row[field]) : row[field] ?? null]))),
    }, {maxObservations: Infinity});
}

export function examinationCsv(document) {
    // The document includes person_name only when person names are on.
    const personName = 'person_name' in document.examination ? ['person_name'] : [];
    const headers = ['examination_reference', 'examiner', ...personName, 'date', 'examination_notes', ...observationFields];
    const cell = value => {
        let result = String(value ?? '');
        if (/^[\x00-\x20]*[=+\-@]|^[\t\r\n]/.test(result)) result = "'" + result;
        return '"' + result.replaceAll('"', '""') + '"';
    };
    const metadata = ['reference', 'examiner', ...personName, 'date', 'notes'].map(field => document.examination[field]);
    const rows = (document.observations.length ? document.observations : [{}]).map(row => [...metadata, ...observationFields.map(field => row[field])]);
    return '\uFEFF' + [headers, ...rows].map(row => row.map(cell).join(',')).join('\r\n') + '\r\n';
}

export function downloadFile(content, filename, type) {
    const blob = content instanceof Blob ? content : new Blob([content], {type});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url; link.download = filename;
    document.body.append(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export async function downloadExaminationResponse(url, format, filename) {
    const response = await fetch(url, {credentials: 'same-origin'});
    const type = format === 'csv' ? 'text/csv' : 'application/json';
    if (!response.ok || !response.headers.get('Content-Type')?.includes(type)) fail('The examination could not be exported. Please check your access and try again.');
    downloadFile(await response.blob(), filename, type);
}
