<template>
    <main class="profile-page account-settings-page">
        <header class="profile-page__header account-settings-header">
            <div>
                <p class="profile-eyebrow">YOUR ACCOUNT</p>
                <h1>Account Settings</h1>
                <p>Manage your personal information, security, and preferences.</p>
            </div>
            <span v-if="profile" class="profile-page__context"><i aria-hidden="true" />{{ roleLabel(profile.role) }}</span>
        </header>

        <div v-if="notice" class="profile-message profile-message--success" role="status">
            <span aria-hidden="true">✓</span>{{ notice }}
            <button type="button" aria-label="Dismiss notification" @click="notice = ''">×</button>
        </div>
        <div v-if="errorMessage" class="profile-message profile-message--error" role="alert">
            <span aria-hidden="true">!</span>{{ errorMessage }}
            <button type="button" aria-label="Dismiss notification" @click="errorMessage = ''">×</button>
        </div>

        <section v-if="loading && !profile" class="profile-loading" aria-live="polite" aria-label="Loading account settings">
            <span class="profile-loading__bar profile-loading__bar--wide" />
            <span class="profile-loading__bar" />
            <span class="profile-loading__bar profile-loading__bar--short" />
        </section>
        <section v-else-if="loadError && !profile" class="profile-card profile-load-error">
            <h2>Account settings unavailable</h2>
            <p>{{ loadError }}</p>
            <button class="profile-button profile-button--primary" type="button" @click="loadAccount">Try again</button>
        </section>

        <template v-else-if="profile">
        <div v-if="loadError" class="profile-message profile-message--error" role="alert">
            <span aria-hidden="true">!</span>
            <span>{{ loadError }} Your current account details are still shown.</span>
            <button class="account-settings-retry" type="button" :disabled="loading" @click="loadAccount">{{ loading ? 'Retrying…' : 'Try again' }}</button>
        </div>
        <div class="account-settings-layout">
            <nav class="account-settings-navigation" aria-label="Account settings sections">
                <p>ACCOUNT</p>
                <RouterLink v-for="section in sections" :key="section.id" :to="{ hash: `#${section.id}` }" :class="{ 'is-active': activeSection === section.id }" :aria-current="activeSection === section.id ? 'location' : undefined">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path v-if="section.id === 'account-profile'" d="M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-6 6c.4-3 2.4-4.6 6-4.6s5.6 1.6 6 4.6"/><path v-else-if="section.id === 'account-personal'" d="M4 4.5h12v11H4zM7 8h6M7 11h6M7 14h3"/><path v-else-if="section.id === 'account-security'" d="M10 2.8 16 5v4c0 3.8-2.5 6.8-6 8.2C6.5 15.8 4 12.8 4 9V5l6-2.2Zm-2.4 7 1.6 1.6 3.3-3.5"/><path v-else-if="section.id === 'account-preferences'" d="M10 2.8a7.2 7.2 0 1 0 0 14.4h1a1.7 1.7 0 0 0 1.1-3c-.5-.4-.2-1.3.5-1.3h1.1a3.4 3.4 0 0 0 3.4-3.4c0-3.7-3.2-6.7-7.1-6.7Z"/><path v-else d="M4 5.5h12v9H4zM7 8.5h6M7 11.5h3"/><circle v-if="section.id === 'account-preferences'" cx="6.5" cy="9" r=".7"/><circle v-if="section.id === 'account-preferences'" cx="9.3" cy="6.4" r=".7"/><circle v-if="section.id === 'account-preferences'" cx="13" cy="7" r=".7"/></svg>
                    <span>{{ section.label }}</span>
                </RouterLink>
                <div class="account-settings-navigation__account">
                    <UserAvatar :name="profile.name" :image-src="profile.avatar || ''" />
                    <span><strong>{{ profile.name }}</strong><small>{{ profile.email }}</small></span>
                </div>
            </nav>

            <div class="account-settings-content">
                <section id="account-profile" class="account-settings-section" aria-labelledby="account-profile-heading">
                    <div class="account-settings-section-heading"><div><h2 id="account-profile-heading">Profile</h2><p>Your account identity and status.</p></div></div>
                    <ProfileOverview :user="profile" :busy="avatarBusy" :server-error="firstError('avatar')" @upload="uploadAvatar" @remove="removeAvatar" />
                    <ProfileAccountInformation :user="profile" />
                </section>

                <section id="account-personal" class="account-settings-section" aria-labelledby="account-personal-heading">
                    <div class="account-settings-section-heading"><div><h2 id="account-personal-heading">Personal Information</h2><p>Update the details associated with your account.</p></div></div>
                    <ProfilePersonalInformation :user="profile" :errors="fieldErrors" :busy="personalBusy" @save="saveProfile" @clear-error="clearFieldError" />
                </section>

                <section id="account-security" class="account-settings-section" aria-labelledby="account-security-heading">
                    <div class="account-settings-section-heading"><div><h2 id="account-security-heading">Security</h2><p>Keep your sign-in credentials secure.</p></div></div>
                    <ProfileSecurity :errors="fieldErrors" :busy="passwordBusy" :reset-key="passwordResetKey" @save="savePassword" @clear-error="clearFieldError" />
                </section>

                <section id="account-preferences" class="account-settings-section" aria-labelledby="account-preferences-heading">
                    <div class="account-settings-section-heading"><div><h2 id="account-preferences-heading">Preferences</h2><p>Appearance is managed by Planora’s existing account-scoped preference settings.</p></div></div>
                    <div class="account-preferences-card">
                        <div class="account-preferences-card__title"><span class="account-preferences-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M10 2.8a7.2 7.2 0 1 0 0 14.4h1.1a1.7 1.7 0 0 0 1.1-3c-.5-.4-.2-1.3.5-1.3h1.1a3.4 3.4 0 0 0 3.4-3.4c0-3.7-3.2-6.7-7.2-6.7Z"/><circle cx="6.5" cy="9" r=".8"/><circle cx="9.3" cy="6.4" r=".8"/><circle cx="13" cy="7" r=".8"/></svg></span><div><h3>Appearance</h3><p>Your saved interface preferences for this account.</p></div></div>
                        <dl class="account-preferences-list">
                            <div><dt>Theme</dt><dd>{{ capitalize(preferences.theme) }}</dd></div>
                            <div><dt>Accent color</dt><dd><i class="account-settings-accent-dot" :style="{ backgroundColor: preferences.accent }" />{{ accentLabel }}</dd></div>
                            <div><dt>Interface density</dt><dd>{{ capitalize(preferences.density) }}</dd></div>
                            <div><dt>Sidebar default</dt><dd>{{ capitalize(preferences.sidebar) }}</dd></div>
                        </dl>
                        <RouterLink class="profile-button profile-button--secondary account-preferences-link" :to="{ name: 'settings.appearance' }">Manage appearance</RouterLink>
                    </div>
                </section>

                <section id="account-management" class="account-settings-section" aria-labelledby="account-management-heading">
                    <div class="account-settings-section-heading"><div><h2 id="account-management-heading">Account management</h2><p>Information about account closure and data stewardship.</p></div></div>
                    <ProfileDangerZone />
                </section>
            </div>
        </div>
        </template>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useCurrentProfile } from '../../features/profile/composables/useCurrentProfile.js';
import { APPEARANCE_ACCENTS, useAppearancePreferences } from '../../features/appearance/composables/appearancePreferences.js';
import ProfileAccountInformation from '../../features/profile/components/ProfileAccountInformation.vue';
import ProfileOverview from '../../features/profile/components/ProfileOverview.vue';
import ProfilePersonalInformation from '../../features/profile/components/ProfilePersonalInformation.vue';
import ProfileSecurity from '../../features/profile/components/ProfileSecurity.vue';
import ProfileDangerZone from '../../features/profile/components/ProfileDangerZone.vue';
import UserAvatar from '../../shared/components/UserAvatar.vue';

const { profile, loading, loadError, load, update, uploadAvatar: saveAvatar, removeAvatar: deleteAvatar, changePassword } = useCurrentProfile();
const { preferences, load: loadAppearance } = useAppearancePreferences();
const route = useRoute();
const personalBusy = ref(false);
const avatarBusy = ref(false);
const passwordBusy = ref(false);
const passwordResetKey = ref(0);
const fieldErrors = ref({});
const notice = ref('');
const errorMessage = ref('');
const sections = [
    { id: 'account-profile', label: 'Profile' },
    { id: 'account-personal', label: 'Personal Information' },
    { id: 'account-security', label: 'Security' },
    { id: 'account-preferences', label: 'Preferences' },
    { id: 'account-management', label: 'Account management' },
];
const activeSection = computed(() => sections.find((section) => `#${section.id}` === route.hash)?.id || sections[0].id);
const accentLabel = computed(() => APPEARANCE_ACCENTS.find((item) => item.value === preferences.accent)?.label || 'Planora Indigo');

onMounted(() => { void loadAccount(); });

async function loadAccount() {
    try {
        const user = await load();
        loadAppearance(user.id);
        fieldErrors.value = {};
        await nextTick();
        if (route.hash) document.getElementById(activeSection.value)?.scrollIntoView({ block: 'start' });
    } catch {
        // useCurrentProfile retains the last loaded profile and exposes loadError.
    }
}

async function saveProfile(attributes) {
    personalBusy.value = true;
    clearFeedback();
    try {
        await update(attributes);
        notice.value = 'Your personal information has been saved.';
        fieldErrors.value = {};
    } catch (error) {
        setFailure(error, 'Your personal information could not be saved. Try again.');
    } finally {
        personalBusy.value = false;
    }
}

async function uploadAvatar(file) {
    avatarBusy.value = true;
    clearFeedback();
    try {
        await saveAvatar(file);
        notice.value = 'Your profile photo has been updated.';
        fieldErrors.value = {};
    } catch (error) {
        setFailure(error, 'Your profile photo could not be uploaded. Try again.');
    } finally {
        avatarBusy.value = false;
    }
}

async function removeAvatar() {
    avatarBusy.value = true;
    clearFeedback();
    try {
        await deleteAvatar();
        notice.value = 'Your profile photo has been removed.';
        fieldErrors.value = {};
    } catch (error) {
        setFailure(error, 'Your profile photo could not be removed. Try again.');
    } finally {
        avatarBusy.value = false;
    }
}

async function savePassword(attributes) {
    passwordBusy.value = true;
    clearFeedback();
    try {
        const result = await changePassword(attributes);
        passwordResetKey.value += 1;
        notice.value = result.message || 'Your password has been changed.';
        fieldErrors.value = {};
    } catch (error) {
        setFailure(error, 'Your password could not be changed. Review the fields and try again.');
    } finally {
        passwordBusy.value = false;
    }
}

function setFailure(error, fallback) {
    fieldErrors.value = error.response?.data?.errors || {};
    errorMessage.value = error.response?.data?.message || fallback;
}
function firstError(field) {
    const value = fieldErrors.value[field];
    return Array.isArray(value) ? value[0] : (typeof value === 'string' ? value : '');
}
function clearFieldError(field) {
    const next = { ...fieldErrors.value };
    delete next[field];
    fieldErrors.value = next;
}
function clearFeedback() { notice.value = ''; errorMessage.value = ''; fieldErrors.value = {}; }
function roleLabel(role) { return ({ user: 'User account', moderator: 'Moderator account', admin: 'Administrator account' })[role] || 'Account'; }
function capitalize(value) { return String(value || '').replace(/^\w/, (letter) => letter.toUpperCase()); }
</script>

<style src="../../../css/pages/account-settings.css"></style>
