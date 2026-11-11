<template>
    <main class="user-projects-page" :aria-busy="loading">
        <header class="user-projects-header"><div><p class="user-projects-eyebrow">Workspace</p><h1>Projects</h1><p>Projects assigned to you and the work moving them forward.</p></div><span class="user-projects-count">{{ total }} projects</span></header>
        <section class="user-projects-panel" aria-label="Your projects">
            <div class="user-projects-toolbar"><label class="user-projects-search"><span class="sr-only">Search projects</span><input v-model="searchInput" type="search" placeholder="Search your projects…" @input="scheduleSearch"></label><label class="user-projects-status-filter">Status <select v-model="status" :disabled="loading" @change="applyFilters"><option value="">All statuses</option><option value="planned">Planned</option><option value="active">Active</option><option value="on_hold">On hold</option><option value="completed">Completed</option></select></label><button type="button" :disabled="loading" @click="loadProjects">Refresh</button></div>
            <p v-if="error" class="user-projects-error" role="alert">{{ error }} <button type="button" @click="loadProjects">Try again</button></p>
            <div v-else-if="loading && !projects.length" class="user-projects-state" role="status">Loading projects…</div>
            <div v-else-if="!projects.length"><div v-if="!search && !status" class="user-projects-state"><strong>No projects assigned yet</strong><p>Projects assigned to you by an administrator will appear here.</p></div><div v-else class="user-projects-state"><strong>No projects match these filters</strong><p>Try a different name or status.</p></div></div>
            <div v-else class="user-projects-grid"><RouterLink v-for="project in projects" :key="project.id" class="user-project-card" :to="{ name: 'projects.show', params: { projectId: project.id } }"><div class="user-project-card-heading"><h2>{{ project.name }}</h2><span class="user-project-status" :class="`is-${project.status}`">{{ statusLabel(project.status) }}</span></div><p>{{ project.description || 'No project description provided.' }}</p><footer><span>{{ project.start_date ? `Starts ${formatDate(project.start_date)}` : 'Start date not set' }}</span><span>{{ project.due_date ? `Due ${formatDate(project.due_date)}` : 'No due date' }}</span></footer></RouterLink></div>
            <footer v-if="lastPage > 1" class="user-projects-pagination"><span>Showing {{ from }}–{{ to }} of {{ total }} projects</span><div><button type="button" :disabled="page <= 1 || loading" @click="changePage(page - 1)">Previous</button><span>Page {{ page }} of {{ lastPage }}</span><button type="button" :disabled="page >= lastPage || loading" @click="changePage(page + 1)">Next</button></div></footer>
        </section>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { getUserProjects } from '../../../features/projects/user/services/userProjectApi.js';
const projects = ref([]), loading = ref(false), error = ref(''), searchInput = ref(''), search = ref(''), status = ref(''), page = ref(1), total = ref(0), lastPage = ref(1);
const from = computed(() => total.value ? (page.value - 1) * 10 + 1 : 0), to = computed(() => Math.min(page.value * 10, total.value));
let timer, requestSequence = 0;
async function loadProjects() { const sequence = ++requestSequence; loading.value = true; error.value = ''; try { const response = await getUserProjects({ page: page.value, per_page: 10, ...(search.value ? { search: search.value } : {}), ...(status.value ? { status: status.value } : {}) }); if (sequence !== requestSequence) return; projects.value = response.data || []; total.value = Number(response.meta?.total || 0); page.value = Number(response.meta?.current_page || page.value); lastPage.value = Math.max(1, Number(response.meta?.last_page || 1)); } catch (e) { if (sequence === requestSequence) error.value = e.response?.status === 403 ? 'You do not have permission to view these projects.' : 'Projects could not be loaded. Please try again.'; } finally { if (sequence === requestSequence) loading.value = false; } }
function scheduleSearch() { clearTimeout(timer); timer = setTimeout(() => { search.value = searchInput.value.trim(); page.value = 1; loadProjects(); }, 300); }
function changePage(value) { page.value = value; loadProjects(); }
function applyFilters() { page.value = 1; loadProjects(); }
function statusLabel(value) { return ({ planned: 'Planned', active: 'Active', on_hold: 'On hold', completed: 'Completed' })[value] || 'Unknown'; }
function formatDate(value) { if (!value) return '—'; const date = new Date(`${value}T00:00:00`); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date); }
onMounted(loadProjects); onBeforeUnmount(() => clearTimeout(timer));
</script>

<style src="../../../../css/pages/app/projects/styles/ProjectsIndex.css"></style>
