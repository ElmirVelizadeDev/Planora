<template>
    <section class="admin-projects-stat-grid" aria-label="Project statistics">
        <article v-for="stat in stats" :key="stat.key" class="admin-projects-stat-card">
            <span class="admin-projects-stat-icon" :class="`is-${stat.tone}`" aria-hidden="true">
                <svg v-if="stat.icon === 'total'" viewBox="0 0 24 24"><path d="M4 5.5h6v6H4zM14 5.5h6v6h-6zM4 15.5h6v4H4zM14 15.5h6v4h-6z"/></svg>
                <svg v-else-if="stat.icon === 'active'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <svg v-else-if="stat.icon === 'completed'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                <svg v-else viewBox="0 0 24 24"><path d="M9 5v14m6-14v14"/></svg>
            </span>
            <strong>{{ formatNumber(stat.value) }}</strong><span>{{ stat.label }}</span>
        </article>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ statistics: { type: Object, required: true } });
const stats = computed(() => [
    { key: 'total', label: 'Total projects', value: props.statistics.total, icon: 'total', tone: 'primary' },
    { key: 'active', label: 'Active projects', value: props.statistics.active, icon: 'active', tone: 'success' },
    { key: 'completed', label: 'Completed projects', value: props.statistics.completed, icon: 'completed', tone: 'violet' },
    { key: 'on_hold', label: 'On hold', value: props.statistics.on_hold, icon: 'hold', tone: 'neutral' },
]);

function formatNumber(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
</script>
