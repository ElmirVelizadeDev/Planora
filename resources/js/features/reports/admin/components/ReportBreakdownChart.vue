<template>
    <section class="report-chart-card report-breakdown-card">
        <header class="report-chart-card__header"><div><h3>{{ title }}</h3><p>{{ description }}</p></div><span v-if="total > 0 || showZeroItems" class="report-chart-total">{{ format(total) }}<small>shown</small></span></header>
        <div v-if="!items.length || (total === 0 && !showZeroItems)" class="report-chart-empty">No records in this period.</div>
        <ul v-else class="report-breakdown-list">
            <li v-for="(item, index) in items" :key="item.key || item.id || item.label || item.name" :title="`${item.label || item.name}: ${format(item.value)}`">
                <div class="report-breakdown-label"><i :style="{ backgroundColor: item.color || palette[index % palette.length] }"></i><span>{{ item.label || item.name }}<small v-if="item.status === 'archived'">Archived</small></span><strong>{{ format(item.value) }}</strong></div>
                <div class="report-breakdown-track"><span :style="{ width: `${Math.max(0, Math.min(100, (Number(item.value) / max) * 100))}%`, minWidth: Number(item.value) > 0 ? '2px' : '0', backgroundColor: item.color || palette[index % palette.length] }"></span></div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    items: { type: Array, default: () => [] },
    showZeroItems: { type: Boolean, default: false },
});
const palette = ['#5B6CFA', '#2F9B78', '#E5A23B', '#8D70D6', '#E16D78', '#3C9DB5'];
const total = computed(() => props.items.reduce((sum, item) => sum + (Number(item.value) || 0), 0));
const max = computed(() => Math.max(1, ...props.items.map((item) => Number(item.value) || 0)));
function format(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
</script>
