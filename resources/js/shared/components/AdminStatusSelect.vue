<template>
    <div ref="root" class="admin-status-select">
        <button
            :id="triggerId"
            type="button"
            class="admin-status-select__trigger"
            :class="`is-${modelValue}`"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            :aria-activedescendant="isOpen ? optionId(activeIndex) : undefined"
            :aria-label="ariaLabel"
            :disabled="disabled"
            @click="toggle"
            @keydown="onTriggerKeydown"
        >
            <span class="admin-status-select__dot" aria-hidden="true"></span>
            <span class="admin-status-select__value">{{ selectedOption?.label || modelValue }}</span>
            <svg class="admin-status-select__chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg>
        </button>

        <Teleport to="body">
            <Transition name="admin-status-menu">
                <div
                    v-if="isOpen"
                    :id="listboxId"
                    ref="menu"
                    class="admin-status-select__menu"
                    :class="{ 'is-above': opensAbove }"
                    role="listbox"
                    :aria-labelledby="triggerId"
                    :style="menuStyle"
                >
                    <div
                        v-for="(option, index) in options"
                        :id="optionId(index)"
                        :key="option.value"
                        class="admin-status-select__option"
                        :class="[`is-${option.value}`, { 'is-active': activeIndex === index, 'is-selected': option.value === modelValue }]"
                        role="option"
                        :aria-selected="option.value === modelValue"
                        @mouseenter="activeIndex = index"
                        @click="select(option)"
                    >
                        <span class="admin-status-select__dot" aria-hidden="true"></span>
                        <span>{{ option.label }}</span>
                        <svg v-if="option.value === modelValue" class="admin-status-select__check" viewBox="0 0 16 16" aria-hidden="true"><path d="m3 8 3 3 7-7" /></svg>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId } from 'vue';

const props = defineProps({
    modelValue: { type: String, required: true },
    options: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
    ariaLabel: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const menu = ref(null);
const isOpen = ref(false);
const activeIndex = ref(0);
const menuPosition = ref({ top: 0, left: 0, minWidth: 0, maxHeight: 240 });
const opensAbove = ref(false);
const controlId = `admin-status-${useId()}`;
const triggerId = `${controlId}-trigger`;
const listboxId = `${controlId}-listbox`;
const selectedOption = computed(() => props.options.find(option => option.value === props.modelValue));
const menuStyle = computed(() => ({
    top: `${menuPosition.value.top}px`,
    left: `${menuPosition.value.left}px`,
    minWidth: `${menuPosition.value.minWidth}px`,
    maxHeight: `${menuPosition.value.maxHeight}px`,
}));

function optionId(index) { return `${listboxId}-option-${index}`; }

function positionMenu() {
    const trigger = root.value?.querySelector('button');
    if (!trigger || !menu.value) return;

    const rect = trigger.getBoundingClientRect();
    const viewportPadding = 8;
    const menuWidth = Math.min(Math.max(rect.width, 176), window.innerWidth - viewportPadding * 2);
    const availableBelow = window.innerHeight - rect.bottom - viewportPadding;
    const availableAbove = rect.top - viewportPadding;
    opensAbove.value = availableBelow < Math.min(menu.value.scrollHeight, 220) && availableAbove > availableBelow;
    const maxHeight = Math.max(96, Math.min(240, opensAbove.value ? availableAbove : availableBelow));
    const menuHeight = Math.min(menu.value.scrollHeight, maxHeight);
    const left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - menuWidth - viewportPadding));

    menuPosition.value = {
        top: opensAbove.value ? Math.max(viewportPadding, rect.top - menuHeight - 6) : Math.min(window.innerHeight - menuHeight - viewportPadding, rect.bottom + 6),
        left,
        minWidth: menuWidth,
        maxHeight,
    };
}

async function openMenu() {
    if (props.disabled || isOpen.value || !props.options.length) return;
    const selectedIndex = props.options.findIndex(option => option.value === props.modelValue);
    activeIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
    isOpen.value = true;
    await nextTick();
    positionMenu();
    window.addEventListener('pointerdown', onOutsidePointerDown, true);
    window.addEventListener('resize', positionMenu);
    window.addEventListener('scroll', positionMenu, true);
}

function closeMenu() {
    if (!isOpen.value) return;
    isOpen.value = false;
    window.removeEventListener('pointerdown', onOutsidePointerDown, true);
    window.removeEventListener('resize', positionMenu);
    window.removeEventListener('scroll', positionMenu, true);
}

function toggle() {
    if (isOpen.value) closeMenu();
    else openMenu();
}

function onOutsidePointerDown(event) {
    if (!root.value?.contains(event.target) && !menu.value?.contains(event.target)) closeMenu();
}

function select(option) {
    closeMenu();
    if (option.value !== props.modelValue) emit('update:modelValue', option.value);
}

function moveActive(direction) {
    if (!isOpen.value) {
        openMenu();
        return;
    }
    activeIndex.value = (activeIndex.value + direction + props.options.length) % props.options.length;
    nextTick(() => document.getElementById(optionId(activeIndex.value))?.scrollIntoView({ block: 'nearest' }));
}

function onTriggerKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveActive(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveActive(-1);
    } else if (event.key === 'Home' && isOpen.value) {
        event.preventDefault();
        activeIndex.value = 0;
    } else if (event.key === 'End' && isOpen.value) {
        event.preventDefault();
        activeIndex.value = props.options.length - 1;
    } else if ((event.key === 'Enter' || event.key === ' ') && isOpen.value) {
        event.preventDefault();
        select(props.options[activeIndex.value]);
    } else if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        closeMenu();
    } else if (event.key === 'Tab') {
        closeMenu();
    }
}

onBeforeUnmount(closeMenu);
</script>

<style>
.admin-status-select { display: inline-flex; max-width: 100%; vertical-align: middle; }
.admin-status-select__trigger { display: inline-flex; width: 138px; max-width: 100%; min-height: 32px; align-items: center; gap: 8px; padding: 0 9px; border: 1px solid var(--color-border); border-radius: 9px; color: var(--color-text); background: var(--color-surface); font: inherit; font-size: 11px; font-weight: 600; text-align: left; cursor: pointer; transition: border-color 140ms ease, background 140ms ease, box-shadow 140ms ease; }
.admin-status-select__trigger:hover:not(:disabled) { border-color: #c9cffc; background: #fbfbff; }
.admin-status-select__trigger:focus-visible { outline: 0; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgb(91 108 250 / 10%); }
.admin-status-select__trigger:disabled { cursor: wait; opacity: .58; }
.admin-status-select__value { min-width: 0; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.admin-status-select__dot { width: 7px; height: 7px; flex: 0 0 7px; border-radius: 50%; background: #8993a5; }
.admin-status-select__chevron { width: 13px; height: 13px; flex: 0 0 13px; fill: none; stroke: #8490a5; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.6; transition: transform 140ms ease; }
.admin-status-select__trigger[aria-expanded="true"] .admin-status-select__chevron { transform: rotate(180deg); }
.admin-status-select__trigger.is-active .admin-status-select__dot, .admin-status-select__option.is-active .admin-status-select__dot, .admin-status-select__trigger.is-in_progress .admin-status-select__dot, .admin-status-select__option.is-in_progress .admin-status-select__dot { background: #5b6cfa; }
.admin-status-select__trigger.is-on_hold .admin-status-select__dot, .admin-status-select__option.is-on_hold .admin-status-select__dot { background: #b98736; }
.admin-status-select__trigger.is-completed .admin-status-select__dot, .admin-status-select__option.is-completed .admin-status-select__dot { background: #328366; }
.admin-status-select__menu { position: fixed; z-index: 1200; overflow: auto; padding: 5px; border: 1px solid var(--color-border); border-radius: 9px; color: var(--color-text); background: var(--color-surface); box-shadow: 0 10px 24px rgb(32 40 58 / 12%); transform-origin: top center; overscroll-behavior: contain; }
.admin-status-select__menu.is-above { transform-origin: bottom center; }
.admin-status-select__option { display: flex; min-height: 34px; align-items: center; gap: 9px; padding: 0 9px; border-radius: 7px; color: var(--color-text); font-size: 11px; font-weight: 550; cursor: pointer; transition: background 140ms ease, color 140ms ease; }
.admin-status-select__option:hover, .admin-status-select__option.is-active { background: #f5f6ff; }
.admin-status-select__option.is-selected { color: #4657db; background: #f0f2ff; font-weight: 650; }
.admin-status-select__option.is-on_hold.is-selected { color: #8a6a2a; background: #fbf5e8; }
.admin-status-select__option.is-completed.is-selected { color: #328366; background: #eaf7f1; }
.admin-status-select__check { width: 14px; height: 14px; margin-left: auto; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }
.admin-status-menu-enter-active, .admin-status-menu-leave-active { transition: opacity 140ms ease, transform 140ms ease; }
.admin-status-menu-enter-from, .admin-status-menu-leave-to { opacity: 0; transform: translateY(-3px) scale(.99); }
.admin-status-select__menu.is-above.admin-status-menu-enter-from, .admin-status-select__menu.is-above.admin-status-menu-leave-to { transform: translateY(3px) scale(.98); }
@media (prefers-reduced-motion: reduce) { .admin-status-select__trigger, .admin-status-select__chevron, .admin-status-select__option, .admin-status-menu-enter-active, .admin-status-menu-leave-active { transition: none; } }
</style>
