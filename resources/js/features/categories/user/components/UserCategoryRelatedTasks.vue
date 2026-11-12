<template>
    <div v-if="tasks?.length"><h3>Tasks you can access</h3><ul><li v-for="task in tasks" :key="task.id"><RouterLink :to="{ name: 'tasks.show', params: { taskId: task.id } }">{{ task.title }}</RouterLink><span>{{ task.status }}</span></li></ul></div>
</template>

<script setup>
defineProps({ tasks: { type: Array, default: () => [] } });
</script>
