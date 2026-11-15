<template>
    <main class="appearance-page" :aria-busy="!initialized || saving">
        <header class="appearance-header">
            <div>
                <p class="appearance-eyebrow">YOUR WORKSPACE</p>
                <h1>Appearance</h1>
                <p>Personalize how Planora looks and feels on this account.</p>
            </div>
            <span class="appearance-saved-state" :class="{ 'is-dirty': dirty }" role="status">
                <i aria-hidden="true" />{{ dirty ? 'Unsaved changes' : 'Preferences saved' }}
            </span>
        </header>

        <p v-if="notice" class="appearance-feedback is-success" role="status">{{ notice }}</p>
        <p v-if="error" class="appearance-feedback is-error" role="alert">{{ error }}</p>

        <div v-if="!initialized" class="appearance-loading" aria-live="polite">Loading appearance preferences…</div>
        <template v-else>
            <section class="appearance-card" aria-labelledby="appearance-theme-heading">
                <div class="appearance-section-heading">
                    <div><h2 id="appearance-theme-heading">Theme</h2><p>Choose the appearance for this account. Light is the default theme.</p></div>
                </div>
                <div class="appearance-theme-options" role="radiogroup" aria-labelledby="appearance-theme-heading">
                    <button v-for="option in themeOptions" :key="option.value" class="appearance-theme-option" :class="[`is-${option.value}`, { 'is-selected': preferences.theme === option.value }]" type="button" role="radio" :aria-checked="preferences.theme === option.value" :tabindex="preferences.theme === option.value ? 0 : -1" @click="preferences.theme = option.value; clearFeedback()" @keydown="moveRadio($event, 'theme', themeOptions)">
                        <span class="appearance-theme-preview" :class="`is-${option.value}`" aria-hidden="true">
                            <span class="appearance-preview-sidebar"><i /><i /><i /></span>
                            <span class="appearance-preview-content"><i /><i /><b>{{ option.label }} theme</b><small>{{ option.description }}</small></span>
                            <span class="appearance-theme-label"><i aria-hidden="true" />{{ option.label }}</span>
                        </span>
                        <strong>{{ option.label }} Theme</strong>
                        <small>{{ option.description }}</small>
                    </button>
                </div>
            </section>

            <section class="appearance-card" aria-labelledby="appearance-accent-heading">
                <div class="appearance-section-heading">
                    <div><h2 id="appearance-accent-heading">Accent color</h2><p>Choose a clear, accessible accent for interactive elements.</p></div>
                    <span class="appearance-accent-current"><i :style="{ backgroundColor: preferences.accent }" />{{ accentLabel }}</span>
                </div>
                <div class="appearance-accent-options" role="radiogroup" aria-labelledby="appearance-accent-heading">
                    <button
                        v-for="accent in APPEARANCE_ACCENTS"
                        :key="accent.value"
                        type="button"
                        class="appearance-accent-option"
                        :class="{ 'is-selected': preferences.accent === accent.value }"
                        role="radio"
                        :aria-checked="preferences.accent === accent.value"
                        :tabindex="preferences.accent === accent.value ? 0 : -1"
                        :aria-label="accent.label"
                        @click="preferences.accent = accent.value; clearFeedback()"
                        @keydown="moveRadio($event, 'accent', APPEARANCE_ACCENTS)"
                    >
                        <i :style="{ backgroundColor: accent.value }"><svg v-if="preferences.accent === accent.value" viewBox="0 0 20 20" aria-hidden="true"><path d="m4 10 4 4 8-8" /></svg></i>
                        <span>{{ accent.label }}</span>
                    </button>
                </div>
                <div class="appearance-live-preview" :style="{ '--appearance-preview-accent': preferences.accent }">
                    <span>Button preview</span><button type="button" tabindex="-1" aria-hidden="true">Create task</button><a href="#appearance-accent-heading" @click.prevent>Interactive text</a>
                </div>
            </section>

            <section class="appearance-card" aria-labelledby="appearance-density-heading">
                <div class="appearance-section-heading"><div><h2 id="appearance-density-heading">Interface density</h2><p>Adjust spacing while keeping text and controls comfortable to use.</p></div></div>
                <div class="appearance-choice-grid" role="radiogroup" aria-labelledby="appearance-density-heading">
                    <button v-for="option in densityOptions" :key="option.value" class="appearance-choice" :class="{ 'is-selected': preferences.density === option.value }" type="button" role="radio" :aria-checked="preferences.density === option.value" :tabindex="preferences.density === option.value ? 0 : -1" @click="preferences.density = option.value; clearFeedback()" @keydown="moveRadio($event, 'density', densityOptions)">
                        <span class="appearance-choice-mark" aria-hidden="true"><i /></span><strong>{{ option.label }}</strong><small>{{ option.description }}</small>
                        <span class="appearance-density-preview" :class="`is-${option.value}`" aria-hidden="true"><i /><i /><i /></span>
                    </button>
                </div>
            </section>

            <section class="appearance-card" aria-labelledby="appearance-sidebar-heading">
                <div class="appearance-section-heading"><div><h2 id="appearance-sidebar-heading">Sidebar default</h2><p>Choose how workspace navigation opens on wider screens.</p></div></div>
                <div class="appearance-choice-grid appearance-choice-grid--sidebar" role="radiogroup" aria-labelledby="appearance-sidebar-heading">
                    <button v-for="option in sidebarOptions" :key="option.value" class="appearance-choice appearance-choice--sidebar" :class="{ 'is-selected': preferences.sidebar === option.value }" type="button" role="radio" :aria-checked="preferences.sidebar === option.value" :tabindex="preferences.sidebar === option.value ? 0 : -1" @click="preferences.sidebar = option.value; clearFeedback()" @keydown="moveRadio($event, 'sidebar', sidebarOptions)">
                        <span class="appearance-choice-mark" aria-hidden="true"><i /></span><strong>{{ option.label }}</strong><small>{{ option.description }}</small>
                        <span class="appearance-sidebar-preview" :class="`is-${option.value}`" aria-hidden="true"><i /><i /><i /><i /></span>
                    </button>
                </div>
                <p class="appearance-helper">Mobile navigation continues to open as an overlay, regardless of this preference.</p>
            </section>

            <footer class="appearance-actions">
                <button class="appearance-button appearance-button--quiet" type="button" :disabled="saving || !isCustomized" @click="requestReset">Reset to defaults</button>
                <button class="appearance-button appearance-button--primary" type="button" :disabled="saving || !dirty" @click="saveChanges">{{ saving ? 'Saving…' : 'Save changes' }}</button>
            </footer>
        </template>

        <AppConfirmDialog
            :open="confirmation.open"
            :title="confirmation.title"
            :message="confirmation.message"
            :confirm-label="confirmation.confirmLabel"
            @cancel="cancelConfirmation"
            @confirm="confirmConfirmation"
        />
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import { APPEARANCE_ACCENTS, APPEARANCE_DEFAULTS, useAppearancePreferences } from '../../../features/appearance/composables/appearancePreferences.js';
import AppConfirmDialog from '../../../shared/components/AppConfirmDialog.vue';

const auth = useAuthStore();
const { preferences, saved, initialized, dirty, load, save, restoreSaved, apply } = useAppearancePreferences();
const saving = ref(false);
const notice = ref('');
const error = ref('');
const confirmation = reactive({ open: false, mode: '', title: '', message: '', confirmLabel: '' });
let pendingNavigation = null;
const densityOptions = [
    { value: 'comfortable', label: 'Comfortable', description: 'Balanced spacing for everyday work.' },
    { value: 'compact', label: 'Compact', description: 'Fit more content with tighter spacing.' },
];
const sidebarOptions = [
    { value: 'expanded', label: 'Expanded', description: 'Show navigation labels by default.' },
    { value: 'collapsed', label: 'Collapsed', description: 'Keep navigation compact by default.' },
];
const themeOptions = [
    { value: 'light', label: 'Light', description: 'Bright surfaces and soft borders.' },
    { value: 'dark', label: 'Dark', description: 'Deep surfaces with clear contrast.' },
];
const accentLabel = computed(() => APPEARANCE_ACCENTS.find((item) => item.value === preferences.accent)?.label || 'Planora Indigo');
const isCustomized = computed(() => JSON.stringify(preferences) !== JSON.stringify(APPEARANCE_DEFAULTS) || JSON.stringify(saved.value) !== JSON.stringify(APPEARANCE_DEFAULTS));

onMounted(() => load(auth.user?.id));
window.addEventListener('planora:appearance-updated', refreshSavedPreferences);

function clearFeedback() { notice.value = ''; error.value = ''; }
function refreshSavedPreferences() { load(auth.user?.id); }
function moveRadio(event, field, options) {
    const direction = ['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 0;
    if (!direction) return;
    event.preventDefault();
    const current = options.findIndex((option) => option.value === preferences[field]);
    const next = (current + direction + options.length) % options.length;
    preferences[field] = options[next].value;
    clearFeedback();
    event.currentTarget.parentElement.querySelectorAll('[role="radio"]')[next]?.focus();
}
function saveChanges() {
    if (saving.value || !dirty.value) return;
    saving.value = true;
    clearFeedback();
    try {
        if (!save()) throw new Error('Preferences could not be saved in this browser. Check that local storage is available, then try again.');
        notice.value = 'Appearance preferences saved for this account.';
    } catch (exception) { error.value = exception.message || 'Appearance preferences could not be saved.'; }
    finally { saving.value = false; }
}
function requestReset() {
    if (!isCustomized.value || saving.value) return;
    confirmation.open = true;
    confirmation.mode = 'reset';
    confirmation.title = 'Reset appearance?';
    confirmation.message = 'This will restore the Light theme, Planora Indigo, Comfortable density and Expanded sidebar for this account.';
    confirmation.confirmLabel = 'Reset to defaults';
}
function requestLeave() {
    if (pendingNavigation) return pendingNavigation.promise;
    confirmation.open = true;
    confirmation.mode = 'leave';
    confirmation.title = 'Discard unsaved changes?';
    confirmation.message = 'Your appearance changes have not been saved. Leave this page and restore the saved appearance?';
    confirmation.confirmLabel = 'Discard changes';
    const promise = new Promise((resolve) => { pendingNavigation = { promise: null, resolve }; });
    pendingNavigation.promise = promise;
    return promise;
}
function settleLeave(leave) {
    if (!pendingNavigation) return;
    const current = pendingNavigation;
    pendingNavigation = null;
    confirmation.open = false;
    confirmation.mode = '';
    if (leave) restoreSaved();
    current.resolve(leave);
}
function cancelConfirmation() {
    if (confirmation.mode === 'leave') settleLeave(false);
    else { confirmation.open = false; confirmation.mode = ''; }
}
function confirmConfirmation() {
    if (confirmation.mode === 'leave') { settleLeave(true); return; }
    if (confirmation.mode !== 'reset') return;
    saving.value = true;
    clearFeedback();
    apply(APPEARANCE_DEFAULTS);
    try {
        if (!save()) throw new Error('Defaults could not be saved in this browser. Check that local storage is available, then try again.');
        notice.value = 'Appearance preferences restored to defaults.';
        confirmation.open = false;
        confirmation.mode = '';
    } catch (exception) { error.value = exception.message || 'Appearance preferences could not be reset.'; }
    finally { saving.value = false; }
}
const warnBeforeLeave = (event) => {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = '';
};
window.addEventListener('beforeunload', warnBeforeLeave);
onBeforeRouteLeave(() => dirty.value ? requestLeave() : true);
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeLeave);
    window.removeEventListener('planora:appearance-updated', refreshSavedPreferences);
});
</script>

<style src="../../../../css/pages/app/appearance.css"></style>
