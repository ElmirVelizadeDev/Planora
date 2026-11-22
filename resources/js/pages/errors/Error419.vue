<template>
    <AppErrorPage
        :code="419"
        eyebrow="Secure session expired"
        title="This page needs a fresh session"
        description="For your security, this request was not repeated. Reload the page to start again; any unsaved form entries may need to be restored."
        icon="clock"
        tone="warning"
        primary-label="Reload page"
        :primary-action="reloadPage"
        secondary-label="Sign in"
        :secondary-to="{ name: 'login' }"
    />
</template>

<script setup>
import AppErrorPage from './AppErrorPage.vue';

function reloadPage() {
    window.location.reload();
}
</script>
