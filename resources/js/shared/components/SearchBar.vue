<template>
    <form class="workspace-search" role="search" @submit.prevent="$emit('search', query.trim())">
        <span class="workspace-search-icon" aria-hidden="true" />
        <input v-model="query" type="search" placeholder="Search your workspace" aria-label="Search your workspace" />
        <kbd aria-hidden="true">⌘ K</kbd>
    </form>
</template>

<script setup>
import { ref } from 'vue';

defineEmits(['search']);

const query = ref('');
</script>

<style src="../../../css/components/SearchBar.css"></style>
