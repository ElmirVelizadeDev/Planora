<template>
    <section class="admin-settings-detail__intro">
        <span class="admin-settings-detail__icon" :class="tone" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M7 4v6m10-6v6M4 17h16m-10-3v6m4-6v6" /></svg></span>
        <div><p class="admin-settings-eyebrow">{{ title.toUpperCase() }}</p><h2>{{ title }}</h2><p>{{ description }}</p></div>
        <span class="admin-settings-readonly"><span aria-hidden="true"></span>Saved to workspace</span>
    </section>
</template>

<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, required: true }, tone: { type: String, default: 'is-indigo' } });
</script>
