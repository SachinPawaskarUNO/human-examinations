<script setup>
import { router } from '@inertiajs/vue3';
import HumanExaminationWorkspace from '../../../../resources/js/components/HumanExaminationWorkspace.vue';

const props = defineProps({
    observationTypes: { type: Array, default: () => [] },
    examination: { type: Object, default: null },
});

// Receives the details entered in the component's "New examination" dialog.
function createExamination(details) {
    router.post('/human-examinations/examinations', details);
}

// Saves edits from the Examination tab. The server sends back the updated examination.
function saveExamination({ reference, examiner, date, notes }) {
    if (!props.examination) {
        return;
    }

    router.patch(
        `/human-examinations/examinations/${props.examination.id}`,
        { reference, examiner, date, notes },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <main class="workbench">
        <!-- The key starts the workspace fresh (observations, open forms) for each examination. -->
        <HumanExaminationWorkspace
            :key="props.examination?.id ?? 'none'"
            :examination="props.examination ?? undefined"
            :types="observationTypes"
            @update:examination="saveExamination"
            @new-examination="createExamination"
        />
    </main>
</template>

<style scoped>
.workbench {
    max-width: 72rem;
    margin: 0 auto;
    padding: 2rem;
    font-family: system-ui, sans-serif;
    color: #0f172a;
}
</style>
