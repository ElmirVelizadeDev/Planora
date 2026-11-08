<template>
    <main class="auth-page page-forgot-password">
        <section class="auth-card auth-unavailable-card" aria-labelledby="forgot-password-title">
            <div class="auth-status-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
            </div>
            <p class="auth-eyebrow">Account recovery</p>
            <h1 id="forgot-password-title">Need help signing in?</h1>
            <p class="auth-description">Password recovery is not available for this workspace right now. Your account is safe; contact your workspace administrator for help getting back in.</p>
            <RouterLink class="auth-submit auth-submit-link" :to="{ name: 'login' }"><span>Back to sign in</span><span aria-hidden="true">→</span></RouterLink>
        </section>
    </main>
</template>

<style src="../../../css/pages/auth/forgot-password.css"></style>
