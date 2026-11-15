<script setup>
import { computed, ref, watch } from 'vue';
import { sketchGroups, sketches } from '../sketches.js';

const view = defineModel('view', { type: String, default: 'body-front' });
const selected = computed(
    () => sketches.find((sketch) => sketch.id === view.value) ?? sketches[0],
);
const paired = computed(() => ['body-front', 'body-back'].includes(selected.value.id));
const visibleSketches = computed(() => paired.value
    ? sketchGroups.find((group) => group.id === 'body').views
    : [selected.value],
);
const pairRatio = computed(
    () => Math.max(...visibleSketches.value.map((sketch) => sketch.width / sketch.height)),
);
const selectorGroups = sketchGroups.map((group) => group.id === 'body'
    ? { ...group, views: [{ ...group.views[0], label: 'Body · Front and back' }] }
    : group,
);
const selectedId = computed({
    get: () => paired.value ? 'body-front' : selected.value.id,
    set: (id) => {
        view.value = id;
    },
});
const failed = ref({});

watch(
    () => selected.value.id,
    () => { failed.value = {}; },
);
</script>

<template>
    <div class="he-anatomy">
        <label class="he-view-picker">
            <span>Anatomical view</span>
            <select v-model="selectedId" class="he-view-select">
                <optgroup
                    v-for="group in selectorGroups"
                    :key="group.id"
                    :label="group.label"
                >
                    <option
                        v-for="sketch in group.views"
                        :key="sketch.id"
                        :value="sketch.id"
                    >
                        {{ sketch.label }}
                    </option>
                </optgroup>
            </select>
        </label>

        <div
            class="he-diagrams"
            :class="{ 'he-diagrams--paired': paired }"
            :style="{ '--he-pair-ratio': pairRatio }"
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
                    <img
                        :src="sketch.src"
                        :alt="`${sketch.label} anatomical sketch`"
                        draggable="false"
                        @error="failed[sketch.id] = true"
                    />
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
            </figure>
        </div>

        <p class="he-anatomy-note">Anatomical left and right refer to the subject.</p>
    </div>
</template>

<style scoped>
.he-anatomy {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    padding: 1rem;
    color: var(--he-foreground, #0a0a0a);
}

.he-view-picker {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
    font-size: 0.875rem;
    font-weight: 500;
}

.he-view-select {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    padding: 0.5rem;
    border: 1px solid var(--he-border, #e5e5e5);
    border-radius: 0.375rem;
    background: var(--he-background, #fff);
    color: inherit;
    font: inherit;
}

.he-view-select:focus-visible {
    outline: 2px solid var(--he-ring, #a3a3a3);
    outline-offset: 2px;
}

.he-diagrams {
    display: grid;
    gap: 1.5rem;
    margin: 1rem 0 0;
}

.he-diagrams--paired {
    grid-template-columns: repeat(auto-fit, minmax(min(12rem, 100%), 1fr));
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

.he-diagram-sides {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.he-anatomy-note,
.he-diagram-error {
    margin: 1rem 0 0;
    color: var(--he-muted-foreground, #737373);
    font-size: 0.8125rem;
    text-align: center;
}
</style>
