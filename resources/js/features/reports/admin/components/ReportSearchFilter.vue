<template>
    <label ref="filterRoot" class="report-search-filter">
        <span>{{ label }}</span>
        <span class="report-search-filter__control">
            <input
                v-model="query"
                type="search"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listId"
                :placeholder="placeholder"
                autocomplete="off"
                @focus="openList"
                @input="search"
                @keydown.enter.prevent="chooseFirst"
                @keydown.esc="open = false"
            >
            <button v-if="modelValue" type="button" class="report-search-filter__clear" :aria-label="`Clear ${label} filter`" @click="clear">×</button>
            <span v-if="open" :id="listId" class="report-search-filter__options" role="listbox">
                <template v-if="!loading && !error"><button v-for="option in options" :key="option.id" type="button" role="option" @mousedown.prevent @click="choose(option)">
                    <span>{{ option.name }}</span><small>{{ option.status || option.role || '' }}</small>
                </button></template>
                <span v-if="loading" class="report-search-filter__empty" role="status">Searching…</span>
                <span v-else-if="error" class="report-search-filter__empty" role="alert">{{ error }} <button type="button" @mousedown.prevent @click="emit('search', filterType, query)">Retry</button></span>
                <span v-else-if="!options.length" class="report-search-filter__empty">No matches found</span>
            </span>
        </span>
    </label>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    placeholder: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    filterType: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue', 'search']);
const query = ref('');
const open = ref(false);
const filterRoot = ref(null);
const listId = computed(() => `report-filter-${props.filterType}-options`);

watch(() => props.modelValue, (id) => {
    if (!id) {
        query.value = '';
        return;
    }
    const selected = props.options.find((option) => String(option.id) === String(id));
    if (selected) query.value = selected.name;
});
watch(() => props.options, (options) => {
    const selected = options.find((option) => String(option.id) === String(props.modelValue));
    if (selected) query.value = selected.name;
}, { deep: true });

function openList() {
    open.value = true;
    emit('search', props.filterType, query.value);
}
function search() {
    open.value = true;
    emit('update:modelValue', '');
    emit('search', props.filterType, query.value);
}
function choose(option) {
    query.value = option.name;
    open.value = false;
    emit('update:modelValue', option.id);
}
function chooseFirst() {
    if (options.length) choose(options[0]);
}
function clear() {
    query.value = '';
    open.value = false;
    emit('update:modelValue', '');
    emit('search', props.filterType, '');
}
function closeOnOutsidePointer(event) {
    if (!filterRoot.value?.contains(event.target)) open.value = false;
}
onMounted(() => document.addEventListener('pointerdown', closeOnOutsidePointer));
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutsidePointer));
</script>
