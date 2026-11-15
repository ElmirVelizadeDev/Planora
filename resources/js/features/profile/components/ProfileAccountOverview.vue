<template>
    <section class="profile-card profile-work-overview" aria-labelledby="profile-work-overview-title">
        <div class="profile-card__heading">
            <div>
                <p class="profile-eyebrow">Your work</p>
                <h2 id="profile-work-overview-title">Account overview</h2>
            </div>
            <span class="profile-account-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M4 16V9m4 7V5m4 11v-4m4 4V7" /></svg></span>
        </div>

        <div v-if="loading" class="profile-work-overview__loading" aria-live="polite" aria-label="Loading your work overview">
            <span v-for="item in 5" :key="item" class="profile-loading__bar" />
        </div>
        <div v-else-if="error" class="profile-work-overview__error" role="alert">
            <p>{{ error }}</p>
            <button class="profile-button profile-button--secondary" type="button" @click="$emit('retry')">Try again</button>
        </div>
        <template v-else-if="overview">
            <p v-if="isEmpty" class="profile-work-overview__empty">No projects or tasks are linked to your account yet.</p>
            <div class="profile-work-overview__groups">
                <div class="profile-work-overview__group">
                    <div class="profile-work-overview__group-heading">
                        <h3>Projects you own</h3>
                        <span>Current projects</span>
                    </div>
                    <div class="profile-work-overview__metrics profile-work-overview__metrics--projects">
                        <article class="profile-work-metric">
                            <span>Total projects</span>
                            <strong>{{ format(overview.projects.total) }}</strong>
                        </article>
                        <article class="profile-work-metric">
                            <span>Completed</span>
                            <strong>{{ format(overview.projects.completed) }}</strong>
                        </article>
                    </div>
                </div>
                <div class="profile-work-overview__group">
                    <div class="profile-work-overview__group-heading">
                        <h3>Tasks you own or are assigned</h3>
                        <span>Active task records</span>
                    </div>
                    <div class="profile-work-overview__metrics profile-work-overview__metrics--tasks">
                        <article class="profile-work-metric">
                            <span>Total tasks</span>
                            <strong>{{ format(overview.tasks.total) }}</strong>
                        </article>
                        <article class="profile-work-metric profile-work-metric--success">
                            <span>Completed</span>
                            <strong>{{ format(overview.tasks.completed) }}</strong>
                        </article>
                        <article class="profile-work-metric profile-work-metric--pending">
                            <span>Pending</span>
                            <strong>{{ format(overview.tasks.pending) }}</strong>
                        </article>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    overview: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
defineEmits(['retry']);

const isEmpty = computed(() => props.overview?.projects?.total === 0 && props.overview?.tasks?.total === 0);
const numberFormatter = new Intl.NumberFormat();
function format(value) {
    return numberFormatter.format(Number.isFinite(Number(value)) ? Number(value) : 0);
}
</script>
