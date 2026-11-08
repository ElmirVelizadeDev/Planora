<template>
    <section class="home-cta section-shell" aria-labelledby="home-cta-title">
        <div><span class="home-eyebrow">Your next step</span><h2 id="home-cta-title">Bring a little more focus to your day.</h2><p>Start with a clear place to plan your work.</p></div>
        <RouterLink class="home-button home-button-primary" :to="{ name: 'login' }">Get started <span aria-hidden="true">→</span></RouterLink>
    </section>
</template>
