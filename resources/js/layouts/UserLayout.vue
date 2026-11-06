<template>
    <div class="workspace-layout user-layout" :class="{ 'is-collapsed': sidebarCollapsed }">
        <UserSidebar
            :collapsed="sidebarCollapsed"
            :mobile-open="mobileSidebarOpen"
            @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
            @close="mobileSidebarOpen = false"
        />
        <div class="workspace-main">
            <UserHeader @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen" />
            <div class="workspace-content">
                <RouterView />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import UserHeader from '../shared/components/UserHeader.vue';
import UserSidebar from '../shared/components/UserSidebar.vue';

const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
</script>

<style src="../../css/layouts/UserLayout.css" scoped></style>
