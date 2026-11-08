<template>
    <main class="auth-page page-reset-password">
        <section class="auth-card auth-unavailable-card" aria-labelledby="reset-password-title">
            <div class="auth-status-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <p class="auth-eyebrow">Password reset</p>
            <h1 id="reset-password-title">Can’t use this reset link?</h1>
            <p class="auth-description">Password recovery is not available for this workspace right now. Contact your workspace administrator for help accessing your account.</p>
            <RouterLink class="auth-submit auth-submit-link" :to="{ name: 'login' }"><span>Back to sign in</span><span aria-hidden="true">→</span></RouterLink>
        </section>
    </main>
</template>

<style src="../../../css/pages/auth/reset-password.css"></style>
