<template>
    <main class="access-page" :aria-busy="loading">
        <header class="access-header"><div><p class="access-eyebrow">ACCESS CONTROL</p><h1>Roles &amp; Permissions</h1><p>Review fixed role boundaries and grant supported access to individual Moderators.</p></div><button class="reports-button reports-button--secondary" type="button" :disabled="loading" :aria-busy="loading" @click="refresh"><svg :class="{ 'is-spinning': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>{{ loading ? 'Refreshing…' : 'Refresh' }}</button></header>
        <p v-if="error" class="access-message is-error" role="alert">{{ error }} <button type="button" @click="load">Try again</button></p>
        <p v-if="success" class="access-message is-success" role="status">{{ success }}</p>
        <div v-if="loading && !loaded" class="access-message" role="status">Loading access policy…</div>
        <template v-else-if="loaded">
            <section class="access-role-grid" aria-label="System roles">
                <article v-for="role in roleCards" :key="role.key" class="access-role-card"><div><span class="access-role-mark">{{ role.key.slice(0, 1).toUpperCase() }}</span><h2>{{ role.name }}</h2></div><p>{{ role.description }}</p><span class="access-role-count">{{ number(roleCounts[role.key]) }} accounts</span><small>{{ role.note }}</small></article>
            </section>
            <section class="access-panel">
                <div class="access-panel-heading"><div><h2>Moderator access</h2><p>Permissions are assigned per account. Changes take effect on the next API request.</p></div><div class="access-search"><input v-model="search" type="search" aria-label="Search Moderators" placeholder="Search moderators..." :disabled="loading" @input="searchModerators"></div></div>
                <div v-if="loading" class="access-message" role="status">Loading Moderators…</div>
                <div v-else-if="!moderators.length" class="access-message"><strong>No Moderators found</strong><span>{{ search ? 'Try a different search.' : 'Accounts assigned the Moderator role will appear here.' }}</span></div>
                <div v-else class="access-table-wrap"><table class="access-table"><thead><tr><th>Moderator</th><th>Status</th><th>Assigned access</th><th>Last sign-in</th><th></th></tr></thead><tbody>
                    <tr v-for="person in moderators" :key="person.id"><td><div class="access-person"><img v-if="person.avatar" :src="person.avatar" alt=""><span v-else class="access-avatar">{{ initials(person.name) }}</span><span><strong>{{ person.name }}</strong><small>{{ person.email }}</small></span></div></td><td><span class="access-status" :class="`is-${person.status}`">{{ label(person.status) }}</span></td><td>{{ permissionSummary(person) }}</td><td>{{ date(person.last_login_at) }}</td><td><button class="access-action" type="button" @click="selectModerator(person)">{{ person.id === selectedId ? 'Selected' : 'Manage access' }}</button></td></tr>
                </tbody></table></div>
                <footer v-if="meta.total" class="access-pagination"><span>{{ number(meta.total) }} Moderators · page {{ meta.current_page }} of {{ meta.last_page }}</span><div><button type="button" :disabled="meta.current_page <= 1 || loading" @click="load(meta.current_page - 1)">Previous</button><button type="button" :disabled="meta.current_page >= meta.last_page || loading" @click="load(meta.current_page + 1)">Next</button></div></footer>
            </section>
            <section v-if="selected" class="access-panel access-editor" aria-labelledby="access-editor-title">
                <div class="access-panel-heading"><div><h2 id="access-editor-title">Manage access for {{ selected.name }}</h2><p>{{ selected.email }} · {{ label(selected.status) }} account</p></div><span class="access-sensitive-note">Sensitive access changes are recorded in Activity Logs.</span></div>
                <fieldset :disabled="saving"><legend class="sr-only">Moderator permissions</legend>
                    <section v-for="group in permissionGroups" :key="group.module" class="access-permission-group"><h3>{{ group.module }}</h3>
                        <label v-for="item in group.items" :key="item.key" class="access-permission" :class="{ 'is-sensitive': item.permission.sensitive, 'is-unassignable': !item.permission.assignable }"><input v-model="draft" type="checkbox" :value="item.key" :disabled="!item.permission.assignable || (!draft.includes(item.key) && missingRequirements(item.permission).length > 0)"><span><strong>{{ item.permission.name }}</strong><small>{{ item.permission.description }}</small><small v-if="item.permission.requires?.length">Requires {{ requirementNames(item.permission) }}</small><small v-if="!item.permission.assignable">Not assignable to Moderators</small></span><span class="access-module">{{ item.permission.module }}</span><span v-if="item.permission.sensitive" class="access-sensitive-badge">Sensitive</span></label>
                    </section>
                </fieldset>
                <div class="access-save-row"><span v-if="changedPermissions.length">Changes: {{ changedPermissions.join(', ') }}</span><span v-else>No changes to save</span><button type="button" :disabled="saving || !changedPermissions.length" @click="save">{{ saving ? 'Saving…' : 'Save permissions' }}</button></div>
            </section>
            <section class="access-panel"><div class="access-panel-heading"><div><h2>Recent access changes</h2><p>Latest permission assignment events</p></div><RouterLink :to="{ name: 'admin.activity-logs.index', query: { event: 'moderator.permissions_changed' } }">View Activity Logs →</RouterLink></div><ul v-if="recentChanges.length" class="access-change-list"><li v-for="event in recentChanges" :key="event.id"><span><strong>{{ event.moderator || 'Moderator account' }}</strong> · {{ event.description }}</span><small>{{ event.actor || 'Administrator' }} · {{ dateTime(event.created_at) }}</small></li></ul><p v-else class="access-message">No access changes have been recorded.</p></section>
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../../shared/services/api.js';

const loading = ref(true); const loaded = ref(false); const saving = ref(false); const error = ref(''); const success = ref('');
const search = ref(''); const moderators = ref([]); const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const roleCounts = ref({ admin: 0, moderator: 0, user: 0 }); const roles = ref({}); const catalog = ref({}); const recentChanges = ref([]);
const selected = ref(null); const selectedId = computed(() => selected.value?.id ?? null); const draft = ref([]); const original = ref([]);
let searchTimer;
let requestActive = false;
const roleCards = computed(() => [
    { key: 'admin', name: 'Admin', description: roles.value.admin || 'Full administrative authority.', note: 'System-managed; this page cannot change Admin authority.' },
    { key: 'moderator', name: 'Moderator', description: roles.value.moderator || 'Restricted access assigned individually.', note: 'Can use only explicitly granted capabilities.' },
    { key: 'user', name: 'User', description: roles.value.user || 'Standard application access.', note: 'No administrative permissions.' },
]);
const permissionGroups = computed(() => {
    const groups = new Map();
    Object.entries(catalog.value).forEach(([key, permission]) => {
        const module = permission.module || 'Other';
        if (!groups.has(module)) groups.set(module, { module, items: [] });
        groups.get(module).items.push({ key, permission });
    });
    return [...groups.values()];
});
const changedPermissions = computed(() => [...new Set([...draft.value, ...original.value])].filter((key) => draft.value.includes(key) !== original.value.includes(key)).map((key) => catalog.value[key]?.name || key));
async function load(page = 1) {
    if (requestActive) return;
    requestActive = true;
    loading.value = true; error.value = ''; success.value = '';
    try {
        const [catalogResponse, moderatorsResponse] = await Promise.all([
            api.get('/admin/roles-permissions'),
            api.get('/admin/roles-permissions/moderators', { params: { search: search.value || undefined, page, per_page: 10 } }),
        ]);
        roles.value = catalogResponse.data.roles || {}; catalog.value = catalogResponse.data.permission_catalog || {};
        moderators.value = moderatorsResponse.data.data || []; meta.value = moderatorsResponse.data.meta;
        roleCounts.value = meta.value.role_counts || roleCounts.value; recentChanges.value = meta.value.recent_access_changes || [];
        if (selected.value) {
            const latest = moderators.value.find((person) => person.id === selected.value.id);
            if (latest) selectModerator(latest); else { selected.value = null; draft.value = []; original.value = []; }
        }
        loaded.value = true;
    } catch (cause) { error.value = cause.response?.data?.message || 'Access information could not be loaded.'; }
    finally { loading.value = false; requestActive = false; }
}
function refresh() { return load(meta.value.current_page || 1); }
function searchModerators() { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(1), 250); }
function selectModerator(person) { selected.value = person; original.value = [...(person.moderator_permissions || [])]; draft.value = [...original.value]; success.value = ''; }
async function save() {
    if (!selected.value || !changedPermissions.value.length || saving.value) return;
    const before = original.value.map((key) => catalog.value[key]?.name || key);
    const after = draft.value.map((key) => catalog.value[key]?.name || key);
    const summary = `Update permissions for ${selected.value.name}?\n\nBefore: ${before.join(', ') || 'None'}\nAfter: ${after.join(', ') || 'None'}`;
    if (!window.confirm(summary)) return;
    saving.value = true; error.value = ''; success.value = '';
    try {
        const { data } = await api.put(`/admin/roles-permissions/moderators/${selected.value.id}`, { permissions: draft.value });
        const updated = data.user; selected.value = updated; original.value = [...(updated.moderator_permissions || [])]; draft.value = [...original.value];
        moderators.value = moderators.value.map((person) => person.id === updated.id ? updated : person);
        success.value = `Permissions for ${updated.name} were updated.`;
        await load(meta.value.current_page);
    } catch (cause) { error.value = cause.response?.data?.message || 'Permissions could not be saved. Review the selected access and try again.'; }
    finally { saving.value = false; }
}
function permissionSummary(person) { const names = (person.moderator_permissions || []).map((key) => catalog.value[key]?.name || key); return names.join(', ') || 'No permissions assigned'; }
function missingRequirements(permission) { return (permission.requires || []).filter((required) => !draft.value.includes(required)); }
function requirementNames(permission) { return (permission.requires || []).map((required) => catalog.value[required]?.name || required).join(', '); }
function initials(value) { return String(value || '').trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'M'; }
function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function number(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
function date(value) { return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : 'Never'; }
function dateTime(value) { return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'; }
onMounted(() => load());
</script>

<style src="../../../../css/pages/admin/roles/RolesPermissionsIndex.css" scoped></style>
