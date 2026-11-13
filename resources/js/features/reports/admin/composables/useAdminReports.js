import { onBeforeUnmount, reactive, ref } from 'vue';
import { exportAdminReport, getAdminReport, getAdminReportFilterOptions } from '../services/adminReportsApi.js';

export function useAdminReports() {
    const report = ref(null);
    const loading = ref(false);
    const exporting = ref(false);
    const error = ref('');
    const exportError = ref('');
    const generatedAt = ref(null);
    const filters = reactive({ from: '', to: '', user_id: '', project_id: '', task_status: '', category_id: '' });
    const options = reactive({ users: [], projects: [], categories: [] });
    const optionLoading = reactive({ users: false, projects: false, categories: false });
    const optionErrors = reactive({ users: '', projects: '', categories: '' });
    let reportController;
    let reportSequence = 0;
    const optionControllers = {};
    const optionSequences = { users: 0, projects: 0, categories: 0 };
    const optionTimers = {};
    let exportController;
    let unmounted = false;

    function params(source = filters) {
        return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== '' && value != null));
    }

    async function load() {
        const sequence = ++reportSequence;
        reportController?.abort();
        reportController = new AbortController();
        loading.value = true;
        error.value = '';
        try {
            const response = await getAdminReport(params(), reportController.signal);
            if (sequence !== reportSequence) return false;
            report.value = response.data?.data ?? null;
            generatedAt.value = report.value?.generated_at ?? null;
            return true;
        } catch (requestError) {
            if (sequence !== reportSequence || requestError.code === 'ERR_CANCELED') return false;
            error.value = requestError.response?.data?.message || 'Reports could not be loaded. Please try again.';
            return false;
        } finally {
            if (sequence === reportSequence) loading.value = false;
        }
    }

    function searchOptions(type, search = '') {
        if (!Object.hasOwn(optionSequences, type)) return;
        const sequence = ++optionSequences[type];
        clearTimeout(optionTimers[type]);
        optionControllers[type]?.abort();
        optionLoading[type] = true;
        optionErrors[type] = '';
        optionTimers[type] = setTimeout(async () => {
            const controller = new AbortController();
            optionControllers[type] = controller;
            try {
                const response = await getAdminReportFilterOptions(type, search, controller.signal);
                if (!unmounted && sequence === optionSequences[type]) options[type] = response.data?.data ?? [];
            } catch (requestError) {
                if (!unmounted && requestError.code !== 'ERR_CANCELED' && sequence === optionSequences[type]) {
                    options[type] = [];
                    optionErrors[type] = requestError.response?.data?.message || 'Options could not be loaded.';
                }
            } finally {
                if (!unmounted && sequence === optionSequences[type]) optionLoading[type] = false;
            }
        }, 180);
    }

    async function exportCsv() {
        if (exporting.value) return;
        const controller = new AbortController();
        const exportFilters = params();
        exportController = controller;
        exporting.value = true;
        exportError.value = '';
        try {
            const response = await exportAdminReport(exportFilters, controller.signal);
            if (unmounted || exportController !== controller) return;
            const contentDisposition = response.headers?.['content-disposition'] || '';
            const filename = contentDisposition.match(/filename="?([^";]+)"?/i)?.[1] || 'planora-report.csv';
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (requestError) {
            if (unmounted || requestError.code === 'ERR_CANCELED') return;
            exportError.value = requestError.response?.status === 403
                ? 'You do not have permission to export reports.'
                : 'The report could not be exported. Please try again.';
        } finally {
            if (exportController === controller && !unmounted) exporting.value = false;
        }
    }

    onBeforeUnmount(() => {
        unmounted = true;
        reportSequence += 1;
        reportController?.abort();
        Object.keys(optionSequences).forEach((type) => { optionSequences[type] += 1; });
        Object.values(optionTimers).forEach(clearTimeout);
        Object.values(optionControllers).forEach((controller) => controller?.abort());
        exportController?.abort();
    });

    return { report, loading, exporting, error, exportError, generatedAt, filters, options, optionLoading, optionErrors, load, searchOptions, exportCsv };
}
