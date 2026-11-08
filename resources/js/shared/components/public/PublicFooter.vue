<template>
    <footer class="public-footer">
        <div class="section-shell public-footer-inner">
            <RouterLink class="public-brand" :to="{ name: 'home' }"><img :src="'/images/branding/planora-master-512.png'" width="30" height="30" alt="" /><span>Planora</span></RouterLink>
            <p>Thoughtful tools for focused work.</p>
            <nav aria-label="Footer navigation"><RouterLink :to="{ name: 'home' }">Home</RouterLink><RouterLink :to="{ name: 'login' }">Login</RouterLink><RouterLink :to="{ name: 'register' }">Register</RouterLink></nav>
            <small>© {{ year }} Planora</small>
        </div>
    </footer>
</template>

<script setup>
const year = new Date().getFullYear();
</script>
