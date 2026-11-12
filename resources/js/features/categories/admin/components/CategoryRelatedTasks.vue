<template>
    <section v-if="tasks?.length" class="ac-related-records"><h3>Related tasks</h3><ul><li v-for="task in tasks" :key="task.id">{{ task.title }} <span>· {{ task.status }}</span></li></ul></section>
</template>

<script setup>
defineProps({ tasks: { type: Array, default: () => [] } });
</script>
