<template>
    <div ref="root" class="header-menu admin-quick-add" @keydown="onKeydown" @focusout="onFocusout">
        <button
            ref="trigger"
            class="header-action-trigger quick-add-trigger"
            type="button"
            aria-label="Quick add"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-controls="menuId"
            @click="toggleMenu"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
            <span>Quick add</span>
        </button>

        <section v-if="open" :id="menuId" ref="menu" class="header-popover admin-quick-add-popover" role="menu" aria-label="Create new">
            <p class="admin-quick-add-heading">Create new</p>
            <button v-for="(action, index) in actions" :key="action.label" class="admin-quick-add-item" type="button" role="menuitem" @click="selectAction(action)">
                <span class="admin-quick-add-icon" aria-hidden="true">
                    <svg v-if="index === 0" viewBox="0 0 24 24"><path d="M8 5h12M8 12h12M8 19h12M4 5h.01M4 12h.01M4 19h.01" /></svg>
                    <svg v-else-if="index === 1" viewBox="0 0 24 24"><path d="M3.5 7.5h6l2 2h9v9h-17zM3.5 7.5v-2h7l2 2" /></svg>
                    <svg v-else viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5" /><path d="M4.5 20a7.5 7.5 0 0 1 15 0M19 7v5m-2.5-2.5h5" /></svg>
                </span>
                <span class="admin-quick-add-copy"><strong>{{ action.label }}</strong><small>{{ action.description }}</small></span>
            </button>
        </section>
        <p v-if="navigationError" class="admin-quick-add-error" role="alert">{{ navigationError }}</p>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const root = ref(null);
const trigger = ref(null);
const menu = ref(null);
const open = ref(false);
const navigationError = ref('');
const menuId = `admin-quick-add-${useId()}`;
let focusoutTimer;
const actions = [
    { label: 'New Task', description: 'Create a new task quickly.', to: { name: 'admin.tasks.create' } },
    { label: 'New Project', description: 'Create a new project.', to: { name: 'admin.projects.index', query: { quickAdd: 'project' } } },
    { label: 'New User', description: 'Add a new user to Planora.', to: { name: 'admin.users.create' } },
];

function closeMenu(restoreFocus = false) {
    window.clearTimeout(focusoutTimer);
    focusoutTimer = undefined;
    open.value = false;
    if (restoreFocus) trigger.value?.focus({ preventScroll: true });
}

function toggleMenu() {
    if (open.value) closeMenu();
    else {
        navigationError.value = '';
        open.value = true;
    }
}

async function focusAction(index) {
    if (!open.value) {
        navigationError.value = '';
        open.value = true;
        await nextTick();
    }
    const items = menu.value?.querySelectorAll('[role="menuitem"]');
    if (items?.length) items[(index + items.length) % items.length]?.focus();
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        event.preventDefault();
        event.stopPropagation();
        closeMenu(true);
        return;
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        const items = [...(menu.value?.querySelectorAll('[role="menuitem"]') || [])];
        const current = items.indexOf(document.activeElement);
        focusAction(current < 0 ? 0 : current + 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        const items = [...(menu.value?.querySelectorAll('[role="menuitem"]') || [])];
        const current = items.indexOf(document.activeElement);
        focusAction(current < 0 ? actions.length - 1 : current - 1);
    } else if (event.key === 'Home' && open.value) {
        event.preventDefault();
        focusAction(0);
    } else if (event.key === 'End' && open.value) {
        event.preventDefault();
        focusAction(actions.length - 1);
    }
}

function onOutsidePointer(event) {
    if (open.value && event.target instanceof Node && !root.value?.contains(event.target)) closeMenu();
}

function onFocusout() {
    window.clearTimeout(focusoutTimer);
    focusoutTimer = window.setTimeout(() => {
        focusoutTimer = undefined;
        if (open.value && !root.value?.contains(document.activeElement)) closeMenu();
    }, 0);
}

function onDocumentKeydown(event) {
    if (event.key !== 'Escape' || !open.value) return;
    event.preventDefault();
    event.stopPropagation();
    closeMenu(true);
}

async function selectAction(action) {
    closeMenu(true);
    try {
        await router.push(action.to);
    } catch {
        navigationError.value = 'That creation form could not be opened. Please try again.';
    }
}

watch(() => route.fullPath, () => closeMenu());
onMounted(() => {
    document.addEventListener('pointerdown', onOutsidePointer);
    document.addEventListener('keydown', onDocumentKeydown);
});
onBeforeUnmount(() => {
    window.clearTimeout(focusoutTimer);
    document.removeEventListener('pointerdown', onOutsidePointer);
    document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<style src="../../../../css/components/AdminQuickAdd.css"></style>
