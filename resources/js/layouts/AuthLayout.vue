<template>
    <div class="layout-auth">
        <RouterLink class="auth-branding" :to="{ name: 'login' }" aria-label="Planora home">
            <img :src="'/images/branding/planora-master-512.png'" alt="Planora" width="40" height="40">
            <span>Planora</span>
        </RouterLink>
        <RouterView />
    </div>
</template>
<style src="../../css/layouts/auth-layout.css"></style>
