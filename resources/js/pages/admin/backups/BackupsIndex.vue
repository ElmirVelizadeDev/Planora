<template>
    <main class="admin-backups" aria-labelledby="backups-title" :aria-busy="loading || working">
        <header class="admin-backups__header">
            <div>
                <p class="admin-backups__eyebrow">ADMINISTRATION / RESILIENCE</p>
                <h1 id="backups-title">Backups &amp; Recovery</h1>
                <p>Protect Planora data and manage verified recovery points.</p>
            </div>
            <div class="admin-backups__header-actions">
                <button class="admin-backups__secondary admin-backups__refresh" type="button" title="Refresh backup history" aria-label="Refresh backup history" :disabled="loading || working" :aria-busy="loading" @click="load">
                    <svg class="admin-backups__refresh-icon" :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>
                    <span>{{ loading ? 'Refreshing...' : 'Refresh' }}</span>
                </button>
                <div class="admin-backups__create-wrap">
                    <button ref="createTrigger" class="admin-backups__primary" type="button" :disabled="working" :aria-expanded="createOpen" aria-haspopup="menu" aria-controls="backup-create-menu" @click="toggleCreateMenu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>{{ working ? 'Working…' : 'Create backup' }}</button>
                </div>
            </div>
        </header>

        <p v-if="feedback" class="admin-backups__feedback" :class="`is-${feedback.type}`" :role="feedback.type === 'error' ? 'alert' : 'status'">{{ feedback.message }}</p>

        <section class="admin-backups__metrics" aria-label="Backup overview">
            <article><span>Total backups</span><strong>{{ summary.total_backups ?? '—' }}</strong><small>Recovery points in history</small></article>
            <article><span>Latest successful</span><strong class="admin-backups__metric-date">{{ formatDate(summary.latest_successful?.created_at) }}</strong><small>{{ summary.latest_successful?.type || 'No verified backup yet' }}</small></article>
            <article><span>Latest failed</span><strong class="admin-backups__metric-date">{{ summary.latest_failed ? formatDate(summary.latest_failed.created_at) : 'None' }}</strong><small>{{ summary.latest_failed?.type || 'No failed runs recorded' }}</small></article>
            <article><span>Backup storage</span><strong>{{ formatBytes(summary.storage_bytes || 0) }}</strong><small>Encrypted artifacts</small></article>
        </section>

        <section class="admin-backups__panel" aria-labelledby="backup-history-title">
            <div class="admin-backups__panel-heading">
                <div><h2 id="backup-history-title">Backup history</h2><p>Encrypted archives are stored on the configured private disk.</p></div>
                <span class="admin-backups__status-chip" :class="`is-${summary.last_status || 'none'}`"><i aria-hidden="true"></i>{{ statusLabel(summary.last_status) }}</span>
            </div>
            <div class="admin-backups__toolbar">
                <label class="admin-backups__search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.4"/><path d="m12.5 12.5 4 4"/></svg><input v-model="search" type="search" aria-label="Search backups" placeholder="Search backup history" @input="scheduleSearch"></label>
                <label><select v-model="typeFilter" aria-label="Filter backup type" @change="applyFilters"><option value="">All types</option><option value="full">Full</option><option value="database">Database</option><option value="files">Files</option></select></label>
                <label><select v-model="statusFilter" aria-label="Filter backup status" @change="applyFilters"><option value="">All statuses</option><option value="successful">Successful</option><option value="failed">Failed</option><option value="running">Running</option></select></label>
            </div>

            <p v-if="error" class="admin-backups__error" role="alert">{{ error }} <button type="button" :disabled="loading" @click="load">Try again</button></p>
            <div v-else-if="loading && !backups.length" class="admin-backups__loading" role="status"><span></span>Loading backup history…</div>
            <div v-else-if="!backups.length" class="admin-backups__empty">
                <span aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="7.5" ry="3"/><path d="M4.5 5v6c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3V5M4.5 11v6c0 1.7 3.4 3 7.5 3 2 0 3.9-.3 5.3-.9"/><path d="m16 17 2 2 4-4"/></svg></span>
                <strong>{{ hasFilters ? 'No matching backups' : 'No backups yet' }}</strong>
                <p>{{ hasFilters ? 'Change the filters or search term and try again.' : 'Create an encrypted backup to establish your first recovery point.' }}</p>
            </div>
            <div v-else class="admin-backups__table-wrap">
                <table>
                    <thead><tr><th scope="col">Backup</th><th scope="col">Type</th><th scope="col">Created</th><th scope="col">Size</th><th scope="col">Storage</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        <tr v-for="item in backups" :key="item.id">
                            <td><button class="admin-backups__filename" type="button" @click="showDetails(item)">{{ item.filename }}</button><small>{{ item.id }}</small></td>
                            <td><span class="admin-backups__type">{{ typeLabel(item.type) }}</span></td>
                            <td>{{ formatDate(item.created_at) }}</td>
                            <td>{{ formatBytes(item.size_bytes) }}</td>
                            <td>{{ item.storage_location }}</td>
                            <td><span class="admin-backups__status-chip" :class="`is-${item.status}`"><i aria-hidden="true"></i>{{ item.verified ? 'Verified' : statusLabel(item.status) }}</span></td>
                            <td><div class="admin-backups__row-actions">
                                <button type="button" :aria-label="`View ${item.filename} details`" @click="showDetails(item)">Details</button>
                                <button type="button" :disabled="working || item.status !== 'successful'" :aria-label="`Download ${item.filename}`" @click="download(item)">Download</button>
                                <button type="button" :disabled="working || item.status !== 'successful'" @click="requestAction('restore', item)">Restore</button>
                                <button class="is-danger" type="button" :disabled="working" @click="requestAction('delete', item)">Delete</button>
                            </div></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer v-if="meta.total" class="admin-backups__pagination">
                <span>Showing {{ rangeStart }}–{{ rangeEnd }} of {{ meta.total }} backups</span>
                <div><button type="button" :disabled="meta.current_page <= 1 || loading" @click="changePage(meta.current_page - 1)">Previous</button><span>Page {{ meta.current_page }} of {{ meta.last_page }}</span><button type="button" :disabled="meta.current_page >= meta.last_page || loading" @click="changePage(meta.current_page + 1)">Next</button></div>
            </footer>
        </section>

        <Teleport to="body">
            <div v-if="createOpen" id="backup-create-menu" ref="createMenu" class="admin-backups__create-menu" :style="createMenuStyle" role="menu" aria-label="Backup type" @keydown="onCreateMenuKeydown">
                <button type="button" role="menuitem" @click="startBackup('full')">Full backup <span>Database and uploaded files</span></button>
                <button type="button" role="menuitem" @click="startBackup('database')">Database only <span>MySQL tables and data</span></button>
                <button type="button" role="menuitem" @click="startBackup('files')">Files only <span>Managed uploads and app metadata</span></button>
            </div>
        </Teleport>

        <div v-if="selected" class="admin-backups__modal-backdrop" @click.self="selected = null">
            <section class="admin-backups__details" role="dialog" aria-modal="true" aria-labelledby="backup-details-title">
                <button class="admin-backups__close" type="button" aria-label="Close backup details" @click="selected = null">×</button>
                <p class="admin-backups__eyebrow">RECOVERY POINT</p><h2 id="backup-details-title">Backup details</h2>
                <dl><div><dt>Filename</dt><dd>{{ selected.filename }}</dd></div><div><dt>Type</dt><dd>{{ typeLabel(selected.type) }}</dd></div><div><dt>Created</dt><dd>{{ formatDate(selected.created_at) }}</dd></div><div><dt>Completed</dt><dd>{{ formatDate(selected.completed_at) }}</dd></div><div><dt>Size</dt><dd>{{ formatBytes(selected.size_bytes) }}</dd></div><div><dt>Storage</dt><dd>{{ selected.storage_location }}</dd></div><div><dt>Status</dt><dd>{{ statusLabel(selected.status) }}</dd></div><div><dt>Integrity</dt><dd>{{ selected.verified ? 'Verified' : 'Not verified' }}</dd></div><div><dt>Components</dt><dd>{{ componentSummary(selected.components) }}</dd></div></dl>
                <p v-if="selected.failure_code" class="admin-backups__detail-note">Failure reference: {{ selected.failure_code }}. Server diagnostics are available in protected application logs.</p>
            </section>
        </div>

        <AppConfirmDialog :open="confirmOpen" :title="confirmTitle" :message="confirmMessage" :confirm-label="confirmLabel" :cancel-label="'Cancel'" :danger="true" @cancel="confirmOpen = false" @confirm="confirmAction" />
    </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppConfirmDialog from '../../../shared/components/AppConfirmDialog.vue';
import { getAnchoredPopoverStyle } from '../../../shared/composables/getAnchoredPopoverStyle.js';
import { createBackup, deleteBackup, downloadBackup, fetchBackups, restoreBackup } from '../../../features/backups/admin/services/backupsApi.js';

const backups = ref([]);
const summary = ref({});
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const loading = ref(false);
const working = ref(false);
const error = ref('');
const feedback = ref(null);
const search = ref('');
const typeFilter = ref('');
const statusFilter = ref('');
const createOpen = ref(false);
const createTrigger = ref(null);
const createMenu = ref(null);
const createMenuStyle = ref({});
const selected = ref(null);
const confirmOpen = ref(false);
const pending = ref(null);
let searchTimer = null;

const hasFilters = computed(() => Boolean(search.value || typeFilter.value || statusFilter.value));
const rangeStart = computed(() => meta.value.total ? (meta.value.current_page - 1) * meta.value.per_page + 1 : 0);
const rangeEnd = computed(() => Math.min(meta.value.current_page * meta.value.per_page, meta.value.total));
const confirmTitle = computed(() => pending.value?.kind === 'restore' ? 'Restore this recovery point?' : 'Delete this backup?');
const confirmLabel = computed(() => pending.value?.kind === 'restore' ? 'Restore backup' : 'Delete backup');
const confirmMessage = computed(() => pending.value?.kind === 'restore'
    ? `Planora will create and verify a fresh full safety backup first. The application will briefly enter maintenance while database tables are staged and swapped. This will replace current data with ${pending.value.item.filename}.`
    : `This permanently removes ${pending.value?.item.filename}. The only remaining verified backup cannot be deleted.`);

function positionCreateMenu() {
    if (!createOpen.value || !createTrigger.value || !createMenu.value) return;
    createMenuStyle.value = getAnchoredPopoverStyle(createTrigger.value, {
        width: 260,
        maxHeight: 300,
        gutter: 8,
        gap: 7,
        alignment: 'end',
        viewportAware: true,
    });
}
function addCreateMenuListeners() {
    document.addEventListener('pointerdown', onCreateMenuOutsidePointer);
    document.addEventListener('keydown', onCreateMenuDocumentKeydown);
    window.addEventListener('resize', positionCreateMenu, { passive: true });
    window.addEventListener('scroll', positionCreateMenu, { passive: true, capture: true });
    window.visualViewport?.addEventListener('resize', positionCreateMenu, { passive: true });
    window.visualViewport?.addEventListener('scroll', positionCreateMenu, { passive: true });
}
function removeCreateMenuListeners() {
    document.removeEventListener('pointerdown', onCreateMenuOutsidePointer);
    document.removeEventListener('keydown', onCreateMenuDocumentKeydown);
    window.removeEventListener('resize', positionCreateMenu);
    window.removeEventListener('scroll', positionCreateMenu, true);
    window.visualViewport?.removeEventListener('resize', positionCreateMenu);
    window.visualViewport?.removeEventListener('scroll', positionCreateMenu);
}
async function toggleCreateMenu() {
    if (createOpen.value) {
        closeCreateMenu();
        return;
    }
    createOpen.value = true;
    await nextTick();
    positionCreateMenu();
    addCreateMenuListeners();
    createMenu.value?.querySelector('[role="menuitem"]')?.focus({ preventScroll: true });
}
function closeCreateMenu(restoreFocus = false) {
    const shouldRestoreFocus = restoreFocus && Boolean(createMenu.value?.contains(document.activeElement));
    createOpen.value = false;
    createMenuStyle.value = {};
    removeCreateMenuListeners();
    if (shouldRestoreFocus) nextTick(() => createTrigger.value?.focus({ preventScroll: true }));
}
function onCreateMenuOutsidePointer(event) {
    if (createTrigger.value?.contains(event.target) || createMenu.value?.contains(event.target)) return;
    closeCreateMenu();
}
function onCreateMenuDocumentKeydown(event) {
    if (event.key !== 'Escape' || !createOpen.value) return;
    event.preventDefault();
    closeCreateMenu(true);
}
function onCreateMenuKeydown(event) {
    const items = [...(createMenu.value?.querySelectorAll('[role="menuitem"]') || [])];
    const current = items.indexOf(document.activeElement);
    let target = null;
    if (event.key === 'ArrowDown') target = items[(current + 1 + items.length) % items.length];
    if (event.key === 'ArrowUp') target = items[(current - 1 + items.length) % items.length];
    if (event.key === 'Home') target = items[0];
    if (event.key === 'End') target = items.at(-1);
    if (target) {
        event.preventDefault();
        target.focus({ preventScroll: true });
    }
}

async function load() {
    if (loading.value) return;
    loading.value = true;
    error.value = '';
    try {
        const response = await fetchBackups({ page: meta.value.current_page || 1, per_page: 15, search: search.value.trim() || undefined, type: typeFilter.value || undefined, status: statusFilter.value || undefined });
        backups.value = response.data.data || [];
        summary.value = response.data.summary || {};
        meta.value = response.data.meta || meta.value;
    } catch {
        error.value = 'Backup history could not be loaded. Please retry.';
    } finally {
        loading.value = false;
    }
}
function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { meta.value.current_page = 1; load(); }, 300);
}
function applyFilters() { meta.value.current_page = 1; load(); }
async function startBackup(type) {
    closeCreateMenu();
    working.value = true;
    feedback.value = null;
    try {
        const response = await createBackup(type);
        feedback.value = { type: 'success', message: `${typeLabel(type)} completed and passed integrity verification.` };
        meta.value.current_page = 1;
        await load();
        selected.value = response.data.data;
    } catch (exception) {
        feedback.value = { type: 'error', message: exception?.response?.data?.message || 'Backup failed. Review the operation and try again.' };
    } finally {
        working.value = false;
    }
}
function requestAction(kind, item) { pending.value = { kind, item }; confirmOpen.value = true; }
async function confirmAction() {
    if (!pending.value) return;
    const action = pending.value;
    confirmOpen.value = false;
    working.value = true;
    feedback.value = null;
    try {
        if (action.kind === 'restore') {
            await restoreBackup(action.item.id);
            feedback.value = { type: 'success', message: 'Recovery completed and verified. The pre-restore safety backup remains available.' };
        } else {
            await deleteBackup(action.item.id);
            feedback.value = { type: 'success', message: 'Backup deleted.' };
        }
        if (selected.value?.id === action.item.id) selected.value = null;
        await load();
    } catch (exception) {
        feedback.value = { type: 'error', message: exception?.response?.data?.message || 'The requested backup operation could not be completed.' };
    } finally {
        pending.value = null;
        working.value = false;
    }
}
async function download(item) {
    working.value = true;
    feedback.value = null;
    try {
        const response = await downloadBackup(item.id);
        const url = URL.createObjectURL(response.data);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = item.filename;
        anchor.click();
        URL.revokeObjectURL(url);
        feedback.value = { type: 'success', message: 'Encrypted backup download started.' };
    } catch {
        feedback.value = { type: 'error', message: 'This backup could not be verified or downloaded.' };
    } finally { working.value = false; }
}
async function showDetails(item) { selected.value = item; }
async function changePage(page) { meta.value.current_page = page; await load(); }
function typeLabel(type) { return ({ full: 'Full', database: 'Database', files: 'Files' })[type] || type || '—'; }
function statusLabel(status) { return ({ successful: 'Successful', failed: 'Failed', running: 'In progress', none: 'No backups' })[status] || 'Unknown'; }
function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}
function formatBytes(bytes) {
    if (!Number.isFinite(Number(bytes))) return '—';
    const value = Number(bytes);
    if (value < 1024) return `${value} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let size = value / 1024;
    let unit = units[0];
    for (let index = 1; size >= 1024 && index < units.length; index++) { size /= 1024; unit = units[index]; }
    return `${size.toFixed(size >= 10 ? 0 : 1)} ${unit}`;
}
function componentSummary(components = {}) {
    return Object.entries(components).map(([name, detail]) => `${typeLabel(name)}${detail?.tables ? ` · ${detail.tables} tables` : ''}${detail?.files ? ` · ${detail.files} files` : ''}`).join(' / ') || '—';
}
onMounted(load);
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    removeCreateMenuListeners();
});
</script>

<style src="../../../../css/features/backups/admin/index.css"></style>
