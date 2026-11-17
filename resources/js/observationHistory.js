export const observationFields = ['type', 'label', 'region', 'description', 'length_mm', 'width_mm', 'sketch_id', 'x', 'y'];
const numeric = new Set(['length_mm', 'width_mm', 'x', 'y']);

export function snapshotObservation(row) {
    if (!row) return null;
    return {id: row.id ?? null, number: Number(row.number), ...Object.fromEntries(observationFields.map((field) => {
        const value = row[field] === '' ? null : row[field] ?? null;
        return [field, value != null && numeric.has(field) ? Number(value) : value];
    }))};
}

export function sameObservation(a, b) {
    return JSON.stringify(snapshotObservation(a)) === JSON.stringify(snapshotObservation(b));
}

export function createObservationHistory(limit = 50) {
    let past = [], future = [];
    const copy = (row) => row ? {...JSON.parse(JSON.stringify(row)), ...snapshotObservation(row)} : null;
    return {
        record(before, after) {
            if (sameObservation(before, after)) return;
            past = [...past, {before: copy(before), after: copy(after)}].slice(-limit);
            future = [];
        },
        peek(direction) { return (direction === 'undo' ? past : future).at(-1) ?? null; },
        accept(direction) {
            const from = direction === 'undo' ? past : future;
            const to = direction === 'undo' ? future : past;
            if (from.length) to.push(from.pop());
        },
        clear() { past = []; future = []; },
    };
}
