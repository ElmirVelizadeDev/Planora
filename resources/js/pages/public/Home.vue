<template>
    <div class="public-home">
        <AuthenticatedHome v-if="auth.isAuthenticated" />
        <template v-else>
            <PublicNavbar />
            <main>
                <HomeHero />
                <FeaturesSection />
                <HowItWorksSection />
                <HomeCallToAction />
            </main>
            <PublicFooter />
        </template>
    </div>
</template>

<script setup>
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import AuthenticatedHome from '../../shared/components/public/AuthenticatedHome.vue';
import PublicNavbar from '../../shared/components/public/PublicNavbar.vue';
import HomeHero from '../../shared/components/public/HomeHero.vue';
import FeaturesSection from '../../shared/components/public/FeaturesSection.vue';
import HowItWorksSection from '../../shared/components/public/HowItWorksSection.vue';
import HomeCallToAction from '../../shared/components/public/HomeCallToAction.vue';
import PublicFooter from '../../shared/components/public/PublicFooter.vue';

const auth = useAuthStore();
</script>

<style src="../../../css/pages/public/home.css"></style>
