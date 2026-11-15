<template>
    <main class="profile-page">
        <header class="profile-page__header">
            <div>
                <p class="profile-eyebrow">Account</p>
                <h1>My profile</h1>
                <p>Your identity, personal details, and account information in Planora.</p>
            </div>
            <span class="profile-page__context"><i aria-hidden="true" />{{ roleLabel(profile?.role) }}</span>
        </header>

        <div v-if="loading" class="profile-loading" aria-live="polite">
            <span class="profile-loading__bar profile-loading__bar--wide" />
            <span class="profile-loading__bar" />
            <span class="profile-loading__bar profile-loading__bar--short" />
        </div>
        <section v-else-if="loadError && !profile" class="profile-card profile-load-error">
            <h2>Profile unavailable</h2>
            <p>{{ loadError }}</p>
            <button class="profile-button profile-button--primary" type="button" @click="loadProfile">Try again</button>
        </section>
        <template v-else-if="profile">
            <ProfileOverview :user="profile" :editable="false" />
            <div class="profile-page__grid profile-page__grid--single">
                <ProfileAccountOverview :overview="overview" :loading="overviewLoading" :error="overviewError" @retry="retryOverview" />
                <ProfileAccountInformation :user="profile" />
            </div>
        </template>
    </main>
</template>

<script setup>
import { onMounted } from 'vue';
import { useCurrentProfile } from '../../features/profile/composables/useCurrentProfile.js';
import ProfileAccountInformation from '../../features/profile/components/ProfileAccountInformation.vue';
import ProfileAccountOverview from '../../features/profile/components/ProfileAccountOverview.vue';
import ProfileOverview from '../../features/profile/components/ProfileOverview.vue';

const { profile, loading, loadError, load, overview, overviewLoading, overviewError, loadOverview } = useCurrentProfile();

onMounted(() => {
    void loadProfile();
    void loadOverview().catch(() => {});
});

async function loadProfile() {
    try {
        await load();
    } catch {
        // loadError is provided by the profile composable and shown below.
    }
}
async function retryOverview() {
    try {
        await loadOverview();
    } catch {
        // The overview component displays the composable's error state.
    }
}
function roleLabel(role) {
    return ({ user: 'User account', moderator: 'Moderator account', admin: 'Administrator account' })[role] || 'Account';
}
</script>

<style src="../../../css/pages/profile.css"></style>
