<script setup>
import {computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, useId, watch} from 'vue';
import {sketchGroups, sketches} from '../sketches.js';
import {getObservationLocation, offsetLocation, pointToLocation} from '../observationLocations.js';
import {observationStyle, observationType} from '../observationTypes.js';
import {initialSketchLocation, loadSketchHitArea, locationOnSketch} from '../sketchHitAreas.js';
import {clampCamera, fitDiagram, panCamera, wheelZoomFactor, zoomCamera} from '../diagramViewport.js';
import ObservationHeatmap from './ObservationHeatmap.vue';
import {buildHeatmaps} from '../observationDensity.js';

const props = defineProps({
    observations: {type: Array, default: () => []},
    selectedNumber: {type: Number, default: null},
    draftLocation: {type: Object, default: null},
    placementEnabled: {type: Boolean, default: false},
    editingNumber: {type: Number, default: null},
    disabled: {type: Boolean, default: false},
    densityEnabled: {type: Boolean, default: true},
    markerColors: {type: Object, default: () => ({})},
    initialDiagramHeight: {type: Number, default: 560},
    exportEnabled: {type: Boolean, default: false},
    exportDisabled: {type: Boolean, default: false},
});
const emit = defineEmits(['place', 'select', 'export']);
const mode = defineModel('mode', {type: String, default: 'pins'});
const density = computed(() => props.densityEnabled && mode.value === 'density');
const canPlace = computed(() => props.placementEnabled && !props.disabled && !density.value);
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
    // A moved marker is drawn only at its new, unsaved location.
    return markers.value.filter((marker) => marker.location.sketch_id === sketchId
        && !(preview.value && marker.number === props.draftLocation?.number));
}

function hasSavedMarkers(sketchId) {
    return markers.value.some((marker) => marker.location.sketch_id === sketchId);
}

function exportTitle(sketch) {
    if (!hasSavedMarkers(sketch.id)) return 'Place a marker on this sketch first';
    return props.exportDisabled ? 'Save or cancel the current observation first' : 'Export as PNG';
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
    const bounds = event.currentTarget.querySelector('.he-diagram-map').getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
        warnPlacement();
        return;
    }
    const location = pointToLocation(sketch.id, event.clientX, event.clientY, bounds);
    placeOnAnatomy(location);
}

async function placeFromKeyboard(event, sketch) {
    if (!canPlace.value || !hitAreas.value[sketch.id]) return;
    const surface = event.currentTarget;
    if (!placeOnAnatomy(initialSketchLocation(hitAreas.value[sketch.id]))) return;
    await nextTick();
    surface.querySelector('[data-draft-marker]')?.focus();
}

function refinePreview(event, from = preview.value) {
    const steps = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]};
    if (!canPlace.value || !steps[event.key]) return;
    event.preventDefault();
    event.stopPropagation();
    const [dx, dy] = steps[event.key];
    const step = event.shiftKey ? 0.001 : 0.005;
    const location = offsetLocation(from, dx * step, dy * step);
    if (location && placeOnAnatomy(location)) {
        nextTick(() => {
            const draftMarker = panel.value?.querySelector('[data-draft-marker]');
            draftMarker?.focus();
            revealMarker(draftMarker);
        });
    }
}

function nudgeMarker(event, marker) {
    if (marker.number === props.editingNumber) refinePreview(event, marker.location);
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
const heatmaps = computed(() => buildHeatmaps(visibleSketches.value, markers.value));
const tabId = `${useId()}-anatomy`;
const pairRatio = computed(
    () => Math.max(...visibleSketches.value.map((sketch) => sketch.width / sketch.height)),
);

const panel = ref(null);
const zooms = shallowRef({});
const minDiagramHeight = 280;
const maxDiagramHeight = 900;
const diagramHeightStep = 20;
const requestedHeight = Number.isFinite(props.initialDiagramHeight) ? props.initialDiagramHeight : 560;
const roundedHeight = Math.round(requestedHeight / diagramHeightStep) * diagramHeightStep;
const diagramHeight = ref(Math.min(maxDiagramHeight, Math.max(minDiagramHeight, roundedHeight)));
const viewportSizes = shallowRef({});
const pans = shallowRef({});
const drag = shallowRef(null);
const zoomFor = (sketch) => zooms.value[sketch.id] ?? 1;
const helpId = `${tabId}-navigation-help`;
let resizeObserver;
let suppressedClick = null;

function viewportSize(sketch) {
    return viewportSizes.value[sketch.id] ?? {width: 0, height: diagramHeight.value};
}

function imageSize(sketch) {
    return fitDiagram(viewportSize(sketch), sketch.width / sketch.height, paired.value ? pairRatio.value : sketch.width / sketch.height);
}

function cameraFor(sketch) {
    return clampCamera({zoom: zoomFor(sketch), x: 0, y: 0, ...pans.value[sketch.id]}, viewportSize(sketch), imageSize(sketch));
}

function mapStyle(sketch) {
    const camera = cameraFor(sketch);
    const size = imageSize(sketch);
    return {
        width: size.width ? `${size.width}px` : '100%',
        transform: `translate(${camera.x}px, ${camera.y}px) scale(${camera.zoom})`,
        '--he-marker-scale': 1 / camera.zoom,
    };
}

function storePan(sketch, camera) {
    pans.value = {...pans.value, [sketch.id]: {x: camera.x, y: camera.y}};
}

function setZoom(sketch, value, anchor = {x: 0, y: 0}) {
    if (props.disabled || drag.value) return;
    const nextZoom = Math.max(1, Math.min(5, value));
    storePan(sketch, zoomCamera(cameraFor(sketch), nextZoom, anchor, viewportSize(sketch), imageSize(sketch)));
    zooms.value = {...zooms.value, [sketch.id]: nextZoom};
}

function resetView(sketch = null) {
    endPan();
    if (sketch) {
        zooms.value = {...zooms.value, [sketch.id]: 1};
        storePan(sketch, {x: 0, y: 0});
    } else {
        zooms.value = {};
        pans.value = {};
    }
}

function changeMode(value) {
    if (props.disabled) return;
    mode.value = value;
}

watch(mode, () => {
    endPan();
    dismissPlacementWarning();
});

function wheelZoom(event, sketch) {
    if (props.disabled) return;
    event.preventDefault();
    if (drag.value || !event.deltaY) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    setZoom(sketch, zoomFor(sketch) * wheelZoomFactor(event.deltaY, event.deltaMode, bounds.height), {
        x: event.clientX - bounds.left - bounds.width / 2,
        y: event.clientY - bounds.top - bounds.height / 2,
    });
}

function startPan(event, sketch) {
    suppressedClick = null;
    if (props.disabled || drag.value || event.pointerType !== 'mouse' || ![0, 2].includes(event.button)) return;
    const marker = event.target.closest('.he-marker');
    event.preventDefault();
    (event.button === 0 && marker ? marker : event.currentTarget).focus({preventScroll: true});
    drag.value = {
        element: event.currentTarget, pointerId: event.pointerId, sketch,
        startX: event.clientX, startY: event.clientY, camera: cameraFor(sketch), moved: false,
        marker, button: event.button,
    };
    event.currentTarget.setPointerCapture(event.pointerId);
}

function movePan(event) {
    const gesture = drag.value;
    if (!gesture || gesture.pointerId !== event.pointerId) return;
    const dx = event.clientX - gesture.startX;
    const dy = event.clientY - gesture.startY;
    if (!gesture.moved && Math.hypot(dx, dy) <= 5) return;
    drag.value = {...gesture, moved: true};
    storePan(gesture.sketch, panCamera(gesture.camera, dx, dy, viewportSize(gesture.sketch), imageSize(gesture.sketch)));
}

function endPan(event) {
    const gesture = drag.value;
    if (!gesture || (event && gesture.pointerId !== event.pointerId)) return;
    const markerClick = gesture.marker && gesture.button === 0 && !gesture.moved && event?.type === 'pointerup';
    if (markerClick || gesture.moved || event?.type === 'pointercancel' || event?.type === 'lostpointercapture') suppressedClick = gesture.sketch.id;
    drag.value = null;
    if (gesture.element.hasPointerCapture(gesture.pointerId)) gesture.element.releasePointerCapture(gesture.pointerId);
    if (markerClick && !props.disabled && gesture.marker.dataset.observation) {
        emit('select', Number(gesture.marker.dataset.observation));
    }
}

function stopDragClick(event, sketch) {
    if (suppressedClick !== sketch.id || event.detail === 0) return;
    event.preventDefault();
    event.stopPropagation();
    suppressedClick = null;
}

function keyboardViewport(event, sketch) {
    if (props.disabled || event.altKey || event.ctrlKey || event.metaKey) return;
    const steps = {ArrowLeft: [40, 0], ArrowRight: [-40, 0], ArrowUp: [0, 40], ArrowDown: [0, -40]};
    if (steps[event.key]) {
        event.preventDefault();
        const [dx, dy] = steps[event.key];
        storePan(sketch, panCamera(cameraFor(sketch), dx, dy, viewportSize(sketch), imageSize(sketch)));
    } else if (['+', '=', '-', '0', 'Home'].includes(event.key)) {
        event.preventDefault();
        if (event.key === '0' || event.key === 'Home') resetView(sketch);
        else setZoom(sketch, zoomFor(sketch) * (event.key === '-' ? 0.8 : 1.25));
    }
}

function observeViewports() {
    resizeObserver?.disconnect();
    if (!panel.value || typeof ResizeObserver === 'undefined') return;
    resizeObserver = new ResizeObserver((entries) => {
        const sizes = {...viewportSizes.value};
        for (const entry of entries) {
            sizes[entry.target.dataset.viewport] = {width: entry.target.clientWidth, height: entry.target.clientHeight};
        }
        viewportSizes.value = sizes;
        // Save the constrained offsets so enlarging again cannot resurrect an old pan.
        for (const sketch of visibleSketches.value) storePan(sketch, cameraFor(sketch));
    });
    panel.value.querySelectorAll('[data-viewport]').forEach((element) => resizeObserver.observe(element));
}

watch(() => selectedGroup.value.id, () => {
    resetView();
    observeViewports();
}, {flush: 'post'});
watch(() => props.disabled, (disabled) => { if (disabled) endPan(); });
onMounted(observeViewports);
onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    endPan();
});

function revealMarker(marker) {
    const element = marker?.closest('[data-viewport]');
    if (!element) return;
    const bounds = element.getBoundingClientRect();
    const pin = marker.getBoundingClientRect();
    if (pin.left >= bounds.left && pin.right <= bounds.right && pin.top >= bounds.top && pin.bottom <= bounds.bottom) return;
    const sketch = visibleSketches.value.find((item) => item.id === element.dataset.viewport);
    storePan(sketch, panCamera(cameraFor(sketch), bounds.left + bounds.width / 2 - (pin.left + pin.width / 2), bounds.top + bounds.height / 2 - (pin.top + pin.height / 2), viewportSize(sketch), imageSize(sketch)));
}

watch(() => props.selectedNumber, async () => {
    if (props.selectedNumber != null) mode.value = 'pins';
    await nextTick();
    revealMarker(panel.value?.querySelector(`[data-observation="${props.selectedNumber}"]`));
});

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
            <div class="he-view-heading">
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
                <div v-if="$slots.actions" class="he-view-actions"><slot name="actions"/></div>
            </div>
        </div>

        <div class="he-navigation" role="group" aria-label="Diagram navigation">
            <div v-if="densityEnabled" class="he-mode-toggle" role="group" aria-label="Map display">
                <button type="button" aria-label="Pins" :aria-pressed="!density" :disabled="disabled" @click="changeMode('pins')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg> Pins
                </button>
                <button type="button" aria-label="Density" :aria-pressed="density" :disabled="disabled" @click="changeMode('density')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c2 5-2 6 1 9 2-1 3-3 3-3 5 5 4 12-4 12S2 13 7 8c0 3 1 4 2 4-1-5 3-6 3-9Z"/></svg> Density
                </button>
            </div>
            <label class="he-height-control" :for="`${tabId}-height`">
                View height
                <input
                    :id="`${tabId}-height`"
                    v-model.number="diagramHeight"
                    type="range"
                    :min="minDiagramHeight"
                    :max="maxDiagramHeight"
                    :step="diagramHeightStep"
                    aria-label="View height"
                    :aria-valuetext="`${diagramHeight} pixels`"
                    :style="{ '--he-height-progress': `${(diagramHeight - minDiagramHeight) / (maxDiagramHeight - minDiagramHeight) * 100}%` }"
                    :disabled="disabled"
                />
                <output :for="`${tabId}-height`">{{ diagramHeight }} px</output>
            </label>
        </div>
        <p :id="helpId" class="he-navigation-help">
            Scroll to zoom. Drag or right-drag to pan. Keyboard: +/− to zoom, arrow keys to pan, 0 to reset.
        </p>
        <p v-if="density" class="he-placement-help">Observation density by location. Switch to Pins to place or select markers.</p>
        <p v-if="canPlace" class="he-placement-help">
            <template v-if="editingNumber != null">
                Click inside an anatomical outline to move marker {{ editingNumber }}, or focus it and use the arrow keys.
            </template>
            <template v-else>
                Click inside an anatomical outline to place a marker. For keyboard placement, focus a sketch and press
                Enter.
            </template>
        </p>

        <div
            :id="`${tabId}-panel`"
            ref="panel"
            class="he-view-panel he-diagrams"
            :class="{ 'he-diagrams--paired': paired }"
            :style="{ '--he-pair-ratio': pairRatio, '--he-diagram-height': `${diagramHeight}px` }"
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
                        class="he-diagram-viewport"
                        :class="{ 'he-diagram-viewport--dragging': drag?.sketch.id === sketch.id && drag.moved }"
                        :data-viewport="sketch.id"
                        role="group"
                        :tabindex="disabled ? -1 : 0"
                        :aria-label="`${sketch.label} zoomable viewport`"
                        :aria-describedby="helpId"
                        @wheel="wheelZoom($event, sketch)"
                        @contextmenu.prevent
                        @pointerdown="startPan($event, sketch)"
                        @pointermove="movePan"
                        @pointerup="endPan"
                        @pointercancel="endPan"
                        @lostpointercapture="endPan"
                        @click.capture="stopDragClick($event, sketch)"
                        @click="placeFromPointer($event, sketch)"
                        @keydown.self="keyboardViewport($event, sketch)"
                        @focusin="revealMarker($event.target.closest('.he-marker'))"
                    >
                        <div
                            class="he-diagram-map"
                            :class="{ 'he-diagram-map--placing': canPlace && hitAreas[sketch.id] }"
                            :style="mapStyle(sketch)"
                            role="group"
                            :tabindex="canPlace && hitAreas[sketch.id] ? 0 : -1"
                            :aria-label="`${sketch.label} map.${canPlace && hitAreas[sketch.id] ? ' Press Enter or Space to place a marker inside the anatomical outline.' : ''}`"
                            :aria-disabled="disabled"
                            :aria-busy="canPlace && !hitAreas[sketch.id] && !outlineErrors[sketch.id]"
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
                            <ObservationHeatmap v-if="density" :sketch="sketch" :map="heatmaps.maps[sketch.id]" :peak="heatmaps.peak" :area="hitAreas[sketch.id]"/>
                            <button
                                v-for="marker in density ? [] : markersFor(sketch.id)"
                                :key="marker.number"
                                type="button"
                                class="he-marker"
                                :class="{ 'he-marker--selected': selectedNumber === marker.number }"
                                :data-observation="marker.number"
                                :style="{ ...position(marker.location), ...observationStyle(marker.type, markerColors) }"
                                :aria-label="`Observation ${marker.number}, ${observationType(marker.type)}${marker.label ? `: ${marker.label}` : ''}${marker.number === editingNumber && canPlace ? '. Arrow keys move it.' : ''}`"
                                :aria-pressed="selectedNumber === marker.number"
                                :disabled="disabled"
                                @click.stop="emit('select', marker.number)"
                                @keydown="nudgeMarker($event, marker)"
                            >
                                <span aria-hidden="true">{{ marker.number }}</span>
                            </button>
                            <button
                                v-if="!density && preview?.sketch_id === sketch.id"
                                type="button"
                                class="he-marker he-marker--draft"
                                data-draft-marker
                                :style="{ ...position(preview), ...observationStyle(draftLocation.type, markerColors) }"
                                :aria-label="previewLabel"
                                :disabled="!canPlace"
                                @click.stop
                                @keydown="refinePreview"
                            >
                                <span aria-hidden="true">{{ draftLocation.number ?? '+' }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="he-zoom-controls" role="group" :aria-label="`${sketch.label} zoom controls`">
                        <button type="button" aria-label="Zoom out" title="Zoom out" :disabled="disabled || zoomFor(sketch) <= 1" @click="setZoom(sketch, zoomFor(sketch) * 0.8)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5M7.5 10.5h6"/></svg>
                        </button>
                        <output aria-label="Zoom level">{{ Math.round(zoomFor(sketch) * 100) }}%</output>
                        <button type="button" aria-label="Zoom in" title="Zoom in" :disabled="disabled || zoomFor(sketch) >= 5" @click="setZoom(sketch, zoomFor(sketch) * 1.25)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5M7.5 10.5h6m-3-3v6"/></svg>
                        </button>
                        <button type="button" aria-label="Reset view" title="Reset this view" :disabled="disabled" @click="resetView(sketch)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10a9 9 0 1 1 2.6 8.4M3 4v6h6"/></svg>
                        </button>
                        <button
                            v-if="exportEnabled && !density"
                            type="button"
                            :aria-label="`Export ${sketch.label} as PNG`"
                            :title="exportTitle(sketch)"
                            :disabled="disabled || exportDisabled || !hasSavedMarkers(sketch.id)"
                            @click="emit('export', sketch)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m-4.5-4.5L12 15l4.5-4.5M5 19.5h14"/></svg>
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
                <p v-if="(canPlace || density) && outlineErrors[sketch.id]" class="he-diagram-error" role="alert">
                    {{ density ? 'Heatmap' : 'Marker placement' }} is unavailable for this view. Reload to try again.
                </p>
            </figure>
        </div>

        <div v-if="density" class="he-legend he-density-legend" role="group" aria-label="Density legend">
            <span class="he-legend__title">Relative density</span>
            <div class="he-density-scale"><span>Lower</span><span class="he-density-gradient" aria-hidden="true"></span><span>Higher</span></div>
            <p v-if="heatmaps.count">{{ heatmaps.count }} mapped observations in these views. Overlapping locations appear warmer.</p>
            <p v-else>No mapped observations in these views.</p>
            <p>Mapped observations only; draft marker previews are excluded. Colors show concentration, not observation type or severity.</p>
        </div>
        <div v-else class="he-legend" role="group" aria-label="Observation type legend">
            <span class="he-legend__title">Observation types</span>
            <ul v-if="legendTypes.length" class="he-legend__items">
                <li v-for="type in legendTypes" :key="type" :style="observationStyle(type, markerColors)">
                    <span class="he-legend__swatch" aria-hidden="true"></span>
                    {{ type }}
                </li>
            </ul>
            <p v-else>Your observations will appear here.</p>
        </div>

        <!-- The workspace's observation table -->
        <slot name="after-legend"/>

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

.he-view-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    min-width: 0;
}

.he-view-actions { margin-left: auto; }

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
    position: relative;
    width: 100%;
    margin: 0 auto;
}

.he-diagram-viewport {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: content-box;
    height: var(--he-diagram-height);
    overflow: clip;
    background: #fff;
    border: 1px solid var(--he-border, #e5e5e5);
    border-radius: 0.375rem;
    cursor: grab;
    user-select: none;
    touch-action: pan-y;
}

.he-diagram-viewport--dragging,
.he-diagram-viewport--dragging .he-diagram-map {
    cursor: grabbing;
}

.he-diagram-viewport:focus-visible {
    outline: 2px solid var(--he-ring, #a3a3a3);
    outline-offset: 2px;
}

.he-navigation {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    margin-top: 1rem;
    font-size: 0.8125rem;
}

.he-mode-toggle {
    display: inline-flex;
    gap: 0.125rem;
    padding: 0.25rem;
    background: var(--he-muted, #f5f5f5);
    border-radius: 0.5rem;
}
.he-mode-toggle button {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    border: 0;
    border-radius: 0.375rem;
    background: transparent;
    color: var(--he-muted-foreground, #737373);
    font: inherit;
    cursor: pointer;
}
.he-mode-toggle button[aria-pressed="true"] {
    background: var(--he-background, #fff);
    color: var(--he-foreground, #171717);
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
}
.he-mode-toggle button:disabled { opacity: 0.45; cursor: default; }
.he-mode-toggle button:focus-visible { outline: 2px solid var(--he-ring, #a3a3a3); outline-offset: 2px; }
.he-mode-toggle svg { width: 1rem; height: 1rem; }
.he-density-scale { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.75rem; }
.he-density-gradient { width: 10rem; max-width: 45%; height: 0.5rem; border-radius: 999px; background: linear-gradient(to right, #2563eb, #06b6d4, #facc15, #f97316, #dc2626); }

.he-zoom-controls,
.he-height-control {
    display: flex;
    margin-inline-start: auto;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.he-zoom-controls {
    position: absolute;
    top: 0.625rem;
    right: 0.625rem;
    z-index: 3;
    gap: 0.125rem;
    padding: 0.25rem;
    border: 1px solid var(--he-border, #e5e5e5);
    border-radius: 0.625rem;
    background: var(--he-background, #fff);
    box-shadow: 0 2px 8px rgb(0 0 0 / 0.07);
    font-size: 0.75rem;
}

.he-zoom-controls button {
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 0.375rem;
    background: var(--he-background, #fff);
    color: inherit;
    font: inherit;
    cursor: pointer;
}

.he-zoom-controls svg {
    width: 1.125rem;
    height: 1.125rem;
}

.he-zoom-controls button:hover:not(:disabled) {
    background: var(--he-muted, #f5f5f5);
}

.he-zoom-controls button:disabled {
    opacity: 0.45;
    cursor: default;
}

.he-zoom-controls button:focus-visible,
.he-height-control input:focus-visible {
    outline: 2px solid var(--he-ring, #a3a3a3);
    outline-offset: 2px;
}

.he-zoom-controls output {
    min-width: 3em;
    text-align: center;
    font-variant-numeric: tabular-nums;
}

.he-height-control input {
    appearance: none;
    -webkit-appearance: none;
    width: 9rem;
    max-width: 30vw;
    height: 0.375rem;
    margin: 0 0.25rem;
    min-width: 0;
    border: 0;
    border-radius: 999px;
    background: linear-gradient(to right, var(--he-foreground, #404040) var(--he-height-progress), var(--he-border, #e5e5e5) var(--he-height-progress));
    cursor: pointer;
}

.he-height-control input::-webkit-slider-thumb {
    appearance: none;
    -webkit-appearance: none;
    width: 1rem;
    height: 1rem;
    border: 2px solid var(--he-background, #fff);
    border-radius: 50%;
    background: var(--he-foreground, #404040);
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.15), 0 1px 3px rgb(0 0 0 / 0.15);
}

.he-height-control input::-moz-range-thumb {
    width: 0.75rem;
    height: 0.75rem;
    border: 2px solid var(--he-background, #fff);
    border-radius: 50%;
    background: var(--he-foreground, #404040);
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.15), 0 1px 3px rgb(0 0 0 / 0.15);
}

.he-height-control input::-moz-range-track {
    background: transparent;
}

.he-height-control input:disabled {
    opacity: 0.45;
    cursor: default;
}

.he-height-control output {
    min-width: 3.5em;
    font-variant-numeric: tabular-nums;
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
    flex-shrink: 0;
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
    transform: translate(-50%, -50%) scale(var(--he-marker-scale, 1));
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
.he-navigation-help,
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
