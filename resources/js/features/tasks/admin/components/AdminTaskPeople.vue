<template>
    <section class="admin-task-panel" aria-labelledby="assigned-user-title">
        <div class="admin-task-section-heading"><div><h2 id="assigned-user-title">Assigned user</h2><p>Task assignee</p></div></div>
        <div v-if="user" class="admin-task-user">
            <UserAvatar class="admin-task-avatar user-avatar--plain" :name="user.name" :image-src="user.avatar || ''" />
            <div><strong class="admin-task-user-name">{{ user.name }}</strong><span>{{ user.email }}</span></div>
        </div>
        <p v-else class="admin-task-unassigned">No user is assigned to this task.</p>
    </section>
</template>

<script setup>
import UserAvatar from '../../../../shared/components/UserAvatar.vue';
defineProps({ user: { type: Object, default: null } });
</script>
