import { ref } from 'vue';
import { getCurrentUser } from '../../auth/services/authApi.js';
import { useAuthStore } from '../../auth/stores/authStore.js';
import {
    changeCurrentPassword,
    getCurrentProfileOverview,
    removeCurrentAvatar,
    updateCurrentAvatar,
    updateCurrentProfile,
} from '../services/profileApi.js';

export function useCurrentProfile() {
    const auth = useAuthStore();
    const profile = ref(null);
    const loading = ref(true);
    const loadError = ref('');
    const overview = ref(null);
    const overviewLoading = ref(true);
    const overviewError = ref('');

    function applyUser(user) {
        profile.value = user;
        auth.user = user;
        return user;
    }

    async function load() {
        loading.value = true;
        loadError.value = '';
        try {
            const response = await getCurrentUser();
            return applyUser(response.data.data ?? response.data);
        } catch (error) {
            loadError.value = error.response?.data?.message || 'Your account information could not be loaded. Try again.';
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function loadOverview() {
        overviewLoading.value = true;
        overviewError.value = '';
        try {
            const response = await getCurrentProfileOverview();
            overview.value = response.data.data ?? response.data;
            return overview.value;
        } catch (error) {
            overviewError.value = error.response?.data?.message || 'Your work overview could not be loaded.';
            throw error;
        } finally {
            overviewLoading.value = false;
        }
    }

    async function update(attributes) {
        const response = await updateCurrentProfile(attributes);
        return applyUser(response.data.user);
    }

    async function uploadAvatar(file) {
        const response = await updateCurrentAvatar(file);
        return applyUser(response.data.user);
    }

    async function removeAvatar() {
        const response = await removeCurrentAvatar();
        return applyUser(response.data.user);
    }

    async function changePassword(attributes) {
        const response = await changeCurrentPassword(attributes);
        if (response.data.user) applyUser(response.data.user);
        return response.data;
    }

    return { profile, loading, loadError, load, overview, overviewLoading, overviewError, loadOverview, update, uploadAvatar, removeAvatar, changePassword };
}
