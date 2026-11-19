<template>
    <div ref="root" class="header-search-area" :class="{ 'is-open': open }">
        <button
            ref="triggerRef"
            class="header-search-mobile-trigger"
            type="button"
            aria-label="Open workspace search"
            :aria-expanded="open"
            aria-controls="workspace-search-panel"
            @click="openSearch"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>
        </button>
        <form class="workspace-search" role="search" @submit.prevent="activateSelected">
            <svg class="workspace-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>
            <input
                ref="inputRef"
                v-model="query"
                type="search"
                maxlength="120"
                placeholder="Search your workspace"
                aria-label="Search your workspace"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="workspace-search-listbox"
                :aria-expanded="open"
                :aria-activedescendant="activeResultId"
                @focus="openPanel"
                @keydown="onInputKeydown"
            >
            <button v-if="query" class="workspace-search-clear" type="button" aria-label="Clear search" @click="clearSearch">×</button>
            <kbd aria-hidden="true">{{ isMac ? '⌘ K' : 'Ctrl K' }}</kbd>
            <button class="workspace-search-close" type="button" aria-label="Close search" @click="closeSearch(true)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </form>

        <section v-if="open" id="workspace-search-panel" class="workspace-search-panel" aria-label="Search results">
            <div v-if="!normalizedQuery" class="workspace-search-intro">
                <span class="workspace-search-panel-icon" aria-hidden="true">⌕</span>
                <div><strong>Search your workspace</strong><p>Find tasks, projects{{ canSearchUsers ? ', users' : '' }}, and categories.</p></div>
                <span class="workspace-search-hint"><kbd>↑</kbd><kbd>↓</kbd><span>navigate</span><kbd>Esc</kbd><span>close</span></span>
            </div>
            <div v-else-if="normalizedQuery.length < minimumLength" class="workspace-search-state">Type at least {{ minimumLength }} characters to search.</div>
            <div v-else-if="loading" class="workspace-search-state" role="status"><span class="workspace-search-spinner" aria-hidden="true"></span>Searching…</div>
            <div v-else-if="error" class="workspace-search-state is-error" role="alert">
                <span>{{ error }}</span><button type="button" @click="runSearch">Try again</button>
            </div>
            <div v-else-if="!groups.length" class="workspace-search-state" role="status" aria-live="polite">
                <strong>No results for “{{ normalizedQuery }}”</strong><span>Check the spelling or try another keyword.</span>
            </div>
            <div v-else id="workspace-search-listbox" class="workspace-search-groups" role="listbox" aria-label="Workspace search results">
                <section v-for="group in groups" :key="group.type" class="workspace-search-group" :aria-label="group.label">
                    <h2>{{ group.label }}<span>{{ group.results.length }}</span></h2>
                    <button
                        v-for="result in group.results"
                        :id="resultId(result)"
                        :key="`${result.type}-${result.id}`"
                        type="button"
                        class="workspace-search-result"
                        :class="{ 'is-selected': isSelected(result) }"
                        role="option"
                        :aria-selected="isSelected(result)"
                        @mouseenter="selectResult(result)"
                        @click="openResult(result)"
                    >
                        <span class="workspace-search-result-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path v-if="result.type === 'task'" d="M8 5h11M8 12h11M8 19h11M4 5h.01M4 12h.01M4 19h.01"/><path v-else-if="result.type === 'project'" d="M3.5 7.5h6l2 2h9v9h-17zM3.5 7.5v-2h7l2 2"/><path v-else-if="result.type === 'user'" d="M20 20a8 8 0 0 0-16 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path v-else d="M4 6.5h16v11H4zM8 6.5V4h8v2.5M8 11h8M8 14.5h5"/></svg></span>
                        <span class="workspace-search-result-copy">
                            <strong><template v-for="(part, index) in highlightParts(result.title)" :key="`title-${index}`"><mark v-if="part.match">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template></strong>
                            <small v-if="result.subtitle"><template v-for="(part, index) in highlightParts(result.subtitle)" :key="`subtitle-${index}`"><mark v-if="part.match">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template></small>
                        </span>
                        <span class="workspace-search-result-type">{{ result.type }}</span>
                        <span class="workspace-search-result-enter" aria-hidden="true">↵</span>
                    </button>
                </section>
                <p class="workspace-search-count" aria-live="polite">{{ total }} result{{ total === 1 ? '' : 's' }}</p>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { searchWorkspace } from '../../features/search/services/globalSearchApi.js';

const emit = defineEmits(['overlay-change']);
const router = useRouter();
const auth = useAuthStore();
const root = ref(null);
const inputRef = ref(null);
const triggerRef = ref(null);
const query = ref('');
const open = ref(false);
const loading = ref(false);
const error = ref('');
const groups = ref([]);
const total = ref(0);
const selected = ref(null);
const mobileMedia = window.matchMedia('(max-width: 680px)');
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || '');
const minimumLength = 2;
const normalizedQuery = computed(() => query.value.trim());
const canSearchUsers = computed(() => auth.hasRole('admin') || auth.hasManagementPermission('users.view'));
const flattenedResults = computed(() => groups.value.flatMap((group) => group.results));
const activeResultId = computed(() => selected.value ? resultId(selected.value) : undefined);
let debounceTimer;
let focusTimer;
let requestController;
let requestSequence = 0;

function resultId(result) { return `workspace-search-${result.type}-${result.id}`; }
function highlightParts(value) {
    const text = String(value || '');
    const needle = normalizedQuery.value.toLocaleLowerCase();
    if (!needle) return [{ text, match: false }];
    const lower = text.toLocaleLowerCase();
    const parts = [];
    let cursor = 0;
    while (cursor < text.length) {
        const index = lower.indexOf(needle, cursor);
        if (index < 0) {
            parts.push({ text: text.slice(cursor), match: false });
            break;
        }
        if (index > cursor) parts.push({ text: text.slice(cursor, index), match: false });
        parts.push({ text: text.slice(index, index + needle.length), match: true });
        cursor = index + needle.length;
    }
    return parts.length ? parts : [{ text, match: false }];
}
function isSelected(result) { return selected.value?.type === result.type && selected.value?.id === result.id; }
function selectResult(result) { selected.value = result; }
function openPanel() {
    if (!open.value) {
        open.value = true;
        emit('overlay-change', true);
        if (normalizedQuery.value.length >= minimumLength && !groups.value.length && !error.value) scheduleSearch();
    }
}
function openSearch() {
    openPanel();
    window.cancelAnimationFrame(focusTimer);
    focusTimer = window.requestAnimationFrame(() => inputRef.value?.focus());
}
function resetResults() {
    groups.value = [];
    total.value = 0;
    selected.value = null;
    error.value = '';
}
function closeSearch(restoreFocus = false) {
    const wasOpen = open.value;
    open.value = false;
    requestSequence += 1;
    requestController?.abort();
    clearTimeout(debounceTimer);
    loading.value = false;
    if (restoreFocus) {
        inputRef.value?.blur();
        window.cancelAnimationFrame(focusTimer);
        focusTimer = window.requestAnimationFrame(() => (mobileMedia.matches ? triggerRef.value : inputRef.value)?.focus({ preventScroll: true }));
    }
    if (wasOpen) emit('overlay-change', false);
}
function clearSearch() {
    query.value = '';
    resetResults();
    openSearch();
}
async function runSearch() {
    const value = normalizedQuery.value;
    const sequence = ++requestSequence;
    requestController?.abort();
    resetResults();
    if (value.length < minimumLength || !open.value) {
        loading.value = false;
        return;
    }
    requestController = new AbortController();
    loading.value = true;
    try {
        const result = await searchWorkspace(value, { signal: requestController.signal });
        if (sequence !== requestSequence || value !== normalizedQuery.value || !open.value) return;
        groups.value = Array.isArray(result.groups) ? result.groups : [];
        total.value = Number(result.total || 0);
        selected.value = flattenedResults.value[0] || null;
    } catch (requestError) {
        if (requestError.name === 'CanceledError' || requestError.name === 'AbortError' || sequence !== requestSequence) return;
        error.value = requestError.response?.status === 401
            ? 'Your session has expired. Sign in again to search.'
            : 'Search is temporarily unavailable. Check your connection and try again.';
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}
function scheduleSearch() {
    clearTimeout(debounceTimer);
    requestSequence += 1;
    requestController?.abort();
    resetResults();
    if (normalizedQuery.value.length < minimumLength) {
        loading.value = false;
        return;
    }
    loading.value = true;
    debounceTimer = window.setTimeout(runSearch, 300);
}
function moveSelection(direction) {
    const results = flattenedResults.value;
    if (!results.length) return;
    const current = results.findIndex((result) => isSelected(result));
    const next = current < 0 ? 0 : (current + direction + results.length) % results.length;
    selected.value = results[next];
    document.getElementById(resultId(selected.value))?.scrollIntoView({ block: 'nearest' });
}
async function openResult(result = selected.value) {
    if (!result?.to) return;
    try {
        await router.push(result.to);
        closeSearch();
        query.value = '';
        resetResults();
    } catch {
        error.value = 'That item is no longer available. Refresh the search and try again.';
    }
}
function activateSelected() { if (selected.value) openResult(selected.value); }
function onInputKeydown(event) {
    if (event.key === 'ArrowDown') { event.preventDefault(); moveSelection(1); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); moveSelection(-1); }
    else if (event.key === 'Escape') { event.preventDefault(); closeSearch(true); }
    else if (event.key === 'Enter') { event.preventDefault(); activateSelected(); }
}
function onGlobalKeydown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        const target = event.target;
        const isTypingElsewhere = target instanceof HTMLElement
            && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName))
            && !root.value?.contains(target);
        if (isTypingElsewhere) return;
        event.preventDefault();
        openSearch();
    } else if (event.key === 'Escape' && open.value) {
        closeSearch(true);
    }
}
function onOutsidePointer(event) {
    if (open.value && event.target instanceof Node && !root.value?.contains(event.target)) closeSearch();
}
function onViewportChange(event) {
    if (!event.matches && open.value) closeSearch();
}

watch(normalizedQuery, scheduleSearch);
onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
    document.addEventListener('pointerdown', onOutsidePointer);
    mobileMedia.addEventListener('change', onViewportChange);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    document.removeEventListener('pointerdown', onOutsidePointer);
    mobileMedia.removeEventListener('change', onViewportChange);
    clearTimeout(debounceTimer);
    window.cancelAnimationFrame(focusTimer);
    requestController?.abort();
});
defineExpose({ close: closeSearch });
</script>
