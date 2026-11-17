<script setup>
defineProps({undoDisabled: Boolean, redoDisabled: Boolean, editing: Boolean});
const emit = defineEmits(['undo', 'redo']);
</script>

<template>
    <div class="he-history" role="group" aria-label="Observation history">
        <button type="button" aria-label="Undo" aria-keyshortcuts="Control+Z Meta+Z" :disabled="undoDisabled" :title="editing ? 'Save or cancel the current edit first' : 'Undo last saved observation change (Ctrl+Z / Cmd+Z)'" @click="emit('undo')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5-5 5 5 5M4 10h10a6 6 0 0 1 0 12"/></svg> Undo
        </button>
        <button type="button" aria-label="Redo" aria-keyshortcuts="Control+Shift+Z Meta+Shift+Z Control+Y" :disabled="redoDisabled" :title="editing ? 'Save or cancel the current edit first' : 'Redo last undone observation change (Ctrl+Y / Cmd+Shift+Z)'" @click="emit('redo')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5 5 5-5 5M20 10H10a6 6 0 0 0 0 12"/></svg> Redo
        </button>
    </div>
</template>

<style scoped>
.he-history { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-left: auto; }
button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    height: 2.25rem;
    padding: 0 0.75rem;
    border: 1px solid var(--he-border, #e5e5e5);
    border-radius: 0.375rem;
    background: var(--he-background, #fff);
    color: var(--he-foreground, #171717);
    font: inherit;
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
    cursor: pointer;
}
button:hover:not(:disabled) { background: var(--he-muted, #f5f5f5); }
button:disabled { opacity: 0.45; cursor: default; }
button:focus-visible { outline: 2px solid var(--he-ring, #a3a3a3); outline-offset: 2px; }
svg { width: 1rem; height: 1rem; fill: none; stroke: currentColor; stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round; }
</style>
