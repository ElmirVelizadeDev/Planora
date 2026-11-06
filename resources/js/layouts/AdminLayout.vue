<template>
    <div class="workspace-layout admin-layout" :class="{ 'is-collapsed': sidebarCollapsed }">
        <AdminSidebar
            :collapsed="sidebarCollapsed"
            :mobile-open="mobileSidebarOpen"
            @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
            @close="mobileSidebarOpen = false"
        />
        <div class="workspace-main">
            <AdminHeader @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen" />
            <div class="workspace-content">
                <RouterView />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import AdminHeader from '../shared/components/AdminHeader.vue';
import AdminSidebar from '../shared/components/AdminSidebar.vue';

const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
</script>

<style src="../../css/layouts/AdminLayout.css" scoped></style>
