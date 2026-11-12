<template>
    <footer class="ac-pagination">
        <p>Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ total }}</strong> categories</p>
        <div><button type="button" aria-label="Previous page" :disabled="page <= 1 || loading" @click="$emit('page', page - 1)">‹</button><span>Page <strong>{{ page }}</strong> of {{ lastPage }}</span><button type="button" aria-label="Next page" :disabled="page >= lastPage || loading" @click="$emit('page', page + 1)">›</button></div>
    </footer>
</template>

<script setup>
defineProps({ rangeStart: Number, rangeEnd: Number, total: Number, page: Number, lastPage: Number, loading: Boolean });
defineEmits(['page']);
</script>
