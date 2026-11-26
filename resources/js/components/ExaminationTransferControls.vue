<script setup>
import {computed, onBeforeUnmount, ref, useId} from 'vue';
import {parseExaminationDocument} from '../examinationTransfer.js';

const props = defineProps({
    disabled: Boolean, importEnabled: Boolean, exportEnabled: Boolean, persistent: Boolean,
    types: {type: Array, default: () => []},
    maxFileBytes: {type: Number, default: 10485760},
    maxObservations: {type: Number, default: 10000},
});
const emit = defineEmits(['import', 'export', 'export-sketch', 'busy']);
const input = ref(null), dialog = ref(null), menu = ref(null);
const file = ref(null), preview = ref(null), reading = ref(false), pending = ref(false);
const errors = ref([]), notice = ref('');
const titleId = `${useId()}-import-title`;
const unavailable = computed(() => props.disabled || reading.value || pending.value);
let readToken = 0, disposed = false;
onBeforeUnmount(() => { disposed = true; readToken++; });

async function choose(event) {
    const selected = event.target.files?.[0];
    event.target.value = '';
    if (!selected || unavailable.value) return;
    errors.value = []; notice.value = ''; preview.value = null; file.value = null;
    const token = ++readToken;
    reading.value = true;
    try {
        if (selected.size > props.maxFileBytes) throw new Error(`Choose a JSON file no larger than ${Math.round(props.maxFileBytes / 1024)} KiB.`);
        let parsed;
        try { parsed = JSON.parse((await selected.text()).replace(/^\uFEFF/, '')); }
        catch { throw new Error('The selected file is not valid JSON.'); }
        if (token !== readToken || disposed) return;
        preview.value = parseExaminationDocument(parsed, {types: props.types, maxObservations: props.maxObservations});
        file.value = selected;
        dialog.value.showModal();
    } catch (error) {
        if (!disposed && token === readToken) errors.value = [error.message];
    } finally {
        if (!disposed && token === readToken) reading.value = false;
    }
}

function close() {
    if (pending.value) return;
    dialog.value?.close(); file.value = null; preview.value = null; errors.value = [];
}

function perform(event, payload, message) {
    if (unavailable.value) return;
    pending.value = true; emit('busy', true); errors.value = []; notice.value = '';
    let completed = false;
    emit(event, payload, (result = {}) => {
        if (completed || disposed) return;
        completed = true; pending.value = false; emit('busy', false);
        errors.value = Object.values(result).flat().map(String);
        if (!errors.value.length) {
            if (event === 'import') close();
            notice.value = message;
        }
    });
}
function confirm() {
    if (file.value) perform('import', file.value, 'Examination imported.');
}
function exportFile(format) {
    menu.value?.removeAttribute('open');
    perform('export', format, `${format.toUpperCase()} export downloaded.`);
}
// The PNG buttons are on the sketches, but share these controls' progress and error messages.
function exportSketch(sketch) {
    perform('export-sketch', sketch, 'PNG export downloaded.');
}
defineExpose({exportSketch});
</script>

<template>
    <div v-if="importEnabled || exportEnabled" class="he-transfer">
        <input ref="input" type="file" accept=".json,application/json" aria-label="Choose examination JSON" hidden @change="choose"/>
        <button v-if="importEnabled" type="button" class="he-transfer-button" :disabled="unavailable" title="Import a JSON examination" @click="input.click()">Import JSON</button>
        <details v-if="exportEnabled" ref="menu" class="he-export-menu">
            <summary class="he-transfer-button" :aria-disabled="unavailable" @click="unavailable && $event.preventDefault()">Export</summary>
            <div class="he-export-options">
                <button type="button" :disabled="unavailable" @click="exportFile('json')">Export JSON</button>
                <button type="button" :disabled="unavailable" @click="exportFile('csv')">Export CSV</button>
            </div>
        </details>
        <div v-if="!preview && (reading || pending || notice || errors.length)" class="he-transfer-feedback">
            <div v-if="errors.length" class="he-transfer-errors" role="alert">
                <p v-for="(error, index) in errors" :key="index">{{ error }}</p>
            </div>
            <div v-else role="status">
                {{ reading ? 'Reading examination file…' : pending ? 'Preparing export…' : notice }}
            </div>
            <button
                v-if="!reading && !pending"
                type="button"
                class="he-transfer-dismiss"
                aria-label="Dismiss transfer message"
                @click="errors = []; notice = ''"
            >
                <span aria-hidden="true">×</span>
            </button>
        </div>
        <dialog ref="dialog" class="he-import-dialog" :aria-labelledby="titleId" @cancel.prevent="close">
            <form v-if="preview" @submit.prevent="confirm">
                <h2 :id="titleId">Import examination</h2>
                <p><strong>{{ preview.examination.reference || 'Untitled examination' }}</strong></p>
                <p>{{ preview.observations.length }} observation{{ preview.observations.length === 1 ? '' : 's' }} with examination details and mapped locations.</p>
                <p v-if="persistent">A new examination will be created. Existing saved examinations will remain unchanged.</p>
                <p v-else>This replaces the local workspace. Export it first if you want to keep a copy.</p>
                <div v-if="errors.length" class="he-transfer-errors" role="alert"><p v-for="(error, index) in errors" :key="index">{{ error }}</p></div>
                <p v-if="pending" role="status">Importing examination…</p>
                <div class="he-import-actions">
                    <button type="button" class="he-transfer-button" :disabled="pending" @click="close">Cancel</button>
                    <button type="submit" class="he-transfer-button he-transfer-primary" :disabled="unavailable">{{ persistent ? 'Import examination' : 'Open examination' }}</button>
                </div>
            </form>
        </dialog>
    </div>
</template>

<style scoped>
.he-transfer { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: .5rem; }
.he-transfer-button { box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; min-height: 2.25rem; padding: .5rem .75rem; background: var(--he-background, #fff); color: var(--he-foreground, #171717); border: 1px solid var(--he-border, #e5e5e5); border-radius: .375rem; font: inherit; font-size: .8125rem; cursor: pointer; list-style: none; }
.he-transfer-button:disabled, .he-transfer-button[aria-disabled=true] { opacity: .45; cursor: default; }
.he-transfer-button:focus-visible, .he-export-options button:focus-visible { outline: 2px solid var(--he-ring, #a3a3a3); outline-offset: 2px; }
.he-export-menu { position: relative; }
summary::-webkit-details-marker { display: none; }
.he-export-options { position: absolute; right: 0; z-index: 40; min-width: 9rem; padding: .25rem; border: 1px solid var(--he-border, #e5e5e5); border-radius: .375rem; background: var(--he-background, #fff); box-shadow: 0 3px 8px #0002; }
.he-export-options button { display: block; width: 100%; padding: .5rem; border: 0; border-radius: .25rem; font: inherit; font-size: .8125rem; text-align: left; background: transparent; color: inherit; cursor: pointer; }
.he-export-options button:hover { background: var(--he-muted, #f5f5f5); }
.he-transfer-feedback {
    position: fixed;
    z-index: 1000;
    inset-inline-end: max(1rem, env(safe-area-inset-right));
    bottom: max(1rem, env(safe-area-inset-bottom));
    display: flex;
    align-items: center;
    gap: .75rem;
    box-sizing: border-box;
    max-width: min(24rem, calc(100vw - 2rem));
    max-height: 50dvh;
    overflow: auto;
    overflow-wrap: anywhere;
    padding: .5rem .75rem;
    border: 1px solid var(--he-border, #e5e5e5);
    border-radius: .5rem;
    background: var(--he-background, #fff);
    color: var(--he-foreground, #171717);
    box-shadow: 0 4px 16px rgb(0 0 0 / .14);
    font-size: .875rem;
}
.he-transfer-dismiss {
    flex: none;
    margin-inline-start: auto;
    width: 2rem;
    height: 2rem;
    padding: 0;
    border: 0;
    border-radius: .25rem;
    background: transparent;
    color: inherit;
    font-size: 1.25rem;
    cursor: pointer;
}
.he-transfer-dismiss:hover { background: var(--he-muted, #f5f5f5); }
.he-transfer-errors { font-size: .8125rem; }
.he-transfer-errors { color: #b91c1c; }
.he-transfer-errors p { margin: .35rem 0; }
.he-import-dialog { margin: auto; width: min(30rem, calc(100vw - 2rem)); box-sizing: border-box; padding: 1.25rem; border: 1px solid var(--he-border, #e5e5e5); border-radius: .75rem; color: var(--he-foreground, #171717); background: var(--he-background, #fff); font: inherit; }
.he-import-dialog::backdrop { background: #0006; }
.he-import-dialog h2 { font-size: 1.125rem; margin: 0 0 1rem; }
.he-import-dialog p { line-height: 1.5; font-size: .875rem; }
.he-import-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1.25rem; }
.he-transfer-primary { background: var(--he-primary, #171717); color: var(--he-primary-foreground, #fff); }
</style>
