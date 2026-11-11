import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import {
    createAdminProject,
    deleteAdminProject,
    getAdminProject,
    getAdminProjectOwners,
    getAdminProjects,
    updateAdminProject,
} from '../services/adminProjectApi.js';

export const ADMIN_PROJECT_STATUSES = [
    { value: 'planned', label: 'Planned' },
    { value: 'active', label: 'Active' },
    { value: 'on_hold', label: 'On hold' },
    { value: 'completed', label: 'Completed' },
];

const PER_PAGE = 10;

export function useAdminProjects() {
    const projects = ref([]), owners = ref([]), ownersError = ref('');
    const loading = ref(false), ownersLoading = ref(false), error = ref('');
    const searchInput = ref(''), search = ref(''), status = ref('');
    const page = ref(1), total = ref(0), lastPage = ref(1);
    const statistics = ref({ total: 0, active: 0, completed: 0, on_hold: 0 });
    const detail = ref(null), detailLoading = ref(false), detailError = ref(''), detailNotFound = ref(false);
    const dialog = reactive({ type: '', project: null, saving: false, error: '', errors: {} });
    const hasFilters = computed(() => Boolean(search.value || status.value));
    const rangeStart = computed(() => total.value ? (page.value - 1) * PER_PAGE + 1 : 0);
    const rangeEnd = computed(() => Math.min(page.value * PER_PAGE, total.value));
    let searchTimer, requestSequence = 0, detailRequestSequence = 0;

    async function loadProjects() {
        const sequence = ++requestSequence;
        loading.value = true;
        error.value = '';
        try {
            const response = await getAdminProjects({
                page: page.value,
                per_page: PER_PAGE,
                ...(search.value ? { search: search.value } : {}),
                ...(status.value ? { status: status.value } : {}),
            });
            if (sequence !== requestSequence) return;
            projects.value = response.data || [];
            total.value = Number(response.meta?.total || 0);
            page.value = Number(response.meta?.current_page || page.value);
            lastPage.value = Math.max(1, Number(response.meta?.last_page || 1));
            statistics.value = { ...statistics.value, ...(response.meta?.statistics || {}) };
            if (page.value > lastPage.value) {
                page.value = lastPage.value;
                await loadProjects();
            }
        } catch (requestError) {
            if (sequence === requestSequence) {
                error.value = requestError.response?.status === 403
                    ? 'You do not have permission to manage projects.'
                    : 'Project information could not be loaded. Please try again.';
            }
        } finally {
            if (sequence === requestSequence) loading.value = false;
        }
    }

    async function loadOwners() {
        ownersLoading.value = true;
        ownersError.value = '';
        try {
            const response = await getAdminProjectOwners();
            owners.value = response.data || [];
        } catch {
            ownersError.value = 'Eligible project owners could not be loaded. Refresh the page and try again.';
        } finally {
            ownersLoading.value = false;
        }
    }

    async function loadProject(projectId) {
        const sequence = ++detailRequestSequence;
        detail.value = null;
        detailLoading.value = true;
        detailError.value = '';
        detailNotFound.value = false;
        try {
            const response = await getAdminProject(projectId);
            if (sequence !== detailRequestSequence) return;
            detail.value = response.project || response.data || response;
        } catch (requestError) {
            if (sequence !== detailRequestSequence) return;
            detailNotFound.value = requestError.response?.status === 404;
            detailError.value = detailNotFound.value
                ? 'This project could not be found.'
                : 'Project details could not be loaded. Please try again.';
        } finally {
            if (sequence === detailRequestSequence) detailLoading.value = false;
        }
    }

    function refreshProjects() {
        if (!loading.value) return loadProjects();
        return undefined;
    }

    function onSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            search.value = searchInput.value.trim();
            page.value = 1;
            loadProjects();
        }, 300);
    }

    function applyFilters() {
        clearTimeout(searchTimer);
        search.value = searchInput.value.trim();
        page.value = 1;
        loadProjects();
    }

    function clearFilters() {
        clearTimeout(searchTimer);
        searchInput.value = '';
        search.value = '';
        status.value = '';
        page.value = 1;
        loadProjects();
    }

    function goToPage(nextPage) {
        if (loading.value || nextPage < 1 || nextPage > lastPage.value || nextPage === page.value) return;
        page.value = nextPage;
        loadProjects();
    }

    function openCreate() {
        dialog.type = 'create';
        dialog.project = null;
        dialog.error = '';
        dialog.errors = {};
    }

    function openEdit(project) {
        dialog.type = 'edit';
        dialog.project = project;
        dialog.error = '';
        dialog.errors = {};
    }

    function openDelete(project) {
        dialog.type = 'delete';
        dialog.project = project;
        dialog.error = '';
        dialog.errors = {};
    }

    function closeDialog() {
        if (dialog.saving) return;
        dialog.type = '';
        dialog.project = null;
        dialog.error = '';
        dialog.errors = {};
    }

    async function saveProject(payload, { refreshList = true } = {}) {
        if (dialog.saving || !['create', 'edit'].includes(dialog.type)) return false;
        dialog.saving = true;
        dialog.error = '';
        dialog.errors = {};
        try {
            const response = dialog.type === 'create'
                ? await createAdminProject(payload)
                : await updateAdminProject(dialog.project.id, payload);
            if (detail.value && response.project?.id === detail.value.id) detail.value = response.project;
            dialog.saving = false;
            closeDialog();
            if (refreshList) await loadProjects();
            return true;
        } catch (requestError) {
            dialog.errors = requestError.response?.data?.errors || {};
            dialog.error = requestError.response?.data?.message || 'The project could not be saved. Please review the fields and try again.';
            return false;
        } finally {
            dialog.saving = false;
        }
    }

    async function confirmDelete({ refreshList = true } = {}) {
        if (!dialog.project || dialog.saving) return false;
        dialog.saving = true;
        dialog.error = '';
        try {
            await deleteAdminProject(dialog.project.id);
            if (detail.value?.id === dialog.project.id) detail.value = null;
            dialog.saving = false;
            closeDialog();
            if (refreshList) await loadProjects();
            return true;
        } catch (requestError) {
            dialog.error = requestError.response?.data?.message || 'The project could not be deleted. Please try again.';
            return false;
        } finally {
            dialog.saving = false;
        }
    }

    onBeforeUnmount(() => clearTimeout(searchTimer));

    return {
        projects, owners, ownersError, ownersLoading, loading, error,
        searchInput, search, status, page, total, lastPage, statistics,
        detail, detailLoading, detailError, detailNotFound, dialog,
        hasFilters, rangeStart, rangeEnd, perPage: PER_PAGE,
        loadProjects, loadOwners, loadProject, refreshProjects,
        onSearch, applyFilters, clearFilters, goToPage,
        openCreate, openEdit, openDelete, closeDialog, saveProject, confirmDelete,
    };
}
