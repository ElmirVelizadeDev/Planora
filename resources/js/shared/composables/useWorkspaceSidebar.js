import { ref, watch } from 'vue';
import { useAppearancePreferences } from '../../features/appearance/composables/appearancePreferences.js';

export function useWorkspaceSidebar() {
    const { preferences } = useAppearancePreferences();
    const sidebarCollapsed = ref(preferences.sidebar === 'collapsed');
    const mobileSidebarOpen = ref(false);
    watch(() => preferences.sidebar, (value) => { sidebarCollapsed.value = value === 'collapsed'; });

    function toggleSidebar() {
        if (window.matchMedia('(max-width: 820px)').matches) {
            mobileSidebarOpen.value = !mobileSidebarOpen.value;
            return;
        }

        sidebarCollapsed.value = !sidebarCollapsed.value;
    }

    return { sidebarCollapsed, mobileSidebarOpen, toggleSidebar };
}
