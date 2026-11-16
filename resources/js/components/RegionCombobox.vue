<script setup>
import {computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch} from 'vue';

defineOptions({inheritAttrs: false});
const props = defineProps({
    options: {type: Array, default: () => []},
    disabled: {type: Boolean, default: false},
});
const value = defineModel({type: String, default: null});
const emit = defineEmits(['edit']);
const root = ref(null);
const input = ref(null);
const list = ref(null);
const listId = `${useId()}-region-options`;
const open = ref(false);
const filtering = ref(false);
const activeIndex = ref(-1);
const upward = ref(false);
const maxHeight = ref(288);
const choices = computed(() => {
    const query = filtering.value ? String(value.value ?? '').trim().toLocaleLowerCase() : '';
    return props.options.filter((option) => !query || option.toLocaleLowerCase().includes(query));
});
const activeId = computed(() => open.value && activeIndex.value >= 0
    ? `${listId}-${activeIndex.value}` : undefined,
);

function close() {
    open.value = false;
    activeIndex.value = -1;
}

function positionMenu() {
    if (!open.value || !root.value) return;
    const bounds = root.value.getBoundingClientRect();
    const below = window.innerHeight - bounds.bottom - 8;
    const above = bounds.top - 8;
    upward.value = below < 200 && above > below;
    maxHeight.value = Math.max(80, Math.min(288, upward.value ? above : below));
}

function revealActive() {
    nextTick(() => list.value?.querySelector('[aria-selected="true"]')?.scrollIntoView({block: 'nearest'}));
}

function openChoices() {
    if (props.disabled) return;
    filtering.value = false;
    open.value = true;
    activeIndex.value = choices.value.indexOf(value.value);
    nextTick(() => {
        positionMenu();
        revealActive();
    });
}

function toggle() {
    if (props.disabled) return;
    if (open.value) close();
    else openChoices();
    input.value?.focus();
}

function edit(event) {
    if (props.disabled || event.isComposing) return;
    value.value = event.target.value;
    emit('edit');
    filtering.value = true;
    activeIndex.value = -1;
    open.value = true;
    nextTick(positionMenu);
}

function choose(option) {
    if (props.disabled) return;
    value.value = option;
    emit('edit');
    close();
    input.value?.focus();
}

function keydown(event) {
    if (props.disabled || event.isComposing) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const wasOpen = open.value;
        if (!wasOpen) openChoices();
        const length = choices.value.length;
        if (!length) return;
        if (activeIndex.value < 0) activeIndex.value = event.key === 'ArrowDown' ? 0 : length - 1;
        else if (wasOpen) activeIndex.value = Math.max(0, Math.min(length - 1, activeIndex.value + (event.key === 'ArrowDown' ? 1 : -1)));
        revealActive();
    } else if (open.value && event.key === 'Enter') {
        event.preventDefault();
        if (activeIndex.value >= 0 && choices.value[activeIndex.value]) choose(choices.value[activeIndex.value]);
        else close();
    } else if (open.value && event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
    } else if (open.value && activeIndex.value >= 0 && ['Home', 'End'].includes(event.key)) {
        event.preventDefault();
        activeIndex.value = event.key === 'Home' ? 0 : choices.value.length - 1;
        revealActive();
    } else if (event.key === 'Tab') close();
}

function outside(event) {
    if (!root.value?.contains(event.target)) close();
}

watch(() => props.disabled, (disabled) => {
    if (disabled) close();
});
watch(value, () => {
    if (open.value && !filtering.value) {
        activeIndex.value = choices.value.indexOf(value.value);
        revealActive();
    }
});
onMounted(() => {
    document.addEventListener('pointerdown', outside);
    window.addEventListener('resize', positionMenu);
    window.addEventListener('scroll', positionMenu, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', outside);
    window.removeEventListener('resize', positionMenu);
    window.removeEventListener('scroll', positionMenu, true);
});
</script>

<template>
    <div ref="root" class="he-region-combobox">
        <input
            ref="input"
            v-bind="$attrs"
            :value="value"
            :disabled="disabled"
            type="text"
            class="he-region-input"
            role="combobox"
            aria-autocomplete="list"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-controls="listId"
            :aria-activedescendant="activeId"
            autocomplete="off"
            @input="edit"
            @compositionend="edit"
            @click="openChoices"
            @keydown="keydown"
            @blur="close"
        />
        <button
            type="button"
            class="he-region-toggle"
            tabindex="-1"
            :disabled="disabled"
            :aria-label="open ? 'Hide region choices' : 'Show region choices'"
            :aria-expanded="open"
            :aria-controls="listId"
            @pointerdown.prevent
            @click="toggle"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>
        <ul
            v-if="open"
            :id="listId"
            ref="list"
            class="he-region-menu"
            :class="{ 'he-region-menu--up': upward }"
            :style="{ maxHeight: `${maxHeight}px` }"
            role="listbox"
            aria-label="Region choices"
        >
            <li
                v-for="(option, index) in choices"
                :id="`${listId}-${index}`"
                :key="option"
                class="he-region-option"
                role="option"
                :aria-selected="index === activeIndex"
                @pointermove="activeIndex = index"
                @pointerdown.prevent
                @click="choose(option)"
            >{{ option }}
            </li>
            <li v-if="!choices.length" class="he-region-empty" role="presentation">No matching regions. You can enter
                your own.
            </li>
        </ul>
    </div>
</template>

<style scoped>
.he-region-combobox {
    position: relative;
    min-width: 0;
}

.he-region-input {
    box-sizing: border-box;
    width: 100%;
    height: 2.25rem;
    padding: 0 2.5rem 0 0.75rem;
    border: 1px solid var(--he-input, #e5e5e5);
    border-radius: 0.375rem;
    background: var(--he-background, #fff);
    color: var(--he-foreground, #0a0a0a);
    font: inherit;
    font-size: 0.875rem;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
}

.he-region-input:focus-visible {
    outline: 2px solid var(--he-ring, #a3a3a3);
    outline-offset: 2px;
}

.he-region-toggle {
    position: absolute;
    top: 1px;
    right: 1px;
    display: grid;
    place-items: center;
    width: 2.25rem;
    height: calc(2.25rem - 2px);
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--he-muted-foreground, #737373);
    cursor: pointer;
}

.he-region-toggle svg {
    width: 1rem;
    height: 1rem;
}

.he-region-toggle:disabled {
    cursor: default;
}

.he-region-menu {
    position: absolute;
    z-index: 30;
    top: calc(100% + 1px);
    left: 0;
    box-sizing: border-box;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    border: 1px solid var(--he-ring, #a3a3a3);
    border-radius: 0.125rem;
    background: var(--he-background, #fff);
    color: var(--he-foreground, #0a0a0a);
    box-shadow: 0 3px 8px rgb(0 0 0 / 0.16);
    font-size: 0.875rem;
    list-style: none;
}

.he-region-menu--up {
    top: auto;
    bottom: calc(100% + 1px);
}

.he-region-option {
    padding: 0.375rem 0.5rem;
    cursor: pointer;
    overflow-wrap: anywhere;
}

.he-region-option[aria-selected="true"] {
    background: #1967d2;
    color: #fff;
}

.he-region-empty {
    padding: 0.5rem;
    color: var(--he-muted-foreground, #737373);
}

@media (forced-colors: active) {
    .he-region-option[aria-selected="true"] {
        background: Highlight;
        color: HighlightText;
    }
}
</style>
