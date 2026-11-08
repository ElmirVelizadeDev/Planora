<template>
    <div class="layout-auth">
        <header class="auth-topbar">
            <RouterLink class="auth-branding" :to="{ name: 'login' }" aria-label="Planora — sign in">
                <img :src="'/images/branding/planora-master-512.png'" alt="" width="40" height="40">
                <span>Planora</span>
            </RouterLink>
            <span class="auth-topbar-note"><span aria-hidden="true"></span> Workspace access</span>
        </header>

        <div class="auth-layout-grid">
            <aside class="auth-story" aria-label="About Planora">
                <div class="auth-story-copy">
                    <span class="auth-story-kicker">PLANORA WORKSPACE</span>
                    <h2>Bring your work into focus.</h2>
                    <p>Plan your day, keep projects moving, and make room for the work that matters.</p>
                </div>
                <div class="auth-product-preview" aria-hidden="true">
                    <div class="auth-preview-header"><span class="auth-preview-mark">P</span><span>My workspace</span><span class="auth-preview-dots">•••</span></div>
                    <div class="auth-preview-greeting"><span>MONDAY, 9:41 AM</span><strong>A little progress goes a long way.</strong></div>
                    <div class="auth-preview-task"><span class="auth-preview-check is-done">✓</span><span>Review project priorities</span><span class="auth-preview-tag">Done</span></div>
                    <div class="auth-preview-task"><span class="auth-preview-check"></span><span>Prepare weekly plan</span><span class="auth-preview-tag is-current">Today</span></div>
                    <div class="auth-preview-task"><span class="auth-preview-check"></span><span>Share updates with the team</span><span class="auth-preview-tag">Later</span></div>
                    <div class="auth-preview-progress"><span><i></i></span><small>2 of 3 tasks complete</small></div>
                </div>
                <div class="auth-story-orb auth-story-orb-one"></div>
                <div class="auth-story-orb auth-story-orb-two"></div>
            </aside>
            <div class="auth-content"><RouterView /></div>
        </div>

        <footer class="auth-footer"><span>© Planora</span><span>Thoughtful tools for focused work</span></footer>
    </div>
</template>
<style src="../../css/layouts/auth-layout.css"></style>
