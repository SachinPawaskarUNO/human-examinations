<script setup>
import AnatomicalViews from './AnatomicalViews.vue';
import RegionCombobox from './RegionCombobox.vue';
import ObservationHistoryControls from './ObservationHistoryControls.vue';
import ExaminationTransferControls from './ExaminationTransferControls.vue';
import {createExaminationDocument, parseExaminationDocument, examinationCsv, downloadFile} from '../examinationTransfer.js';
import {getObservationLocation} from '../observationLocations.js';
import {observationStyle} from '../observationTypes.js';
import {bodyRegions, suggestRegion} from '../observationRegions.js';
import {sketchGroups, sketches} from '../sketches.js';
import {renderSketchPng} from '../sketchPng.js';
import {createObservationHistory, observationFields, sameObservation} from '../observationHistory.js';

const props = defineProps({
    // Observation types for the Type dropdown.
    types: {type: Array, default: () => []},
    // The host handles persistence through save-observation/delete-observation events.
    persistentObservations: {type: Boolean, default: false},
    referenceLocked: {type: Boolean, default: false},
    // True when the `person_name` config is 'typed', so users can enter names.
    personNameEditable: {type: Boolean, default: false},
    transferLimits: {type: Object, default: () => ({maxFileBytes: 10485760, maxObservations: 10000})},
    features: {type: Object, default: () => ({})},
    markerColors: {type: Object, default: () => ({})},
    workspaceDefaults: {type: Object, default: () => ({})},
});

const emit = defineEmits(['new-examination', 'save-observation', 'delete-observation', 'restore-observation', 'import-examination', 'export-examination']);

import {computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, useId, watch} from 'vue';

const workspaceInstance = getCurrentInstance();
const workspaceRoot = ref(null);

const examination = defineModel('examination', {
    type: Object,
    default: () => ({
        reference: null,
        examiner: null,
        date: null,
        notes: null,
    }),
});

const observations = defineModel('observations', {
    type: Array,
    default: () => [],
});

// Tab for what information the side panel shows: 'examination' or 'observations'.
const tab = ref('observations');

// "New Examination" dialog: collects details, then emits them for the page to save.
const newExaminationDialog = ref(null);
const newExamination = ref(null);

function openNewExamination() {
    newExamination.value = {
        reference: null,
        examiner: null,
        date: new Date().toLocaleDateString('en-CA'),
        ...(props.personNameEditable ? {person_name: null} : {}),
    };
    newExaminationDialog.value.showModal();
}

function closeNewExamination() {
    newExaminationDialog.value.close();
}

function submitNewExamination() {
    emit(
        'new-examination',
        Object.fromEntries(
            Object.entries(newExamination.value).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        ),
    );
    closeNewExamination();
}

function updateExamination(key, value) {
    if (transferPending.value) return;
    examination.value = {
        ...examination.value,
        [key]: value === '' ? null : value,
    };
}

const FIELDS = observationFields;
const observationPending = ref(false);
const transferPending = ref(false);
const workspacePending = computed(() => observationPending.value || transferPending.value);
const importEnabled = computed(() => props.features.import !== false
    && (!props.persistentObservations || Boolean(workspaceInstance.vnode.props?.onImportExamination)));
const exportEnabled = computed(() => props.features.export !== false
    && (!props.persistentObservations || Boolean(examination.value.id && workspaceInstance.vnode.props?.onExportExamination)));
const observationErrors = ref({});
const selectedNumber = ref(null);
function defaultView() {
    const requested = props.workspaceDefaults.default_view;
    return sketchGroups.find(group => group.id === requested)?.views[0].id
        ?? (sketches.some(sketch => sketch.id === requested) ? requested : 'body-front');
}

function defaultMode() {
    return props.features.density !== false && props.workspaceDefaults.default_mode === 'density' ? 'density' : 'pins';
}

const activeView = ref(defaultView());
const mapMode = ref(defaultMode());
const anatomicalViews = ref(null);
const transferControls = ref(null);
const observationForm = ref(null);
const canEditObservations = computed(
    () => !props.persistentObservations || Boolean(examination.value.id),
);

// The observation being added or edited.
const draft = ref(null);
const history = createObservationHistory();
const undoChange = ref(null);
const redoChange = ref(null);
const historyNotice = ref('');
const historyEnabled = computed(() => props.features.undo_redo !== false);
const historyReady = computed(() => historyEnabled.value && canEditObservations.value && !workspacePending.value);
const canUndo = computed(() => canReplayHistory(undoChange.value));
const canRedo = computed(() => canReplayHistory(redoChange.value));
let writeToken = 0;

function canReplayHistory(change) {
    if (!historyReady.value || !change) return false;
    if (!draft.value) return true;

    // Replaying a removal of a different observation leaves this draft intact.
    return change.before !== null && change.after === null
        && change.before.number !== draft.value.number;
}

function syncHistory() {
    undoChange.value = history.peek('undo');
    redoChange.value = history.peek('redo');
}

function rememberChange(before, after) {
    if (!historyEnabled.value) return;
    history.record(before, after);
    syncHistory();
    historyNotice.value = '';
}

function findObservation(row) {
    if (!row) return null;
    return observations.value.find((item) => row.id != null ? item.id === row.id : item.number === row.number) ?? null;
}

function resetHistory(message = '') {
    history.clear();
    syncHistory();
    historyNotice.value = message;
}

watch([() => examination.value?.id, () => props.persistentObservations], () => {
    writeToken++;
    observationPending.value = false;
    observationErrors.value = {};
    draft.value = null;
    selectedNumber.value = null;
    resetHistory();
});

watch(historyEnabled, (enabled) => {
    if (!enabled) resetHistory();
});

async function replayHistory(direction) {
    const change = history.peek(direction);
    if (!canReplayHistory(change)) return;
    const expected = direction === 'undo' ? change.after : change.before;
    const target = direction === 'undo' ? change.before : change.after;
    if (!sameObservation(findObservation(expected ?? target), expected)) {
        resetHistory('The observation changed outside this history. History has been cleared.');
        return;
    }
    const finished = () => {
        if (!historyEnabled.value) return;
        if (!sameObservation(findObservation(expected ?? target), target)) {
            resetHistory('The observation list differs from the expected result. History has been cleared.');
            return;
        }
        history.accept(direction);
        syncHistory();
        if (!draft.value) {
            selectedNumber.value = target?.number ?? null;
            mapMode.value = 'pins';
            if (target?.sketch_id) activeView.value = target.sketch_id;
        }
        historyNotice.value = direction === 'undo' ? 'Observation change undone.' : 'Observation change redone.';
    };
    if (props.persistentObservations) {
        if (!target) persistObservation('delete-observation', expected, finished);
        else if (!expected) persistObservation('restore-observation', target, finished);
        else persistObservation('save-observation', {id: target.id, ...Object.fromEntries(FIELDS.map((f) => [f, target[f]]))}, finished);
    } else {
        observationPending.value = true;
        const token = writeToken;
        const current = findObservation(expected ?? target);
        if (!target) observations.value = observations.value.filter((item) => item !== current);
        else if (!expected) observations.value = [...observations.value, {...target}].sort((a, b) => a.number - b.number);
        else observations.value = observations.value.map((item) => item === current ? {...item, ...target} : item);
        await nextTick();
        if (token !== writeToken) return;
        finished();
        observationPending.value = false;
    }
}

function historyKeyboard(event) {
    const target = event.target instanceof Element ? event.target : document.activeElement;
    if (!(event.ctrlKey || event.metaKey) || event.altKey || event.defaultPrevented || target?.closest('input, textarea, select, [contenteditable], dialog')) return;
    const owner = target?.closest('.he-workspace');
    if (owner ? owner !== workspaceRoot.value : document.querySelectorAll('.he-workspace').length !== 1) return;
    const key = event.key.toLowerCase();
    const direction = key === 'z' ? (event.shiftKey ? 'redo' : 'undo') : key === 'y' ? 'redo' : null;
    if (!direction || !canReplayHistory(history.peek(direction))) return;
    event.preventDefault();
    replayHistory(direction);
}
onMounted(() => window.addEventListener('keydown', historyKeyboard));
onBeforeUnmount(() => window.removeEventListener('keydown', historyKeyboard));
const regionEdited = ref(false);
const regionInputId = `${useId()}-region`;
const regionHelpId = `${regionInputId}-help`;
const regionChoices = Object.values(bodyRegions);
const savedLocation = computed(() => draft.value?.number != null
    ? getObservationLocation(observations.value.find((item) => item.number === draft.value.number))
    : null,
);
const placementEnabled = computed(() => canEditObservations.value && !workspacePending.value);

async function importExamination(file, complete) {
    if (!importEnabled.value) { complete({_form: 'Import is unavailable.'}); return; }
    if (observationPending.value || draft.value) { complete({_form: 'Save or cancel the current observation first.'}); return; }
    if (props.persistentObservations) { emit('import-examination', file, complete); return; }
    try {
        const document = parseExaminationDocument(JSON.parse((await file.text()).replace(/^\uFEFF/, '')), {types: props.types, maxObservations: props.transferLimits.maxObservations});
        const {person_name: personName, ...details} = document.examination;
        examination.value = props.personNameEditable ? {...details, person_name: personName ?? null} : details;
        observations.value = document.observations.map(row => ({...row}));
        writeToken++; observationErrors.value = {}; draft.value = null; selectedNumber.value = null;
        resetHistory(); mapMode.value = defaultMode(); activeView.value = defaultView();
        await nextTick(); complete();
    } catch (error) { complete({_form: error.message}); }
}

function exportExamination(format, complete) {
    if (!exportEnabled.value) { complete({_form: 'Export is unavailable.'}); return; }
    if (observationPending.value || draft.value) { complete({_form: 'Save or cancel the current observation first.'}); return; }
    if (props.persistentObservations) { emit('export-examination', format, complete); return; }
    try {
        const document = createExaminationDocument(examination.value, observations.value);
        downloadFile(format === 'csv' ? examinationCsv(document) : JSON.stringify(document, null, 2) + '\n', `human-examination.${format}`, format === 'csv' ? 'text/csv;charset=utf-8' : 'application/json');
        complete();
    } catch (error) { complete({_form: error.message}); }
}

async function exportSketchPng(sketch, complete) {
    if (!exportEnabled.value) { complete({_form: 'Export is unavailable.'}); return; }
    if (observationPending.value || draft.value) { complete({_form: 'Save or cancel the current observation first.'}); return; }
    try {
        const png = await renderSketchPng(sketch, observations.value, {examination: examination.value, markerColors: props.markerColors});
        downloadFile(png, `human-examination-${sketch.id}.png`, 'image/png');
        complete();
    } catch (error) { complete({_form: error.message}); }
}

const draftLocation = computed(() => {
    const location = getObservationLocation(draft.value);
    const saved = savedLocation.value;
    if (!location || (saved && saved.sketch_id === location.sketch_id && saved.x === location.x && saved.y === location.y)) {
        return null;
    }
    return {...location, number: draft.value.number, type: draft.value.type};
});
const draftLocationLabel = computed(() => sketches.find((sketch) => sketch.id === draft.value?.sketch_id)?.label
    ?? draft.value?.sketch_id,
);

function startNew() {
    if (workspacePending.value || !canEditObservations.value) return;
    mapMode.value = 'pins';
    observationErrors.value = {};
    selectedNumber.value = null;
    regionEdited.value = false;
    tab.value = 'observations';
    draft.value = {
        id: null,
        number: null,
        type: null,
        label: null,
        region: null,
        description: null,
        length_mm: null,
        width_mm: null,
        sketch_id: null,
        x: null,
        y: null,
    };
}

function edit(observation) {
    if (workspacePending.value) return;
    mapMode.value = 'pins';
    observationErrors.value = {};
    selectedNumber.value = observation.number;
    // Saved values, including intentionally blank regions, belong to the user. A region that
    // still matches the marker's suggestion follows the marker if it is moved.
    regionEdited.value = !observation.region || observation.region !== suggestRegion(observation);
    tab.value = 'observations';
    if (sketches.some((sketch) => sketch.id === observation.sketch_id)) {
        activeView.value = observation.sketch_id;
    }
    draft.value = {
        id: observation.id,
        number: observation.number,
        ...Object.fromEntries(FIELDS.map((f) => [f, observation[f] ?? null])),
    };
}

function selectObservation(number) {
    if (workspacePending.value) return;
    const observation = observations.value.find((item) => item.number === number);
    if (!observation) return;
    edit(observation);
    nextTick(() => observationForm.value?.querySelector('select, input, textarea')?.focus());
}

function beginPlacement(observation) {
    if (workspacePending.value || getObservationLocation(observation)) return;
    edit(observation);
    nextTick(() => anatomicalViews.value?.$el.querySelector('.he-diagram-map')?.focus());
}

function placeObservation(location) {
    if (!placementEnabled.value) return;
    const point = getObservationLocation(location);
    if (!point) return;
    if (!draft.value) startNew();
    draft.value = {...draft.value, ...point};
    if (!regionEdited.value) draft.value.region = suggestRegion(point);
    activeView.value = point.sketch_id;
    tab.value = 'observations';
}

function cancel() {
    draft.value = null;
    observationErrors.value = {};
}

function persistObservation(event, payload, onSuccess) {
    const handler = {'save-observation': 'onSaveObservation', 'delete-observation': 'onDeleteObservation', 'restore-observation': 'onRestoreObservation'}[event];
    if (!workspaceInstance.vnode.props?.[handler]) {
        observationErrors.value = {_form: 'This action is unavailable in this application.'};
        return;
    }
    const token = ++writeToken;
    observationPending.value = true;
    observationErrors.value = {};
    historyNotice.value = '';
    let completed = false;

    emit(event, payload, async (errors = {}) => {
        if (completed || token !== writeToken) return;
        completed = true;
        observationErrors.value = errors;
        try {
            if (Object.keys(errors).length === 0) {
                await nextTick();
                if (token === writeToken) onSuccess();
            }
        } finally {
            if (token === writeToken) observationPending.value = false;
        }
    });
}

// An observation can only be saved once it has a type.
const missingType = computed(
    () => !draft.value || !String(draft.value.type ?? '').trim(),
);

async function save() {
    if (missingType.value || workspacePending.value || !canEditObservations.value) {
        return;
    }

    const values = Object.fromEntries(
        FIELDS.map((f) => [f, draft.value[f] === '' ? null : draft.value[f]]),
    );
    const before = findObservation(draft.value);
    const previousNumbers = new Set(observations.value.map((item) => item.number));

    if (props.persistentObservations) {
        persistObservation('save-observation', {id: draft.value.id, ...values}, () => {
            const candidates = observations.value.filter((item) => !previousNumbers.has(item.number));
            const after = before ? findObservation(before) : candidates.length === 1 ? candidates[0] : null;
            if (after) rememberChange(before, after);
            else resetHistory('Saved. Undo is unavailable because the observation list was not refreshed.');
            draft.value = null;
        });
        return;
    }

    if (draft.value.number === null) {
        const number =
            Math.max(0, ...observations.value.map((o) => o.number)) + 1;

        observations.value = [
            ...observations.value,
            {
                number,
                description: null,
                sketch_id: null,
                x: null,
                y: null,
                ...values,
            },
        ];
        selectedNumber.value = number;
    } else {
        observations.value = observations.value.map((o) =>
            o.number === draft.value.number ? {...o, ...values} : o,
        );
    }

    const token = writeToken;
    observationPending.value = true;
    await nextTick();
    if (token !== writeToken) return;
    rememberChange(before, observations.value.find((item) => item.number === (draft.value.number ?? selectedNumber.value)));
    draft.value = null;
    observationPending.value = false;
}

function removeObservation(number) {
    if (workspacePending.value) return;
    const before = observations.value.find((item) => item.number === number);
    if (!before) return;
    if (props.persistentObservations) {
        const observation = observations.value.find((item) => item.number === number);
        if (!observation) return;
        persistObservation('delete-observation', observation, () => {
            if (!findObservation(before)) rememberChange(before, null);
            else resetHistory('Undo is unavailable because the observation list was not refreshed.');
            if (draft.value?.number === number) cancel();
            if (selectedNumber.value === number) selectedNumber.value = null;
        });
        return;
    }
    observations.value = observations.value.filter((o) => o.number !== number);
    rememberChange(before, null);
    if (selectedNumber.value === number) selectedNumber.value = null;

    if (draft.value?.number === number) {
        draft.value = null;
    }
}

function details(o) {
    const size =
        o.length_mm || o.width_mm
            ? `${o.length_mm ?? '–'} × ${o.width_mm ?? '–'} mm`
            : null;

    return [o.label ? o.type : null, o.region, size]
        .filter(Boolean)
        .join(' · ');
}
</script>

<template>
    <div ref="workspaceRoot" class="he-workspace">
        <div class="he-toolbar">
            <div v-if="examination.id || examination.reference || examination.person_name || examination.examiner || examination.date" class="he-current">
                <strong>{{
                        examination.reference || (examination.id ? `Examination #${examination.id}` : 'Examination')
                    }}</strong>
                <span
                    v-if="examination.person_name || examination.examiner || examination.date"
                    class="he-muted he-small"
                >
                    {{
                        [examination.person_name, examination.examiner, examination.date]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </span>
            </div>

            <ObservationHistoryControls v-if="$slots.default && historyEnabled" :undo-disabled="!canUndo" :redo-disabled="!canRedo" :editing="Boolean(draft)" @undo="replayHistory('undo')" @redo="replayHistory('redo')"/>
            <div class="he-toolbar-actions">
                <ExaminationTransferControls
                    ref="transferControls"
                    :disabled="workspacePending || Boolean(draft)"
                    :import-enabled="importEnabled"
                    :export-enabled="exportEnabled"
                    :persistent="persistentObservations"
                    :types="types"
                    :max-file-bytes="transferLimits.maxFileBytes"
                    :max-observations="transferLimits.maxObservations"
                    @busy="transferPending = $event"
                    @import="importExamination"
                    @export="exportExamination"
                    @export-sketch="exportSketchPng"
                />
                <button
                    type="button"
                    class="he-button he-button--primary"
                    :disabled="workspacePending"
                    @click="openNewExamination"
                >
                    <svg class="he-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12h14M12 5v14"/>
                    </svg>
                    New Examination
                </button>
            </div>
        </div>
        <p v-if="historyNotice" class="he-muted he-small" role="status">{{ historyNotice }}</p>

        <dialog
            ref="newExaminationDialog"
            class="he-dialog"
            aria-labelledby="he-new-examination-title"
        >
            <form
                v-if="newExamination"
                class="he-dialog__form"
                @submit.prevent="submitNewExamination"
            >
                <h2 id="he-new-examination-title" class="he-dialog__title">
                    New examination
                </h2>

                <label v-if="!referenceLocked" class="he-field">
                    <span>Reference</span>
                    <input
                        v-model="newExamination.reference"
                        class="he-input"
                        placeholder="e.g. EX-2026-001"
                        autofocus
                    />
                </label>

                <label class="he-field">
                    <span>Examiner</span>
                    <input
                        v-model="newExamination.examiner"
                        class="he-input"
                        placeholder="Name or initials"
                        :autofocus="referenceLocked"
                    />
                </label>

                <label v-if="personNameEditable" class="he-field">
                    <span>Person</span>
                    <input v-model="newExamination.person_name" class="he-input" placeholder="Name, if known"/>
                </label>

                <label class="he-field">
                    <span>Date</span>
                    <input
                        v-model="newExamination.date"
                        class="he-input"
                        type="date"
                    />
                </label>

                <div class="he-form__actions">
                    <button
                        type="button"
                        class="he-button he-button--outline"
                        @click="closeNewExamination"
                    >
                        Cancel
                    </button>
                    <button type="submit" class="he-button he-button--primary">
                        Create
                    </button>
                </div>
            </form>
        </dialog>

        <div class="he-body">
            <section class="he-card he-area" :class="{ 'he-area--anatomical': !$slots.default }" aria-label="Workspace area">
                <slot>
                    <AnatomicalViews
                        :marker-colors="markerColors"
                        :density-enabled="features.density !== false"
                        :initial-diagram-height="Number.isFinite(workspaceDefaults.diagram_height) ? workspaceDefaults.diagram_height : undefined"
                        ref="anatomicalViews"
                        v-model:view="activeView"
                        v-model:mode="mapMode"
                        :observations="observations"
                        :selected-number="selectedNumber"
                        :draft-location="draftLocation"
                        :editing-number="savedLocation ? draft.number : null"
                        :placement-enabled="placementEnabled"
                        :disabled="workspacePending"
                        :export-enabled="exportEnabled"
                        :export-disabled="Boolean(draft)"
                        @place="placeObservation"
                        @select="selectObservation"
                        @export="transferControls?.exportSketch($event)"
                    >
                        <template v-if="historyEnabled" #actions>
                            <ObservationHistoryControls :undo-disabled="!canUndo" :redo-disabled="!canRedo" :editing="Boolean(draft)" @undo="replayHistory('undo')" @redo="replayHistory('redo')"/>
                        </template>
                    </AnatomicalViews>
                </slot>
            </section>

            <aside v-if="$slots.aside" class="he-card he-aside">
                <slot name="aside"/>
            </aside>

            <section
                class="he-card he-panel"
                aria-label="Examination information"
            >
                <div class="he-card__header">
                    <div
                        class="he-tabs"
                        role="tablist"
                        aria-label="Information shown"
                    >
                        <button
                            type="button"
                            role="tab"
                            class="he-tab"
                            :class="{
                                'he-tab--active': tab === 'observations',
                            }"
                            :aria-selected="tab === 'observations'"
                            @click="tab = 'observations'"
                        >
                            Observations
                        </button>
                        <button
                            type="button"
                            role="tab"
                            class="he-tab"
                            :class="{ 'he-tab--active': tab === 'examination' }"
                            :aria-selected="tab === 'examination'"
                            @click="tab = 'examination'"
                        >
                            Examination
                        </button>
                    </div>

                    <button
                        v-if="tab === 'observations'"
                        type="button"
                        class="he-button he-button--primary he-button--icon"
                        aria-label="Add observation"
                        title="Add observation"
                        :disabled="workspacePending || !canEditObservations"
                        @click="startNew"
                    >
                        <svg
                            class="he-icon"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M5 12h14M12 5v14"/>
                        </svg>
                    </button>
                </div>

                <div v-if="tab === 'examination'" class="he-details">
                    <label class="he-field">
                        <span>Reference</span>
                        <input
                            class="he-input"
                            :class="{ 'he-input--locked': referenceLocked }"
                            :value="examination.reference"
                            :placeholder="referenceLocked ? 'Generated automatically' : 'e.g. EX-2026-001'"
                            :readonly="referenceLocked"
                            :title="referenceLocked ? 'Generated automatically' : null"
                            :disabled="transferPending"
                            @change="
                                updateExamination(
                                    'reference',
                                    $event.target.value,
                                )
                            "
                        />
                    </label>

                    <label class="he-field">
                        <span>Examiner</span>
                        <input
                            class="he-input"
                            :value="examination.examiner"
                            :disabled="transferPending"
                            placeholder="Name or initials"
                            @change="
                                updateExamination(
                                    'examiner',
                                    $event.target.value,
                                )
                            "
                        />
                    </label>

                    <label v-if="personNameEditable || examination.person_name" class="he-field">
                        <span>Person</span>
                        <input
                            class="he-input"
                            :class="{ 'he-input--locked': !personNameEditable }"
                            :value="examination.person_name"
                            :readonly="!personNameEditable"
                            :title="personNameEditable ? null : 'Provided by the application'"
                            :disabled="transferPending"
                            placeholder="Name, if known"
                            @change="updateExamination('person_name', $event.target.value)"
                        />
                    </label>

                    <label class="he-field">
                        <span>Date</span>
                        <input
                            class="he-input"
                            type="date"
                            :value="examination.date"
                            :disabled="transferPending"
                            @change="
                                updateExamination('date', $event.target.value)
                            "
                        />
                    </label>

                    <label class="he-field">
                        <span>Notes</span>
                        <textarea
                            class="he-input he-textarea"
                            rows="6"
                            :value="examination.notes"
                            :disabled="transferPending"
                            placeholder="General findings and context…"
                            @change="
                                updateExamination('notes', $event.target.value)
                            "
                        ></textarea>
                    </label>
                </div>

                <template v-else>
                    <p v-if="!canEditObservations" class="he-observation-notice he-muted he-small">
                        Create an examination before adding observations.
                    </p>
                    <p v-if="observationPending" class="he-observation-notice he-muted he-small" role="status">
                        Saving changes…
                    </p>
                    <div v-if="Object.keys(observationErrors).length" class="he-observation-errors" role="alert">
                        <p>Changes were not saved. Please review:</p>
                        <ul>
                            <li v-for="(message, field) in observationErrors" :key="field">{{ message }}</li>
                        </ul>
                    </div>
                    <form v-if="draft" ref="observationForm" class="he-form" @submit.prevent="save">
                        <fieldset class="he-observation-fields" :disabled="workspacePending">
                            <p class="he-form__title">
                                {{
                                    draft.number === null
                                        ? 'New observation'
                                        : `Edit observation ${draft.number}`
                                }}
                            </p>

                            <label class="he-field">
                                <span
                                >Type
                                    <abbr class="he-required" title="required"
                                    >*</abbr
                                    ></span
                                >
                                <select
                                    v-if="types.length"
                                    v-model="draft.type"
                                    name="type"
                                    :aria-invalid="Boolean(observationErrors.type)"
                                    class="he-input"
                                    required
                                >
                                    <option :value="null">Select type</option>
                                    <option
                                        v-for="type in types"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ type }}
                                    </option>
                                </select>
                                <input
                                    v-else
                                    v-model="draft.type"
                                    name="type"
                                    :aria-invalid="Boolean(observationErrors.type)"
                                    class="he-input"
                                    required
                                />
                            </label>

                            <label class="he-field">
                                <span>Label</span>
                                <input
                                    v-model="draft.label"
                                    name="label"
                                    :aria-invalid="Boolean(observationErrors.label)"
                                    class="he-input"
                                    placeholder="e.g. Linear scar"
                                />
                            </label>

                            <div class="he-field">
                                <label :for="regionInputId" class="he-field-label">Region</label>
                                <RegionCombobox
                                    :id="regionInputId"
                                    v-model="draft.region"
                                    name="region"
                                    :options="regionChoices"
                                    :disabled="workspacePending"
                                    :aria-describedby="regionHelpId"
                                    :aria-invalid="Boolean(observationErrors.region)"
                                    maxlength="255"
                                    placeholder="e.g. Right upper arm"
                                    @edit="regionEdited = true"
                                />
                                <small :id="regionHelpId" class="he-muted he-small">
                                    <template v-if="draftLocation && !regionEdited">
                                        {{
                                            draft.region ? 'Suggested from marker. Edit to refine the location.' : 'No region suggested here. Enter a region if needed.'
                                        }}
                                    </template>
                                    <template v-else>Choose a standard region or enter your own.</template>
                                </small>
                            </div>

                            <label class="he-field">
                                <span>Description</span>
                                <textarea
                                    v-model="draft.description"
                                    name="description"
                                    :aria-invalid="Boolean(observationErrors.description)"
                                    class="he-input he-textarea"
                                    rows="3"
                                ></textarea>
                            </label>

                            <div class="he-field-row">
                                <label class="he-field">
                                    <span>Length (mm)</span>
                                    <input
                                        v-model="draft.length_mm"
                                        name="length_mm"
                                        :aria-invalid="Boolean(observationErrors.length_mm)"
                                        class="he-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </label>

                                <label class="he-field">
                                    <span>Width (mm)</span>
                                    <input
                                        v-model="draft.width_mm"
                                        name="width_mm"
                                        :aria-invalid="Boolean(observationErrors.width_mm)"
                                        class="he-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </label>
                            </div>

                            <p class="he-muted he-small">
                                <template v-if="getObservationLocation(draft)">
                                    Marker: {{ draftLocationLabel }}.
                                    <template v-if="draftLocation">{{ savedLocation ? 'Moved. ' : '' }}Save to keep this location.</template>
                                    <template v-else>Click a sketch to move it.</template>
                                </template>
                                <template v-else>Click a sketch to choose a marker location (optional).</template>
                            </p>

                            <div class="he-form__actions">
                                <button
                                    type="button"
                                    class="he-button he-button--outline"
                                    @click="cancel"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="he-button he-button--primary"
                                    :disabled="missingType || workspacePending"
                                    :title="
                                        missingType ? 'Choose a type to save' : null
                                    "
                                >
                                    Save
                                </button>
                            </div>
                        </fieldset>
                    </form>

                    <ul v-if="observations.length" class="he-list">
                        <li
                            v-for="o in observations"
                            :key="o.number"
                            class="he-item"
                            :class="{
                                'he-item--editing': draft?.number === o.number,
                                'he-item--selected': selectedNumber === o.number,
                            }"
                        >
                            <span class="he-badge" :style="observationStyle(o.type, markerColors)">{{ o.number }}</span>

                            <button
                                type="button"
                                class="he-item__text he-item__select"
                                :aria-label="`Select observation ${o.number}`"
                                :aria-pressed="selectedNumber === o.number"
                                :disabled="workspacePending"
                                @click="selectObservation(o.number)"
                            >
                                <strong>{{
                                        o.label ||
                                        o.type ||
                                        `Observation ${o.number}`
                                    }}</strong>
                                <small v-if="details(o)">{{
                                        details(o)
                                    }}</small>
                            </button>

                            <button
                                v-if="!getObservationLocation(o)"
                                type="button"
                                class="he-button he-button--ghost he-button--icon-sm"
                                :aria-label="`Place observation ${o.number}`"
                                title="Place marker on a sketch"
                                :disabled="workspacePending"
                                @click="beginPlacement(o)"
                            >
                                <svg class="he-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                            </button>

                            <button
                                type="button"
                                class="he-button he-button--ghost he-button--icon-sm"
                                :aria-label="`Edit observation ${o.number}`"
                                title="Edit observation"
                                :disabled="workspacePending"
                                @click="selectObservation(o.number)"
                            >
                                <svg
                                    class="he-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"
                                    />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="he-button he-button--ghost he-button--icon-sm"
                                :aria-label="`Remove observation ${o.number}`"
                                title="Remove observation"
                                :disabled="workspacePending"
                                @click="removeObservation(o.number)"
                            >
                                <svg
                                    class="he-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M18 6 6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </li>
                    </ul>

                    <div v-else-if="!draft" class="he-empty">
                        <div class="he-empty__icon">
                            <svg
                                class="he-icon he-icon--lg"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"
                                />
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <path d="M9 12h6M9 16h4"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="he-empty__title">No observations yet</h3>
                            <p class="he-muted he-small">
                                {{
                                    canEditObservations ? 'Click a sketch or use + to record the first observation.' : 'Create an examination to start recording findings.'
                                }}
                            </p>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>

<style scoped>
/*
 * Uses the host app's shadcn-vue theme variables when present, falling back to shadcn's
 * neutral defaults. Override any --he-* variable to restyle the workspace.
 */
.he-workspace {
    --he-background: var(--background, #fff);
    --he-foreground: var(--foreground, #0a0a0a);
    --he-card: var(--card, #fff);
    --he-muted: var(--muted, #f5f5f5);
    --he-muted-foreground: var(--muted-foreground, #737373);
    --he-border: var(--border, #e5e5e5);
    --he-input: var(--input, #e5e5e5);
    --he-primary: var(--primary, #171717);
    --he-primary-foreground: var(--primary-foreground, #fafafa);
    --he-ring: var(--ring, #a3a3a3);

    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    color: var(--he-foreground);
}

.he-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

/* Keeps the button on the right whether or not an examination is shown. */
.he-toolbar-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: .5rem;
    margin-left: auto;
}

.he-current {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.he-current strong {
    font-size: 1rem;
    font-weight: 600;
}

/* Cards */
.he-card {
    border: 1px solid var(--he-border);
    border-radius: 0.5rem;
    background: var(--he-card);
}

.he-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--he-border);
}

.he-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

/* Layout */
.he-body {
    display: flex;
    gap: 1.25rem;
}

.he-area {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: max(32rem, 80vh);
}

.he-area--anatomical {
    display: block;
    min-height: 0;
}

.he-aside {
    width: 20rem;
    padding: 1rem;
}

.he-panel {
    flex-shrink: 0;
    align-self: flex-start;
    width: 24rem;
}

/* Examination / Observations toggle */
.he-tabs {
    display: inline-flex;
    padding: 0.1875rem;
    border-radius: 0.5rem;
    background: var(--he-muted);
}

.he-tab {
    padding: 0.25rem 0.625rem;
    border: 0;
    border-radius: 0.375rem;
    background: none;
    color: var(--he-muted-foreground);
    font: inherit;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
}

.he-tab:focus-visible {
    outline: 2px solid var(--he-ring);
    outline-offset: 1px;
}

.he-tab--active {
    background: var(--he-background);
    color: var(--he-foreground);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
}

.he-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
}

@media (max-width: 1023px) {
    .he-body {
        flex-direction: column;
    }

    .he-aside,
    .he-panel {
        width: auto;
        align-self: stretch;
    }
}

/* Text */
.he-muted {
    margin: 0;
    color: var(--he-muted-foreground);
}

.he-small {
    font-size: 0.875rem;
}

/* Buttons */
.he-button {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    height: 2.25rem;
    padding: 0 0.75rem;
    border: 1px solid transparent;
    border-radius: 0.375rem;
    background: none;
    color: inherit;
    font: inherit;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 150ms;
}

.he-button:focus-visible,
.he-input:focus-visible {
    outline: 2px solid var(--he-ring);
    outline-offset: 2px;
}

.he-button--primary {
    background: var(--he-primary);
    color: var(--he-primary-foreground);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
}

.he-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.he-button--primary:hover:not(:disabled) {
    background: color-mix(in oklab, var(--he-primary) 90%, transparent);
}

.he-button--outline {
    border-color: var(--he-input);
    background: var(--he-background);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
}

.he-button--outline:hover {
    background: var(--he-muted);
}

.he-button--ghost {
    color: var(--he-muted-foreground);
}

.he-button--ghost:hover {
    background: var(--he-muted);
    color: var(--he-foreground);
}

.he-button--icon {
    width: 2.25rem;
    padding: 0;
}

.he-button--icon-sm {
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
}

.he-icon {
    width: 1rem;
    height: 1rem;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.he-icon--lg {
    width: 1.75rem;
    height: 1.75rem;
}

/* Observation list */
.he-list {
    max-height: max(16rem, 50vh);
    margin: 0;
    padding: 0;
    overflow-y: auto;
    list-style: none;
}

.he-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.75rem 0.625rem 1rem;
}

.he-item + .he-item {
    border-top: 1px solid var(--he-border);
}

.he-item--editing,
.he-item--selected {
    background: var(--he-muted);
}

.he-badge {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 999px;
    background: var(--he-observation-color);
    color: var(--he-observation-foreground);
    font-size: 0.75rem;
    font-weight: 600;
}

.he-item__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.he-item__select {
    align-items: flex-start;
    padding: 0;
    border: 0;
    background: none;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.he-item__select:focus-visible {
    outline: 2px solid var(--he-ring);
    outline-offset: 3px;
}

.he-item__text strong,
.he-item__text small {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.he-item__text strong {
    font-size: 0.875rem;
    font-weight: 500;
}

.he-item__text small {
    color: var(--he-muted-foreground);
    font-size: 0.75rem;
}

/* Add / edit form */
.he-form {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    padding: 1rem;
    border-bottom: 1px solid var(--he-border);
    background: color-mix(in oklab, var(--he-muted) 40%, transparent);
}

.he-observation-fields {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.he-observation-notice,
.he-observation-errors {
    padding: 0.75rem 1rem;
}

.he-observation-errors {
    color: #b91c1c;
    font-size: 0.875rem;
}

.he-observation-errors p,
.he-observation-errors ul {
    margin: 0;
}

.he-observation-errors ul {
    padding-left: 1.25rem;
}

.he-form__title {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 600;
}

.he-form__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 0.25rem;
}

.he-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.he-field > span,
.he-field-label {
    color: var(--he-muted-foreground);
    font-size: 0.75rem;
    font-weight: 500;
}

.he-required {
    color: #dc2626;
    text-decoration: none;
}

.he-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.he-input {
    box-sizing: border-box;
    width: 100%;
    height: 2.25rem;
    padding: 0 0.75rem;
    border: 1px solid var(--he-input);
    border-radius: 0.375rem;
    background: var(--he-background);
    color: inherit;
    font: inherit;
    font-size: 0.875rem;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
}

.he-input--locked {
    background: var(--he-muted);
    color: var(--he-muted-foreground);
    cursor: not-allowed;
}

select.he-input {
    --he-dropdown-chevron: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23737373' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
}

select.he-input {
    appearance: none;
    padding-right: 2rem;
    background-image: var(--he-dropdown-chevron);
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
}

.he-textarea {
    height: auto;
    padding: 0.5rem 0.75rem;
    resize: vertical;
}

/* New examination dialog */
.he-dialog {
    margin: auto;
    width: min(24rem, calc(100vw - 2rem));
    padding: 0;
    border: 1px solid var(--he-border);
    border-radius: 0.5rem;
    background: var(--he-card);
    color: var(--he-foreground);
    box-shadow: 0 10px 30px rgb(0 0 0 / 0.15);
}

.he-dialog::backdrop {
    background: rgb(0 0 0 / 0.4);
}

.he-dialog__form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.25rem;
}

.he-dialog__title {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 600;
}

/* Empty state */
.he-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 2.5rem 1rem;
    text-align: center;
}

.he-empty__icon {
    padding: 0.75rem;
    border-radius: 999px;
    background: var(--he-muted);
    color: var(--he-muted-foreground);
}

.he-empty__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 500;
}
</style>
