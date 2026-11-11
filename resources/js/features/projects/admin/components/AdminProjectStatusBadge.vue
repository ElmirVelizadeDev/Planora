<template><span class="admin-projects-badge" :class="`is-${status || 'unknown'}`">{{ label }}</span></template>

<script setup>
import { computed } from 'vue';
import { ADMIN_PROJECT_STATUSES } from '../composables/useAdminProjects.js';

const props = defineProps({ status: { type: String, default: '' } });
const label = computed(() => ADMIN_PROJECT_STATUSES.find(item => item.value === props.status)?.label || props.status || 'Unknown');
</script>
