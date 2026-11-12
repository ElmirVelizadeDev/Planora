<template>
    <div v-if="projects?.length"><h3>Your projects</h3><ul><li v-for="project in projects" :key="project.id"><RouterLink :to="{ name: 'projects.show', params: { projectId: project.id } }">{{ project.name }}</RouterLink><span>{{ project.status }}</span></li></ul></div>
</template>

<script setup>
defineProps({ projects: { type: Array, default: () => [] } });
</script>
