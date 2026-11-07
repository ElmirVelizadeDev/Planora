import { ref } from 'vue';

export function useWorkspaceSidebar() {
    const sidebarCollapsed = ref(false);
    const mobileSidebarOpen = ref(false);

    function toggleSidebar() {
        if (window.matchMedia('(max-width: 820px)').matches) {
            mobileSidebarOpen.value = !mobileSidebarOpen.value;
            return;
        }

        sidebarCollapsed.value = !sidebarCollapsed.value;
    }

    return { sidebarCollapsed, mobileSidebarOpen, toggleSidebar };
}
