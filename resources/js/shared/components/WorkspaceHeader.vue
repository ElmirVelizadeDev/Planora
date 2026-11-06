<template>
    <header class="workspace-header" :class="`is-${variant}`">
        <div class="workspace-header-start">
            <button class="workspace-menu-button" type="button" aria-label="Open navigation" @click="$emit('toggle-sidebar')">
                <span></span><span></span><span></span>
            </button>
            <div class="workspace-header-context">
                <span class="workspace-header-eyebrow"><img :src="'/images/branding/planora-master-512.png'" alt="Planora" width="16" height="16"> Planora</span>
                <span class="workspace-header-label">{{ contextLabel }}</span>
            </div>
        </div>

        <div class="workspace-header-actions">
            <SearchBar />
            <NotificationDropdown />
            <ProfileDropdown :links="profileLinks" :fallback-name="profileName" />
        </div>
    </header>
</template>

<script setup>
import NotificationDropdown from './NotificationDropdown.vue';
import ProfileDropdown from './ProfileDropdown.vue';
import SearchBar from './SearchBar.vue';

defineProps({
    variant: { type: String, required: true },
    contextLabel: { type: String, required: true },
    profileName: { type: String, required: true },
    profileLinks: { type: Array, required: true },
});

defineEmits(['toggle-sidebar']);
</script>

<style src="../../../css/components/WorkspaceHeader.css"></style>
