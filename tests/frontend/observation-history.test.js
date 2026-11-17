import assert from 'node:assert/strict';
import {test} from 'node:test';
import {createObservationHistory, snapshotObservation, sameObservation} from '../../resources/js/observationHistory.js';

test('history snapshots are isolated and decimal fields compare across server reloads', () => {
    const observation = {id: 1, number: 2, type: 'Scar', region: 'Arm', x: '0.500000', y: '0.400000', length_mm: '12.00'};
    const copy = snapshotObservation(observation);
    observation.region = 'Edited';
    assert.equal(copy.region, 'Arm');
    assert.ok(sameObservation(copy, {...copy, x: 0.5, length_mm: 12}));
    assert.ok(!sameObservation(copy, {...copy, region: 'Other'}));
});

test('create/edit/delete undo and redo move only after successful acknowledgement', () => {
    const history = createObservationHistory();
    const original = {id: 1, number: 1, type: 'Scar', label: 'Before'};
    const edited = {...original, label: 'After'};
    history.record(null, original);
    history.record(original, edited);
    history.record(edited, null);
    assert.equal(history.peek('undo').before.label, 'After');
    assert.equal(history.peek('undo').after, null);
    assert.equal(history.peek('undo').after, null, 'a failed request leaves history unchanged');
    history.accept('undo');
    assert.equal(history.peek('undo').before.label, 'Before');
    history.accept('undo');
    assert.equal(history.peek('undo').before, null);
    history.accept('undo');
    assert.equal(history.peek('undo'), null);
    assert.equal(history.peek('redo').after.label, 'Before');
    history.accept('redo');
    assert.equal(history.peek('redo').after.label, 'After');
});

test('new changes clear redo, no-op saves preserve it, and history is bounded and clearable', () => {
    const history = createObservationHistory(2);
    const row = {number: 1, type: 'Scar'};
    history.record(null, row);
    history.record(row, {...row, label: 'A'});
    history.record({...row, label: 'A'}, {...row, label: 'B'});
    history.accept('undo');
    history.record(row, row);
    assert.ok(history.peek('redo'));
    history.record(row, {...row, label: 'New branch'});
    assert.equal(history.peek('redo'), null);
    history.accept('undo');history.accept('undo');
    assert.equal(history.peek('undo'), null);
    history.clear();
    assert.equal(history.peek('redo'), null);
});
