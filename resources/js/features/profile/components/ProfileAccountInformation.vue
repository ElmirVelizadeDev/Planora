<template>
    <section class="profile-card" aria-labelledby="profile-account-title">
        <div class="profile-card__heading">
            <div><p class="profile-eyebrow">Account information</p><h2 id="profile-account-title">Account details</h2></div>
            <span class="profile-account-icon" aria-hidden="true">◎</span>
        </div>
        <dl class="profile-account-list">
            <div><dt>Account ID</dt><dd>#{{ user.id }}</dd></div>
            <div><dt>Email verification</dt><dd><span class="profile-verification" :class="{ 'is-verified': user.email_verified_at }"><i aria-hidden="true" />{{ user.email_verified_at ? 'Verified' : 'Not verified' }}</span></dd></div>
            <div><dt>Role</dt><dd>{{ roleLabel(user.role) }}</dd></div>
            <div><dt>Account status</dt><dd><span class="profile-status-badge" :class="`is-${user.status}`"><i aria-hidden="true" />{{ label(user.status) }}</span></dd></div>
            <div v-if="user.phone"><dt>Phone number</dt><dd>{{ user.phone }}</dd></div>
            <div><dt>Registered</dt><dd>{{ date(user.created_at) }}</dd></div>
            <div><dt>Last sign-in</dt><dd>{{ date(user.last_login_at) }}</dd></div>
            <div><dt>Last updated</dt><dd>{{ date(user.updated_at) }}</dd></div>
        </dl>
        <p v-if="user.role === 'moderator' && user.moderator_permissions?.length" class="profile-permission-note">
            {{ user.moderator_permissions.length }} management {{ user.moderator_permissions.length === 1 ? 'permission is' : 'permissions are' }} assigned to this account.
        </p>
    </section>
</template>

<script setup>
defineProps({ user: { type: Object, required: true } });

function roleLabel(value) { return ({ user: 'User', moderator: 'Moderator', admin: 'Administrator' })[value] || 'Account'; }
function label(value) { return String(value || 'unknown').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function date(value) {
    if (!value) return '—';
    const dateValue = new Date(value);
    return Number.isNaN(dateValue.valueOf()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(dateValue);
}
</script>
