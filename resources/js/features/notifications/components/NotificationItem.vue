<template>
    <button class="notification-item" :class="{ 'is-unread': !notification.read_at }" type="button" :disabled="busy" @click="$emit('activate', notification)">
        <span class="notification-item-icon" aria-hidden="true">
            <svg v-if="notification.type.startsWith('task.')" viewBox="0 0 20 20"><rect x="3.5" y="3.5" width="13" height="13" rx="2.5"/><path d="M7 7.5h6M7 10.5h6M7 13.5h3"/></svg>
            <svg v-else-if="notification.type === 'admin.user_registered'" viewBox="0 0 20 20"><circle cx="7.5" cy="6" r="3"/><path d="M2.8 16c.4-2.7 2-4.2 4.7-4.2 1.1 0 2 .2 2.7.7M15 9v6m-3-3h6"/></svg>
            <svg v-else viewBox="0 0 20 20"><path d="M12.9 3.2a4.1 4.1 0 0 0-5.3 5.3l-4.4 4.4a1.6 1.6 0 0 0 2.3 2.3l4.4-4.4a4.1 4.1 0 0 0 5.3-5.3l-2.3 2.3-2.1-2.1 2.3-2.5Z"/><circle cx="5.1" cy="14.9" r=".5"/></svg>
        </span>
        <span class="notification-item-copy">
            <strong>{{ notification.title }}</strong>
            <span>{{ notification.description }}</span>
            <time v-if="notification.created_at" :datetime="notification.created_at">{{ relativeTime(notification.created_at) }}</time>
        </span>
        <span v-if="!notification.read_at" class="notification-unread-dot" aria-label="Unread"></span>
    </button>
</template>

<script setup>
defineProps({
    notification: { type: Object, required: true },
    busy: { type: Boolean, default: false },
});
defineEmits(['activate']);

const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
function relativeTime(value) {
    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const minutes = Math.round(seconds / 60);
    const hours = Math.round(minutes / 60);
    const days = Math.round(hours / 24);
    if (Math.abs(seconds) < 60) return formatter.format(seconds, 'second');
    if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute');
    if (Math.abs(hours) < 24) return formatter.format(hours, 'hour');
    return formatter.format(days, 'day');
}
</script>
