<script setup>
defineProps({
    // Observation types for the Type dropdown.
    types: { type: Array, default: () => [] },
});

const emit = defineEmits(['new-examination']);

import { computed, ref } from 'vue';

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
    examination.value = {
        ...examination.value,
        [key]: value === '' ? null : value,
    };
}

const FIELDS = ['type', 'label', 'region', 'length_mm', 'width_mm'];

// The observation being added or edited.
const draft = ref(null);

function startNew() {
    draft.value = {
        number: null,
        type: null,
        label: null,
        region: null,
        length_mm: null,
        width_mm: null,
    };
}

function edit(observation) {
    draft.value = {
        number: observation.number,
        ...Object.fromEntries(FIELDS.map((f) => [f, observation[f]])),
    };
}

function cancel() {
    draft.value = null;
}

// An observation can only be saved once it has a type.
const missingType = computed(
    () => !draft.value || !String(draft.value.type ?? '').trim(),
);

function save() {
    if (missingType.value) {
        return;
    }

    const values = Object.fromEntries(
        FIELDS.map((f) => [f, draft.value[f] === '' ? null : draft.value[f]]),
    );

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
    } else {
        observations.value = observations.value.map((o) =>
            o.number === draft.value.number ? { ...o, ...values } : o,
        );
    }

    draft.value = null;
}

function removeObservation(number) {
    observations.value = observations.value.filter((o) => o.number !== number);

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
    <div class="he-workspace">
        <div class="he-toolbar">
            <div v-if="examination.id" class="he-current">
                <strong>{{
                    examination.reference || `Examination #${examination.id}`
                }}</strong>
                <span
                    v-if="examination.examiner || examination.date"
                    class="he-muted he-small"
                >
                    {{
                        [examination.examiner, examination.date]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </span>
            </div>

            <button
                type="button"
                class="he-button he-button--primary"
                @click="openNewExamination"
            >
                <svg class="he-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 12h14M12 5v14" />
                </svg>
                New Examination
            </button>
        </div>

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

                <label class="he-field">
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
                    />
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
            <section class="he-card he-area" aria-label="Workspace area">
                <slot>
                    <p class="he-muted">Workspace area</p>
                </slot>
            </section>

            <aside v-if="$slots.aside" class="he-card he-aside">
                <slot name="aside" />
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
                        @click="startNew"
                    >
                        <svg
                            class="he-icon"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M5 12h14M12 5v14" />
                        </svg>
                    </button>
                </div>

                <div v-if="tab === 'examination'" class="he-details">
                    <label class="he-field">
                        <span>Reference</span>
                        <input
                            class="he-input"
                            :value="examination.reference"
                            placeholder="e.g. EX-2026-001"
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
                            placeholder="Name or initials"
                            @change="
                                updateExamination(
                                    'examiner',
                                    $event.target.value,
                                )
                            "
                        />
                    </label>

                    <label class="he-field">
                        <span>Date</span>
                        <input
                            class="he-input"
                            type="date"
                            :value="examination.date"
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
                            placeholder="General findings and context…"
                            @change="
                                updateExamination('notes', $event.target.value)
                            "
                        ></textarea>
                    </label>
                </div>

                <template v-else>
                    <form v-if="draft" class="he-form" @submit.prevent="save">
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
                                class="he-input"
                                required
                            />
                        </label>

                        <label class="he-field">
                            <span>Label</span>
                            <input
                                v-model="draft.label"
                                class="he-input"
                                placeholder="e.g. Linear scar"
                            />
                        </label>

                        <label class="he-field">
                            <span>Region</span>
                            <input
                                v-model="draft.region"
                                class="he-input"
                                placeholder="e.g. Right upper arm"
                            />
                        </label>

                        <div class="he-field-row">
                            <label class="he-field">
                                <span>Length (mm)</span>
                                <input
                                    v-model="draft.length_mm"
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
                                    class="he-input"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </label>
                        </div>

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
                                :disabled="missingType"
                                :title="
                                    missingType ? 'Choose a type to save' : null
                                "
                            >
                                Save
                            </button>
                        </div>
                    </form>

                    <ul v-if="observations.length" class="he-list">
                        <li
                            v-for="o in observations"
                            :key="o.number"
                            class="he-item"
                            :class="{
                                'he-item--editing': draft?.number === o.number,
                            }"
                        >
                            <span class="he-badge">{{ o.number }}</span>

                            <div class="he-item__text">
                                <strong>{{
                                    o.label ||
                                    o.type ||
                                    `Observation ${o.number}`
                                }}</strong>
                                <small v-if="details(o)">{{
                                    details(o)
                                }}</small>
                            </div>

                            <button
                                type="button"
                                class="he-button he-button--ghost he-button--icon-sm"
                                :aria-label="`Edit observation ${o.number}`"
                                title="Edit observation"
                                @click="edit(o)"
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
                                @click="removeObservation(o.number)"
                            >
                                <svg
                                    class="he-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M18 6 6 18M6 6l12 12" />
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
                                <rect x="9" y="3" width="6" height="4" rx="1" />
                                <path d="M9 12h6M9 16h4" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="he-empty__title">No observations yet</h3>
                            <p class="he-muted he-small">
                                Use + to record the first observation.
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
    gap: 1rem;
}

/* Keeps the button on the right whether or not an examination is shown. */
.he-toolbar > .he-button {
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

.he-aside {
    width: 20rem;
    padding: 1rem;
}

.he-panel {
    flex-shrink: 0;
    align-self: flex-start;
    width: 22rem;
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

@media (max-width: 767px) {
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

.he-item--editing {
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
    background: var(--he-primary);
    color: var(--he-primary-foreground);
    font-size: 0.75rem;
    font-weight: 600;
}

.he-item__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.he-item__text strong,
.he-item__text small {
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

.he-field > span {
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

.he-textarea {
    height: auto;
    padding: 0.5rem 0.75rem;
    resize: vertical;
}

/* New examination dialog */
.he-dialog {
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
