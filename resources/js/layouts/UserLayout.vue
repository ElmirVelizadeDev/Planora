<template>
    <div class="workspace-layout user-layout" :class="{ 'is-collapsed': sidebarCollapsed }">
        <UserSidebar
            :collapsed="sidebarCollapsed"
            :mobile-open="mobileSidebarOpen"
            @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
            @close="mobileSidebarOpen = false"
        />
        <div class="workspace-main">
            <AppHeader variant="user" @toggle-sidebar="toggleSidebar" />
            <div class="workspace-content">
                <RouterView />
            </div>
            <WorkspaceFooter />
        </div>
    </div>
</template>

<script setup>
import AppHeader from '../shared/components/layout/AppHeader.vue';
import WorkspaceFooter from '../shared/components/WorkspaceFooter.vue';
import UserSidebar from '../shared/components/UserSidebar.vue';
import { useWorkspaceSidebar } from '../shared/composables/useWorkspaceSidebar.js';

const { sidebarCollapsed, mobileSidebarOpen, toggleSidebar } = useWorkspaceSidebar();
</script>

<style src="../../css/layouts/UserLayout.css" scoped></style>
