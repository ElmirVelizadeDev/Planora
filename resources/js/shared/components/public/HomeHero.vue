<template>
    <section class="home-hero" aria-labelledby="home-title">
        <div class="home-hero-copy">
            <span class="home-eyebrow"><i aria-hidden="true"></i> A clearer way to work</span>
            <h1 id="home-title">Make space for <span>meaningful work.</span></h1>
            <p>Planora brings your tasks, priorities, and progress into one calm workspace—so you can stay organized and move forward with focus.</p>
            <div class="home-hero-actions">
                <RouterLink class="home-button home-button-primary" :to="{ name: 'login' }">Get started <span aria-hidden="true">→</span></RouterLink>
                <RouterLink class="home-button home-button-secondary" :to="{ name: 'login' }">Log in</RouterLink>
            </div>
            <div class="home-hero-note"><span class="home-note-mark" aria-hidden="true">✓</span> A thoughtful place for the work ahead</div>
        </div>
        <div class="home-preview-wrap" aria-label="Illustration of an organized task list">
            <div class="home-preview-card">
                <div class="home-preview-top"><span class="home-preview-icon" aria-hidden="true">P</span><div><strong>My tasks</strong><small>Keep today in view</small></div><button type="button" tabindex="-1" aria-hidden="true">•••</button></div>
                <div class="home-preview-date">TODAY <span>·</span> 3 TASKS</div>
                <div class="home-preview-task is-done"><span class="home-task-check" aria-hidden="true">✓</span><span><strong>Review project brief</strong><small>Planning</small></span><em>Done</em></div>
                <div class="home-preview-task"><span class="home-task-check" aria-hidden="true"></span><span><strong>Outline next steps</strong><small>Work</small></span><em class="is-today">Today</em></div>
                <div class="home-preview-task"><span class="home-task-check" aria-hidden="true"></span><span><strong>Prepare weekly notes</strong><small>Personal</small></span><em>Later</em></div>
                <div class="home-preview-bottom"><span class="home-preview-progress"><i></i></span><span>Progress at a glance</span><strong>1 / 3</strong></div>
            </div>
            <div class="home-preview-caption"><span aria-hidden="true">✦</span> One clear view of what matters</div>
        </div>
    </section>
</template>
