<template>
    <section class="report-chart-card report-trend-card" :aria-label="`${title} chart`">
        <header class="report-chart-card__header"><div><h3>{{ title }}</h3><p>{{ description }}</p></div><span class="report-chart-card__key"><i :style="{ backgroundColor: color }"></i>{{ seriesLabel }}</span></header>
        <div v-if="!hasData" class="report-chart-empty">No records in this period.</div>
        <svg v-else class="report-trend-chart" viewBox="0 0 640 230" role="img" :aria-label="`${title}, ${series.length} daily data points`">
            <g class="report-chart-grid"><line v-for="y in [36, 78, 120, 162]" :key="y" x1="32" :y1="y" x2="620" :y2="y" /></g>
            <text v-for="tick in yTicks" :key="tick.y" class="report-chart-axis-label" x="26" :y="tick.y + 4" text-anchor="end">{{ format(tick.value) }}</text>
            <polyline :points="points" fill="none" :stroke="color" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <circle v-for="point in visiblePoints" :key="point.date" :cx="point.x" :cy="point.y" r="3.5" :fill="color"><title>{{ point.date }}: {{ format(point.value) }}</title></circle>
            <text v-for="tick in xTicks" :key="tick.label" class="report-chart-axis-label" :x="tick.x" y="198" :text-anchor="tick.anchor">{{ tick.label }}</text>
        </svg>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    seriesLabel: { type: String, default: 'Created' },
    series: { type: Array, default: () => [] },
    color: { type: String, default: '#5B6CFA' },
});
const hasData = computed(() => props.series.some((point) => Number(point.value) > 0));
const max = computed(() => Math.max(1, ...props.series.map((point) => Number(point.value) || 0)));
const min = computed(() => Math.min(0, ...props.series.map((point) => Number(point.value) || 0)));
const range = computed(() => Math.max(1, max.value - min.value));
const mapped = computed(() => props.series.map((point, index) => ({
    ...point,
    x: props.series.length < 2 ? 326 : 34 + (index / (props.series.length - 1)) * 584,
    y: 162 - (((Number(point.value) || 0) - min.value) / range.value) * 126,
})));
const points = computed(() => mapped.value.map((point) => `${point.x},${point.y}`).join(' '));
const visiblePoints = computed(() => mapped.value.filter((_, index) => props.series.length <= 40 || index % Math.ceil(props.series.length / 32) === 0 || index === props.series.length - 1));
const yTicks = computed(() => {
    const values = [...new Set([0, .33, .66, 1].map((ratio) => Math.round(min.value + range.value * ratio)))];
    return values.map((value) => ({ y: 162 - ((value - min.value) / range.value) * 126, value }));
});
const xTicks = computed(() => {
    if (!props.series.length) return [];
    const indexes = [...new Set([0, Math.floor((props.series.length - 1) / 2), props.series.length - 1])];
    return indexes.map((index, position) => ({
        x: mapped.value[index]?.x ?? 34,
        label: new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${props.series[index]?.date}T12:00:00Z`)),
        anchor: position === 0 ? 'start' : position === indexes.length - 1 ? 'end' : 'middle',
    }));
});
function format(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
</script>
