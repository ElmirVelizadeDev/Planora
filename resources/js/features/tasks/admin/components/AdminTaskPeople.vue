<template>
    <section class="admin-task-panel" aria-labelledby="assigned-user-title">
        <div class="admin-task-section-heading"><div><h2 id="assigned-user-title">Assigned user</h2><p>Task assignee</p></div></div>
        <div v-if="user" class="admin-task-user">
            <img v-if="user.avatar" :src="user.avatar" alt="" class="admin-task-avatar">
            <span v-else class="admin-task-avatar" aria-hidden="true">{{ initials(user.name) }}</span>
            <div><strong class="admin-task-user-name">{{ user.name }}</strong><span>{{ user.email }}</span></div>
        </div>
        <p v-else class="admin-task-unassigned">No user is assigned to this task.</p>
    </section>
</template>

<script setup>
defineProps({ user: { type: Object, default: null } });

function initials(name) { return String(name || 'User').trim().split(/\s+/).slice(0, 2).map(part => part[0]?.toUpperCase()).join('') || 'U'; }
</script>
