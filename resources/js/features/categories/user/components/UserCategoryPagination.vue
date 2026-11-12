<template>
    <footer v-if="lastPage > 1 && !error" class="user-categories-pagination">
        <span>Showing {{ rangeStart }}–{{ rangeEnd }} of {{ total }} categories</span>
        <div><button type="button" :disabled="page <= 1 || loading" aria-label="Previous page" @click="$emit('page', page - 1)">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button type="button" :disabled="page >= lastPage || loading" aria-label="Next page" @click="$emit('page', page + 1)">Next</button></div>
    </footer>
</template>

<script setup>
defineProps({ lastPage: Number, error: { type: String, default: '' }, rangeStart: Number, rangeEnd: Number, total: Number, page: Number, loading: Boolean });
defineEmits(['page']);
</script>
