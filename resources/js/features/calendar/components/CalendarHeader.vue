<template>
    <header class="calendar-heading"><div><p class="calendar-eyebrow">YOUR SCHEDULE</p><h1>Calendar</h1><p>Organize your schedule and keep track of upcoming tasks.</p></div><div class="calendar-heading-actions"><span class="calendar-current-date">{{ formatDate(new Date(), { weekday: 'short', month: 'short', day: 'numeric' }) }}</span><button class="calendar-primary" type="button" @click="$emit('add')"><span aria-hidden="true">＋</span> Add task</button></div></header>
</template>
<script setup>
import { formatDate } from '../utils/calendarDateUtils.js';
defineEmits(['add']);
</script>
