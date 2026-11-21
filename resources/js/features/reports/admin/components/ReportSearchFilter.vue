<template>
    <label ref="filterRoot" class="report-search-filter">
        <span>{{ label }}</span>
        <span class="report-search-filter__control">
            <input
                v-model="query"
                type="search"
                ref="trigger"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listId"
                :aria-activedescendant="open && options.length ? optionId(activeIndex) : undefined"
                :placeholder="placeholder"
                autocomplete="off"
                @focus="openList"
                @input="search"
                @keydown="onKeydown"
            >
            <button v-if="modelValue" type="button" class="report-search-filter__clear" :aria-label="`Clear ${label} filter`" @click="clear">×</button>
        </span>
    </label>
    <Teleport to="body">
        <span v-if="open" :id="listId" ref="optionsMenu" :style="optionsStyle" class="report-search-filter__options" role="listbox">
            <template v-if="!loading && !error"><button v-for="(option, index) in options" :id="optionId(index)" :key="option.id" type="button" role="option" :aria-selected="activeIndex === index" @mouseenter="activeIndex = index" @mousedown.prevent @click="choose(option)">
                <span>{{ option.name }}</span><small>{{ option.status || option.role || '' }}</small>
            </button></template>
            <span v-if="loading" class="report-search-filter__empty" role="status">Searching…</span>
            <span v-else-if="error" class="report-search-filter__empty" role="alert">{{ error }} <button type="button" @mousedown.prevent @click="emit('search', filterType, query)">Retry</button></span>
            <span v-else-if="!options.length" class="report-search-filter__empty">No matches found</span>
        </span>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { getAnchoredPopoverStyle } from '../../../../shared/composables/getAnchoredPopoverStyle.js';

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
const trigger = ref(null);
const optionsMenu = ref(null);
const optionsStyle = ref({});
const activeIndex = ref(0);
let listenersAttached = false;
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

watch(() => props.options, async (options) => {
    activeIndex.value = Math.max(0, Math.min(activeIndex.value, options.length - 1));
    if (open.value) {
        await nextTick();
        positionOptions();
    }
}, { deep: true });

function optionId(index) { return `${listId.value}-option-${index}`; }
function positionOptions() {
    if (!open.value || !trigger.value || !optionsMenu.value) return;
    const rect = trigger.value.getBoundingClientRect();
    optionsStyle.value = getAnchoredPopoverStyle(trigger.value, {
        width: Math.max(rect.width, 280),
        maxHeight: 260,
        gutter: 8,
        gap: 5,
        alignment: 'start',
        viewportAware: true,
    });
}
function addPositionListeners() {
    if (listenersAttached) return;
    listenersAttached = true;
    window.addEventListener('resize', positionOptions, { passive: true });
    window.addEventListener('scroll', positionOptions, { passive: true, capture: true });
    window.visualViewport?.addEventListener('resize', positionOptions, { passive: true });
    window.visualViewport?.addEventListener('scroll', positionOptions, { passive: true });
}
function removePositionListeners() {
    if (!listenersAttached) return;
    listenersAttached = false;
    window.removeEventListener('resize', positionOptions);
    window.removeEventListener('scroll', positionOptions, true);
    window.visualViewport?.removeEventListener('resize', positionOptions);
    window.visualViewport?.removeEventListener('scroll', positionOptions);
}
async function openList() {
    open.value = true;
    activeIndex.value = 0;
    emit('search', props.filterType, query.value);
    await nextTick();
    positionOptions();
    addPositionListeners();
}
async function search() {
    open.value = true;
    activeIndex.value = 0;
    emit('update:modelValue', '');
    emit('search', props.filterType, query.value);
    await nextTick();
    positionOptions();
    addPositionListeners();
}
function choose(option) {
    query.value = option.name;
    closeList();
    emit('update:modelValue', option.id);
}
function closeList() {
    open.value = false;
    optionsStyle.value = {};
    removePositionListeners();
}
function scrollActiveOption() {
    const option = optionsMenu.value?.querySelector(`#${CSS.escape(optionId(activeIndex.value))}`);
    if (!option || !optionsMenu.value) return;
    const menuRect = optionsMenu.value.getBoundingClientRect();
    const optionRect = option.getBoundingClientRect();
    if (optionRect.top < menuRect.top) optionsMenu.value.scrollTop -= menuRect.top - optionRect.top;
    else if (optionRect.bottom > menuRect.bottom) optionsMenu.value.scrollTop += optionRect.bottom - menuRect.bottom;
}
function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        event.preventDefault();
        closeList();
    } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && open.value && props.options.length) {
        event.preventDefault();
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        activeIndex.value = (activeIndex.value + direction + props.options.length) % props.options.length;
        nextTick(scrollActiveOption);
    } else if (event.key === 'Enter' && open.value && props.options.length) {
        event.preventDefault();
        choose(props.options[activeIndex.value]);
    } else if (event.key === 'Tab' && open.value) {
        closeList();
    }
}
function clear() {
    query.value = '';
    closeList();
    emit('update:modelValue', '');
    emit('search', props.filterType, '');
}
function closeOnOutsidePointer(event) {
    if (!filterRoot.value?.contains(event.target) && !optionsMenu.value?.contains(event.target)) closeList();
}
watch(open, (isOpen) => {
    if (isOpen) document.addEventListener('pointerdown', closeOnOutsidePointer);
    else document.removeEventListener('pointerdown', closeOnOutsidePointer);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    removePositionListeners();
});
</script>
