<template>
    <div class="workspace-layout admin-layout" :class="{ 'is-collapsed': sidebarCollapsed }">
        <AdminSidebar
            :collapsed="sidebarCollapsed"
            :mobile-open="mobileSidebarOpen"
            @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
            @close="mobileSidebarOpen = false"
        />
        <div class="workspace-main">
            <AppHeader variant="admin" @toggle-sidebar="toggleSidebar" />
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
import AdminSidebar from '../shared/components/AdminSidebar.vue';
import { useWorkspaceSidebar } from '../shared/composables/useWorkspaceSidebar.js';

const { sidebarCollapsed, mobileSidebarOpen, toggleSidebar } = useWorkspaceSidebar();
</script>

<style src="../../css/layouts/AdminLayout.css" scoped></style>
