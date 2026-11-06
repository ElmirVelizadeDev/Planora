<template>
    <span class="user-avatar" :class="{ 'has-image': imageSrc }" :aria-label="`${name} avatar`">
        <img v-if="imageSrc" :src="imageSrc" :alt="`${name} avatar`" />
        <span v-else aria-hidden="true">{{ initials }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: 'Planora User' },
    imageSrc: { type: String, default: '' },
});

const initials = computed(() => props.name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'P');
</script>

<style src="../../../css/components/UserAvatar.css"></style>
