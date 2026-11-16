<script setup>
import {computed, nextTick, onBeforeUnmount, ref, shallowRef, useId, watch} from 'vue';
import {sketchGroups, sketches} from '../sketches.js';
import {getObservationLocation, offsetLocation, pointToLocation} from '../observationLocations.js';
import {observationStyle, observationType} from '../observationTypes.js';
import {initialSketchLocation, loadSketchHitArea, locationOnSketch} from '../sketchHitAreas.js';

const props = defineProps({
    observations: {type: Array, default: () => []},
    selectedNumber: {type: Number, default: null},
    draftLocation: {type: Object, default: null},
    placementEnabled: {type: Boolean, default: false},
    disabled: {type: Boolean, default: false},
});
const emit = defineEmits(['place', 'select']);
const canPlace = computed(() => props.placementEnabled && !props.disabled);
const hitAreas = shallowRef({});
const outlineErrors = ref({});
const placementMessage = ref('');
let placementTimer;

function dismissPlacementWarning() {
    clearTimeout(placementTimer);
    placementMessage.value = '';
}

function warnPlacement() {
    clearTimeout(placementTimer);
    placementMessage.value = 'Place markers inside the anatomical outline.';
    placementTimer = setTimeout(dismissPlacementWarning, 5000);
}

onBeforeUnmount(dismissPlacementWarning);
const preview = computed(() => getObservationLocation(props.draftLocation));
const previewLabel = computed(() => preview.value
    ? `Unsaved ${observationType(props.draftLocation?.type)} marker at ${Math.round(preview.value.x * 1000) / 10}% across and ${Math.round(preview.value.y * 1000) / 10}% down. Arrow keys adjust its location; hold Shift for smaller steps. Save the observation to keep it.`
    : '',
);
const markers = computed(() => props.observations.flatMap((observation) => {
    const location = getObservationLocation(observation);
    const number = Number(observation.number);
    return location && Number.isInteger(number) && number > 0
        ? [{...observation, number, location}]
        : [];
}));
const legendTypes = computed(() => [...new Set([
    ...props.observations.map((observation) => observationType(observation.type)),
    ...(preview.value ? [observationType(props.draftLocation?.type)] : []),
])].sort((a, b) => a.localeCompare(b)));

function markersFor(sketchId) {
    return markers.value.filter((marker) => marker.location.sketch_id === sketchId);
}

function position(location) {
    return {left: `${location.x * 100}%`, top: `${location.y * 100}%`};
}

async function prepareSketch(sketch) {
    outlineErrors.value = {...outlineErrors.value, [sketch.id]: false};
    try {
        const area = await loadSketchHitArea(sketch);
        hitAreas.value = {...hitAreas.value, [sketch.id]: area};
    } catch {
        outlineErrors.value = {...outlineErrors.value, [sketch.id]: true};
    }
}

function placeOnAnatomy(location) {
    const point = locationOnSketch(location, hitAreas.value[location?.sketch_id]);
    if (!point) {
        warnPlacement();
        return false;
    }
    dismissPlacementWarning();
    emit('place', point);
    return true;
}

function placeFromPointer(event, sketch) {
    if (!canPlace.value || !hitAreas.value[sketch.id] || event.button !== 0) return;
    const location = pointToLocation(sketch.id, event.clientX, event.clientY, event.currentTarget.getBoundingClientRect());
    placeOnAnatomy(location);
}

async function placeFromKeyboard(event, sketch) {
    if (!canPlace.value || !hitAreas.value[sketch.id]) return;
    const surface = event.currentTarget;
    if (!placeOnAnatomy(initialSketchLocation(hitAreas.value[sketch.id]))) return;
    await nextTick();
    surface.querySelector('[data-draft-marker]')?.focus();
}

function refinePreview(event) {
    const steps = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]};
    if (!canPlace.value || !steps[event.key]) return;
    event.preventDefault();
    event.stopPropagation();
    const [dx, dy] = steps[event.key];
    const step = event.shiftKey ? 0.001 : 0.005;
    const location = offsetLocation(preview.value, dx * step, dy * step);
    if (location) placeOnAnatomy(location);
}

const view = defineModel('view', {type: String, default: 'body-front'});
const selected = computed(
    () => sketches.find((sketch) => sketch.id === view.value) ?? sketches[0],
);
const selectedGroup = computed(() => sketchGroups.find((group) =>
        group.views.some((sketch) => sketch.id === selected.value.id),
    ) ?? sketchGroups[0],
);
const paired = computed(() => selectedGroup.value.id === 'body');
const visibleSketches = computed(() => selectedGroup.value.views);
const tabId = `${useId()}-anatomy`;
const pairRatio = computed(
    () => Math.max(...visibleSketches.value.map((sketch) => sketch.width / sketch.height)),
);

function selectGroup(group) {
    if (!props.disabled) view.value = group.views[0].id;
}

function navigateGroups(event, index) {
    if (props.disabled || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const tabs = event.currentTarget.parentElement;
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? sketchGroups.length - 1
        : (index + (event.key === 'ArrowRight' ? 1 : -1) + sketchGroups.length) % sketchGroups.length;
    selectGroup(sketchGroups[next]);
    nextTick(() => tabs.querySelectorAll('[role="tab"]')[next]?.focus());
}

function groupHasMarkers(group) {
    return markers.value.some((marker) => group.views.some((sketch) => sketch.id === marker.location.sketch_id));
}

const failed = ref({});

watch(
    () => selected.value.id,
    () => {
        failed.value = {};
        dismissPlacementWarning();
    },
);
</script>

<template>
    <div class="he-anatomy">
        <div class="he-view-picker">
            <span>Anatomical view</span>
            <div class="he-view-tabs" role="tablist" aria-label="Anatomical views">
                <button
                    v-for="(group, index) in sketchGroups"
                    :id="`${tabId}-${group.id}`"
                    :key="group.id"
                    type="button"
                    class="he-view-tab"
                    role="tab"
                    :data-group="group.id"
                    :aria-selected="selectedGroup.id === group.id"
                    :aria-controls="`${tabId}-panel`"
                    :tabindex="selectedGroup.id === group.id ? 0 : -1"
                    :disabled="disabled"
                    @click="selectGroup(group)"
                    @keydown="navigateGroups($event, index)"
                >
                    {{ group.label }}
                    <template v-if="groupHasMarkers(group)">
                        <span class="he-view-tab-dot" aria-hidden="true"></span>
                        <span class="he-visually-hidden">Contains mapped observations</span>
                    </template>
                </button>
            </div>
        </div>

        <p v-if="canPlace" class="he-placement-help">
            Click inside an anatomical outline to place a marker. For keyboard placement, focus a sketch and press
            Enter.
        </p>

        <div
            :id="`${tabId}-panel`"
            class="he-view-panel he-diagrams"
            :class="{ 'he-diagrams--paired': paired }"
            :style="{ '--he-pair-ratio': pairRatio }"
            role="tabpanel"
            :aria-labelledby="`${tabId}-${selectedGroup.id}`"
            tabindex="0"
        >
            <figure
                v-for="sketch in visibleSketches"
                :key="sketch.id"
                class="he-diagram"
                :data-sketch="sketch.id"
            >
                <figcaption class="he-diagram-caption">{{ sketch.label }}</figcaption>

                <p v-if="failed[sketch.id]" class="he-diagram-error" role="alert">
                    This anatomical view could not be loaded. Select another view or
                    reload the page.
                </p>
                <div
                    v-else
                    class="he-diagram-surface"
                    :style="{ '--he-sketch-ratio': sketch.width / sketch.height }"
                >
                    <div
                        class="he-diagram-map"
                        :class="{ 'he-diagram-map--placing': canPlace && hitAreas[sketch.id] }"
                        role="group"
                        :tabindex="canPlace && hitAreas[sketch.id] ? 0 : -1"
                        :aria-label="`${sketch.label} map.${canPlace && hitAreas[sketch.id] ? ' Press Enter or Space to place a marker inside the anatomical outline.' : ''}`"
                        :aria-disabled="disabled"
                        :aria-busy="canPlace && !hitAreas[sketch.id] && !outlineErrors[sketch.id]"
                        @click="placeFromPointer($event, sketch)"
                        @keydown.enter.self.prevent="placeFromKeyboard($event, sketch)"
                        @keydown.space.self.prevent="placeFromKeyboard($event, sketch)"
                    >
                        <img
                            :src="sketch.src"
                            :alt="`${sketch.label} anatomical sketch`"
                            draggable="false"
                            @load="prepareSketch(sketch)"
                            @error="failed[sketch.id] = true"
                        />
                        <button
                            v-for="marker in markersFor(sketch.id)"
                            :key="marker.number"
                            type="button"
                            class="he-marker"
                            :class="{ 'he-marker--selected': selectedNumber === marker.number }"
                            :data-observation="marker.number"
                            :style="{ ...position(marker.location), ...observationStyle(marker.type) }"
                            :aria-label="`Observation ${marker.number}, ${observationType(marker.type)}${marker.label ? `: ${marker.label}` : ''}`"
                            :aria-pressed="selectedNumber === marker.number"
                            :disabled="disabled"
                            @click.stop="emit('select', marker.number)"
                        >
                            <span aria-hidden="true">{{ marker.number }}</span>
                        </button>
                        <button
                            v-if="preview?.sketch_id === sketch.id"
                            type="button"
                            class="he-marker he-marker--draft"
                            data-draft-marker
                            :style="{ ...position(preview), ...observationStyle(draftLocation.type) }"
                            :aria-label="previewLabel"
                            :disabled="!canPlace"
                            @click.stop
                            @keydown="refinePreview"
                        >
                            <span aria-hidden="true">{{ draftLocation.number ?? '+' }}</span>
                        </button>
                    </div>
                    <div
                        v-if="sketch.sides"
                        class="he-diagram-sides"
                        role="group"
                        aria-label="Anatomical sides"
                    >
                        <span>{{ sketch.sides[0] }}</span>
                        <span>{{ sketch.sides[1] }}</span>
                    </div>
                </div>
                <p v-if="canPlace && outlineErrors[sketch.id]" class="he-diagram-error" role="alert">
                    Marker placement is unavailable for this view. Reload to try again.
                </p>
            </figure>
        </div>

        <div class="he-legend" role="group" aria-label="Observation type legend">
            <span class="he-legend__title">Observation types</span>
            <ul v-if="legendTypes.length" class="he-legend__items">
                <li v-for="type in legendTypes" :key="type" :style="observationStyle(type)">
                    <span class="he-legend__swatch" aria-hidden="true"></span>
                    {{ type }}
                </li>
            </ul>
            <p v-else>Your observations will appear here.</p>
        </div>

        <p class="he-anatomy-note">Anatomical left and right refer to the subject.</p>
        <Teleport to="body">
            <div
                v-if="canPlace && placementMessage"
                class="he-placement-warning"
                role="alert"
                aria-label="Placement warning"
                aria-atomic="true"
            >
                <svg class="he-warning-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.1l-8-14a2 2 0 0 0-3.4 0Z"/>
                    <path d="M12 9v4m0 4h.01"/>
                </svg>
                <p>{{ placementMessage }}</p>
                <button type="button" class="he-warning-dismiss" aria-label="Dismiss placement warning"
                        @click="dismissPlacementWarning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         aria-hidden="true">
                        <path d="m6 6 12 12M6 18 18 6"/>
                    </svg>
                </button>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.he-anatomy {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    padding: 1rem;
    color: var(--he-foreground, #0a0a0a);
    container-type: inline-size;
    container-name: examination-anatomy;
}

.he-view-picker {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
    font-size: 0.875rem;
    font-weight: 500;
}

.he-view-tabs {
    display: inline-flex;
    flex-wrap: wrap;
    align-self: flex-start;
    gap: 0.25rem;
    box-sizing: border-box;
    max-width: 100%;
    padding: 0.25rem;
    border-radius: 0.5rem;
    background: var(--he-muted, #f5f5f5);
}

.he-view-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    min-width: 0;
    padding: 0.375rem 0.5rem;
    border: 0;
    border-radius: 0.375rem;
    background: transparent;
    color: var(--he-muted-foreground, #737373);
    font: inherit;
    font-size: 0.8125rem;
    font-weight: 400;
    white-space: nowrap;
    cursor: pointer;
}

.he-view-tab:hover:not(:disabled) {
    color: var(--he-foreground, #0a0a0a);
}

.he-view-tab[aria-selected="true"] {
    background: var(--he-background, #fff);
    color: var(--he-foreground, #0a0a0a);
    font-weight: 500;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
}

.he-view-tab:disabled {
    opacity: 0.5;
    cursor: default;
}

.he-view-tab-dot {
    width: 0.25rem;
    height: 0.25rem;
    border-radius: 50%;
    background: currentColor;
}

.he-view-tab:focus-visible,
.he-view-panel:focus-visible {
    outline: 2px solid var(--he-ring, #a3a3a3);
    outline-offset: 2px;
}

.he-visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.he-diagrams {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem;
    margin: 1rem 0 0;
}

@container examination-anatomy (max-width: 27rem) {
    .he-diagrams {
        grid-template-columns: 1fr;
    }
}

.he-diagram {
    min-width: 0;
    margin: 0;
}

.he-diagram-caption {
    margin-bottom: 1rem;
    text-align: center;
    font-weight: 600;
}

.he-diagrams--paired .he-diagram-caption {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 2.8em;
}

.he-diagram-surface {
    width: 100%;
    max-width: calc(40rem * var(--he-sketch-ratio));
    margin: 0 auto;
}

.he-diagrams--paired .he-diagram-surface {
    /* Fit both drawings to the same height without stretching either SVG. */
    width: calc(100% * var(--he-sketch-ratio) / var(--he-pair-ratio));
}

.he-diagram-surface img {
    display: block;
    width: 100%;
    height: auto;
    aspect-ratio: var(--he-sketch-ratio);
    /* The original line drawings need a light surface in dark host themes. */
    background: #fff;
}

.he-diagram-map {
    position: relative;
    touch-action: manipulation;
}

.he-diagram-map--placing {
    cursor: crosshair;
}

.he-diagram-map:focus-visible {
    outline: 2px solid var(--he-ring, #a3a3a3);
    outline-offset: 3px;
}

.he-marker {
    position: absolute;
    z-index: 1;
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #fff;
    font: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transform: translate(-50%, -50%);
}

.he-marker span {
    display: grid;
    place-items: center;
    box-sizing: border-box;
    min-width: 1.75rem;
    height: 1.75rem;
    padding: 0 0.25rem;
    border: 2px solid #fff;
    border-radius: 999px;
    background: var(--he-observation-color, #8a8a8a);
    color: var(--he-observation-foreground, #fff);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--he-observation-color, #8a8a8a) 18%, transparent);
}

.he-marker--selected,
.he-marker:focus-visible {
    z-index: 2;
    outline: 2px solid #171717;
    outline-offset: -5px;
}

.he-marker--draft span {
    border-style: dashed;
}

.he-marker:disabled {
    cursor: default;
}

.he-legend {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--he-border, #e5e5e5);
    font-size: 0.8125rem;
}

.he-legend__title {
    font-weight: 600;
}

.he-legend__items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin: 0.5rem 0 0;
    padding: 0;
    list-style: none;
}

.he-legend__items li {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    min-width: 0;
    overflow-wrap: anywhere;
}

.he-legend__swatch {
    flex-shrink: 0;
    width: 0.625rem;
    height: 0.625rem;
    border-radius: 50%;
    background: var(--he-observation-color);
}

.he-legend p {
    margin: 0.5rem 0 0;
    color: var(--he-muted-foreground, #737373);
}

.he-diagram-sides {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.he-anatomy-note,
.he-placement-help,
.he-diagram-error {
    margin: 1rem 0 0;
    color: var(--he-muted-foreground, #737373);
    font-size: 0.8125rem;
    text-align: center;
}

.he-placement-warning {
    position: fixed;
    z-index: 1000;
    right: max(1rem, env(safe-area-inset-right));
    bottom: max(1rem, env(safe-area-inset-bottom));
    display: grid;
    grid-template-columns: 1.25rem 1fr 2.75rem;
    align-items: center;
    gap: 0.75rem;
    box-sizing: border-box;
    width: min(24rem, calc(100vw - 2rem));
    padding: 0.5rem 0.5rem 0.5rem 0.875rem;
    border: 1px solid #fbbf24;
    border-radius: 0.5rem;
    background: #fffbeb;
    color: #78350f;
    box-shadow: 0 4px 16px rgb(0 0 0 / 0.14);
    font-family: system-ui, sans-serif;
    font-size: 0.875rem;
    line-height: 1.4;
}

.he-placement-warning p {
    margin: 0;
}

.he-warning-icon {
    width: 1.25rem;
    height: 1.25rem;
}

.he-warning-dismiss {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    padding: 0;
    border: 0;
    border-radius: 0.25rem;
    background: transparent;
    color: inherit;
    cursor: pointer;
}

.he-warning-dismiss svg {
    width: 1rem;
    height: 1rem;
}

.he-warning-dismiss:hover {
    background: #fef3c7;
}

.he-warning-dismiss:focus-visible {
    outline: 2px solid #92400e;
    outline-offset: -2px;
}
</style>
