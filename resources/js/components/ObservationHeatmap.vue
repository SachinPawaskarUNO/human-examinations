<script setup>
import {onMounted, ref, watch} from 'vue';
import {densityColor} from '../observationDensity.js';

const props = defineProps({map: {type: Object, required: true}, peak: {type: Number, required: true}, area: {type: Object, default: null}, sketch: {type: Object, required: true}});
const canvas = ref(null);
function paint() {
    const context = canvas.value?.getContext('2d');
    if (!context) return;
    const {width, height, values} = props.map;
    const pixels = context.createImageData(width, height);
    if (props.area && props.peak > 0) {
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const index = y * width + x;
                if (!values[index] || !props.area.contains((x + 0.5) / width, (y + 0.5) / height)) continue;
                pixels.data.set(densityColor(values[index], props.peak), index * 4);
            }
        }
    }
    context.putImageData(pixels, 0, 0);
}
onMounted(paint);
watch([() => props.map, () => props.peak, () => props.area], paint, {flush: 'post'});
</script>

<template>
    <canvas ref="canvas" class="he-heatmap" :width="map.width" :height="map.height" :data-heatmap="sketch.id" :data-count="map.count" role="img" :aria-busy="!area" :aria-label="`${sketch.label}: ${map.count} mapped observations. Relative observation density.`"/>
</template>

<style scoped>
.he-heatmap {
    position: absolute;
    inset: 0;
    z-index: 1;
    width: 100%;
    height: 100%;
    pointer-events: none;
    mix-blend-mode: multiply;
}
</style>
