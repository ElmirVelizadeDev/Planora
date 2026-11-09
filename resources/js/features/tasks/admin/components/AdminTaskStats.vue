<template>
    <section class="admin-tasks-stat-grid" aria-label="Task statistics">
        <article v-for="stat in stats" :key="stat.key" class="admin-tasks-stat-card">
            <span class="admin-tasks-stat-icon" :class="`is-${stat.tone}`" aria-hidden="true">
                <svg v-if="stat.icon === 'total'" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>
                <svg v-else-if="stat.icon === 'progress'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <svg v-else-if="stat.icon === 'complete'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                <svg v-else-if="stat.icon === 'pending'" viewBox="0 0 24 24"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/><circle cx="12" cy="12" r="4"/></svg>
                <svg v-else viewBox="0 0 24 24"><path d="M12 8v5m0 3h.01"/><path d="M10.3 4.9 2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.9a2 2 0 0 0-3.4 0Z"/></svg>
            </span>
            <strong>{{ formatNumber(stat.value) }}</strong><span>{{ stat.label }}</span>
        </article>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ statistics: { type: Object, required: true } });
const stats = computed(() => [
    { key: 'total', label: 'Total tasks', value: props.statistics.total, icon: 'total', tone: 'primary' },
    { key: 'in_progress', label: 'In progress', value: props.statistics.in_progress, icon: 'progress', tone: 'violet' },
    { key: 'completed', label: 'Completed', value: props.statistics.completed, icon: 'complete', tone: 'success' },
    { key: 'pending', label: 'Pending', value: props.statistics.pending, icon: 'pending', tone: 'neutral' },
    { key: 'overdue', label: 'Overdue', value: props.statistics.overdue, icon: 'overdue', tone: 'danger' },
]);

function formatNumber(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
</script>
