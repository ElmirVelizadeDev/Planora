<template>
    <article class="report-kpi-card" :class="`is-${tone}`">
        <div class="report-kpi-card__heading"><span>{{ label }}</span><span class="report-kpi-card__icon" aria-hidden="true">{{ icon }}</span></div>
        <strong>{{ formattedValue }}</strong>
        <div class="report-kpi-card__foot">
            <span>{{ description }}</span>
            <span v-if="growth !== undefined" class="report-kpi-card__trend" :class="trendClass">{{ trendLabel }}</span>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: 0 },
    description: { type: String, required: true },
    growth: { type: Number, default: undefined },
    icon: { type: String, default: '•' },
    tone: { type: String, default: 'primary' },
    percent: { type: Boolean, default: false },
});
const formattedValue = computed(() => {
    if (props.value == null) return '—';
    return props.percent
        ? `${Number(props.value).toLocaleString(undefined, { maximumFractionDigits: 1 })}%`
        : new Intl.NumberFormat().format(Number(props.value || 0));
});
const trendClass = computed(() => props.growth == null ? 'is-neutral' : props.growth > 0 ? 'is-positive' : props.growth < 0 ? 'is-negative' : 'is-neutral');
const trendLabel = computed(() => props.growth == null ? 'No comparison' : `${props.growth > 0 ? '↑' : props.growth < 0 ? '↓' : '→'} ${Math.abs(props.growth)}% vs prior period`);
</script>
