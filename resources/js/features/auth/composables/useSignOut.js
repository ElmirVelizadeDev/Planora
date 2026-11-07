import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/authStore.js';

export function useSignOut() {
    const auth = useAuthStore();
    const router = useRouter();
    const loading = ref(false);
    const error = ref('');

    async function signOut() {
        if (loading.value) return false;

        loading.value = true;
        error.value = '';
        try {
            await auth.logout();
            await router.replace({ name: 'login' });
            return true;
        } catch {
            error.value = 'Could not sign out. Please try again.';
            return false;
        } finally {
            loading.value = false;
        }
    }

    return { loading, error, signOut };
}
