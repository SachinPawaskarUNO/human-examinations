<script setup>
import {router} from '@inertiajs/vue3';
import HumanExaminationWorkspace from '../../../../resources/js/components/HumanExaminationWorkspace.vue';
import {downloadExaminationResponse} from '../../../../resources/js/examinationTransfer.js';

const props = defineProps({
    observationTypes: {type: Array, default: () => []},
    referenceLocked: {type: Boolean, default: false},
    personNameEditable: {type: Boolean, default: false},
    examination: {type: Object, default: null},
    examinationRoutes: {type: Object, required: true},
    observations: {type: Array, default: () => []},
    observationRoutes: {type: Object, required: true},
    transferLimits: {type: Object, default: undefined},
    features: {type: Object, default: undefined},
    markerColors: {type: Object, default: undefined},
    workspaceDefaults: {type: Object, default: undefined},
});

// Receives the details entered in the component's "New examination" dialog.
function createExamination(details) {
    router.post(props.examinationRoutes.store, details);
}

// Saves edits from the Examination tab. The server sends back the updated examination.
function saveExamination({reference, examiner, person_name, date, notes}) {
    if (!props.examination) {
        return;
    }

    router.patch(
        props.examinationRoutes.update,
        {reference, examiner, person_name, date, notes},
        {preserveScroll: true, preserveState: true},
    );
}

function visitObservation(method, url, data, complete, errorBag = 'observation') {
    let finished = false;
    const finish = (errors = {}) => {
        if (finished) return;
        finished = true;
        complete(errors);
    };
    const failed = () => finish({_form: 'Unable to save these changes. Please try again.'});

    if (!url) {
        failed();
        return;
    }

    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        preserveState: true,
        errorBag,
        onSuccess: () => finish(),
        onError: (errors) => finish(errors),
        onHttpException: () => {
            failed();
            return false;
        },
        onNetworkError: () => {
            failed();
            return false;
        },
        onCancel: failed,
        onFinish: () => {
            if (!finished) failed();
        },
    });
}

function saveObservation({id, ...attributes}, complete) {
    const method = id == null ? 'post' : 'patch';
    const url = id == null ? props.observationRoutes.store : props.observationRoutes.update[id];
    visitObservation(method, url, attributes, complete);
}

function deleteObservation(observation, complete) {
    visitObservation('delete', props.observationRoutes.destroy[observation.id], {}, complete);
}

function restoreObservation(observation, complete) {
    visitObservation('post', props.observationRoutes.restore?.[observation.id], {}, complete);
}

function importExamination(file, complete) {
    visitObservation('post', props.examinationRoutes.import, {file}, complete, 'examinationImport');
}

async function exportExamination(format, complete) {
    try {
        await downloadExaminationResponse(props.examinationRoutes.export?.[format], format, `human-examination-${props.examination.id}.${format}`);
        complete();
    } catch (error) { complete({_form: error.message}); }
}
</script>

<template>
    <main class="workbench">
        <!-- The key starts the workspace fresh (observations, open forms) for each examination. -->
        <HumanExaminationWorkspace
            :key="props.examination?.id ?? 'none'"
            :examination="props.examination ?? undefined"
            :types="observationTypes"
            :reference-locked="referenceLocked"
            :person-name-editable="personNameEditable"
            :observations="props.observations"
            persistent-observations
            :transfer-limits="transferLimits"
            :features="features"
            :marker-colors="markerColors"
            :workspace-defaults="workspaceDefaults"
            @update:examination="saveExamination"
            @new-examination="createExamination"
            @save-observation="saveObservation"
            @delete-observation="deleteObservation"
            @restore-observation="restoreObservation"
            @import-examination="importExamination"
            @export-examination="exportExamination"
        />
    </main>
</template>

<style scoped>
.workbench {
    max-width: 88rem;
    margin: 0 auto;
    padding: 2rem;
    font-family: system-ui, sans-serif;
    color: #0f172a;
}
</style>
