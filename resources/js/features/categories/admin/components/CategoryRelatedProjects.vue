<template>
    <section v-if="projects?.length" class="ac-related-records"><h3>Related projects</h3><ul><li v-for="project in projects" :key="project.id">{{ project.name }} <span>· {{ project.status }}</span></li></ul></section>
</template>

<script setup>
defineProps({ projects: { type: Array, default: () => [] } });
</script>
