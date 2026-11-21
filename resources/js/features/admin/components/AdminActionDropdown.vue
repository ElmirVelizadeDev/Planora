<template>
    <div class="admin-action-dropdown" @click.stop>
        <button
            ref="trigger"
            class="admin-action-dropdown__trigger"
            type="button"
            :aria-label="label"
            aria-haspopup="menu"
            :aria-expanded="isOpen"
            @click="toggle"
            @keydown.arrow-down.prevent="openAndFocusFirst"
        >
            <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.35"/><circle cx="10" cy="10" r="1.35"/><circle cx="16" cy="10" r="1.35"/></svg>
        </button>

        <Teleport to="body">
            <Transition name="admin-action-dropdown">
                <div
                    v-if="isOpen"
                    ref="menu"
                    class="admin-action-dropdown__menu"
                    :style="menuStyle"
                    role="menu"
                    :aria-label="label"
                    @click.stop
                    @keydown.arrow-down.prevent="moveFocus(1)"
                    @keydown.arrow-up.prevent="moveFocus(-1)"
                    @keydown.home.prevent="focusEdge('first')"
                    @keydown.end.prevent="focusEdge('last')"
                >
                    <template v-for="item in items" :key="item.key">
                        <span v-if="item.separatorBefore" class="admin-action-dropdown__separator" role="separator"></span>
                        <RouterLink
                            v-if="item.to"
                            class="admin-action-dropdown__item"
                            :class="{ 'is-danger': item.danger }"
                            :to="item.to"
                            role="menuitem"
                            @click="select(item)"
                        >
                            <ActionIcon :name="item.icon" />
                            <span>{{ item.label }}</span>
                        </RouterLink>
                        <button
                            v-else
                            class="admin-action-dropdown__item"
                            :class="{ 'is-danger': item.danger }"
                            type="button"
                            role="menuitem"
                            :disabled="item.disabled"
                            @click="select(item)"
                        >
                            <ActionIcon :name="item.icon" />
                            <span>{{ item.label }}</span>
                        </button>
                    </template>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { getAnchoredPopoverStyle } from '../../../shared/composables/getAnchoredPopoverStyle.js';

const props = defineProps({
    activeKey: { type: [String, Number], default: null },
    itemKey: { type: [String, Number], required: true },
    label: { type: String, required: true },
    items: { type: Array, required: true },
});
const emit = defineEmits(['update:activeKey']);
const trigger = ref(null);
const menu = ref(null);
const menuStyle = ref({});
const isOpen = computed(() => props.activeKey !== null && String(props.activeKey) === String(props.itemKey));
let listenersAttached = false;

const ActionIcon = defineComponent({
    props: { name: { type: String, default: 'view' } },
    setup(iconProps) {
        const paths = {
            view: () => [h('circle', { cx: '10', cy: '10', r: '6.5' }), h('circle', { cx: '10', cy: '10', r: '2.2' })],
            edit: () => [h('path', { d: 'm4 13.8-.8 3 3-.8L16.8 7.4a1.8 1.8 0 0 0-2.5-2.5L4 13.8Z' }), h('path', { d: 'm12.9 6.3 2.5 2.5' })],
            status: () => [h('circle', { cx: '10', cy: '10', r: '6.5' }), h('path', { d: 'M10 6.5v3.8l2.4 1.4' })],
            delete: () => [h('path', { d: 'M4.5 6.5h11M8 6.5V4h4v2.5m2.5 0-.7 10h-7l-.7-10M8.5 9.2v4.8m3-4.8v4.8' })],
            restore: () => [h('path', { d: 'M5 7.5A6 6 0 1 1 4 12' }), h('path', { d: 'M4 4.5v3.8h3.8' })],
            archive: () => [h('path', { d: 'M3.5 5h13v3h-13zM5 8v8h10V8m-6 3h2' })],
        };
        return () => h('svg', { viewBox: '0 0 20 20', 'aria-hidden': 'true' }, (paths[iconProps.name] || paths.view)());
    },
});

function positionMenu() {
    if (!isOpen.value || !trigger.value || !menu.value) return;
    menuStyle.value = getAnchoredPopoverStyle(trigger.value, {
        width: 184,
        maxHeight: 320,
        gutter: 8,
        gap: 5,
        alignment: 'end',
        viewportAware: true,
    });
}

function close() {
    menuStyle.value = {};
    emit('update:activeKey', null);
}

function toggle() {
    if (isOpen.value) close();
    else emit('update:activeKey', props.itemKey);
}

async function openAndFocusFirst() {
    if (!isOpen.value) emit('update:activeKey', props.itemKey);
    await nextTick();
    menu.value?.querySelector('[role="menuitem"]:not(:disabled)')?.focus({ preventScroll: true });
}

function onOutsidePointer(event) {
    if (trigger.value?.contains(event.target) || menu.value?.contains(event.target)) return;
    close();
}

function onKeydown(event) {
    if (event.key !== 'Escape' || !isOpen.value) return;
    event.preventDefault();
    const focusWasInside = menu.value?.contains(document.activeElement);
    close();
    if (focusWasInside) nextTick(() => trigger.value?.focus({ preventScroll: true }));
}

function addListeners() {
    if (listenersAttached) return;
    listenersAttached = true;
    document.addEventListener('pointerdown', onOutsidePointer, true);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', positionMenu, { passive: true });
    window.addEventListener('scroll', positionMenu, { passive: true, capture: true });
    window.visualViewport?.addEventListener('resize', positionMenu, { passive: true });
    window.visualViewport?.addEventListener('scroll', positionMenu, { passive: true });
}

function removeListeners() {
    if (!listenersAttached) return;
    listenersAttached = false;
    document.removeEventListener('pointerdown', onOutsidePointer, true);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', positionMenu);
    window.removeEventListener('scroll', positionMenu, true);
    window.visualViewport?.removeEventListener('resize', positionMenu);
    window.visualViewport?.removeEventListener('scroll', positionMenu);
}

function moveFocus(direction) {
    const options = [...(menu.value?.querySelectorAll('[role="menuitem"]:not(:disabled)') || [])];
    if (!options.length) return;
    const current = options.indexOf(document.activeElement);
    options[(current + direction + options.length) % options.length]?.focus({ preventScroll: true });
}

function focusEdge(edge) {
    const options = menu.value?.querySelectorAll('[role="menuitem"]:not(:disabled)');
    (edge === 'first' ? options?.[0] : options?.[options.length - 1])?.focus({ preventScroll: true });
}

function select(item) {
    close();
    item.onSelect?.();
}

watch(isOpen, async (opened) => {
    if (!opened) {
        removeListeners();
        return;
    }
    await nextTick();
    if (!isOpen.value) return;
    positionMenu();
    addListeners();
});

onBeforeUnmount(() => {
    if (isOpen.value) emit('update:activeKey', null);
    removeListeners();
});
</script>

<style>
.admin-action-dropdown { display: flex; justify-content: flex-end; }
.admin-action-dropdown__trigger { display: grid; width: 34px; height: 34px; place-items: center; padding: 0; border: 1px solid transparent; border-radius: 9px; color: var(--color-text-secondary); background: transparent; cursor: pointer; transition: color var(--admin-dropdown-transition, 140ms ease), background var(--admin-dropdown-transition, 140ms ease), border-color var(--admin-dropdown-transition, 140ms ease); touch-action: manipulation; }
.admin-action-dropdown__trigger svg { width: 19px; height: 19px; fill: currentColor; }
.admin-action-dropdown__trigger:hover, .admin-action-dropdown__trigger[aria-expanded="true"] { border-color: var(--admin-dropdown-border, var(--color-border)); color: var(--color-accent-text); background: var(--admin-dropdown-surface, var(--color-surface)); }
.admin-action-dropdown__trigger:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: 2px; }
.admin-action-dropdown__menu { position: fixed; z-index: 1400; display: grid; box-sizing: border-box; max-width: calc(100vw - 16px); overflow-x: hidden; overflow-y: auto; padding: 6px; border: 1px solid var(--admin-dropdown-border, var(--color-border)); border-radius: var(--admin-dropdown-radius, 12px); color: var(--color-text); background: var(--admin-dropdown-surface, var(--color-surface)); box-shadow: var(--admin-dropdown-shadow, 0 12px 32px rgb(32 40 58 / 14%)); overscroll-behavior: contain; scrollbar-width: thin; transform-origin: top right; }
.admin-action-dropdown__item { display: flex; min-height: 36px; align-items: center; gap: 10px; padding: 0 10px; border: 0; border-radius: var(--admin-dropdown-option-radius, 8px); color: var(--color-text); background: transparent; font: inherit; font-size: 11px; font-weight: 560; line-height: 1.25; text-align: left; text-decoration: none; cursor: pointer; transition: color var(--admin-dropdown-transition, 140ms ease), background var(--admin-dropdown-transition, 140ms ease); }
.admin-action-dropdown__item svg { width: 16px; height: 16px; flex: 0 0 16px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.5; }
.admin-action-dropdown__item:hover, .admin-action-dropdown__item:focus-visible { color: var(--color-accent-text); background: var(--color-selected-background, var(--color-accent-subtle)); outline: none; }
.admin-action-dropdown__item:focus-visible { box-shadow: inset 0 0 0 2px var(--color-focus-ring); }
.admin-action-dropdown__item.is-danger { color: var(--color-danger); }
.admin-action-dropdown__item.is-danger:hover, .admin-action-dropdown__item.is-danger:focus-visible { color: var(--color-danger); background: var(--color-danger-subtle); }
.admin-action-dropdown__item:disabled { opacity: .5; cursor: not-allowed; }
.admin-action-dropdown__separator { height: 1px; margin: 4px 4px; background: var(--admin-dropdown-border, var(--color-border)); }
.admin-action-dropdown-enter-active, .admin-action-dropdown-leave-active { transition: opacity 120ms ease, transform 120ms ease; }
.admin-action-dropdown-enter-from, .admin-action-dropdown-leave-to { opacity: 0; transform: translateY(-3px) scale(.99); }
@media (prefers-reduced-motion: reduce) { .admin-action-dropdown__trigger, .admin-action-dropdown__item, .admin-action-dropdown-enter-active, .admin-action-dropdown-leave-active { transition-duration: 0ms; } }
</style>
