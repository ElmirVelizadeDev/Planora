<template>
    <div class="user-task-list" :class="{ 'is-loading': loading }">
        <TaskCard
            v-for="task in tasks"
            :key="task.id"
            :task="task"
            :pending="Boolean(pendingStatuses[task.id])"
            @status-change="$emit('status-change', task, $event)"
            @delete="$emit('delete', $event)"
        />
    </div>
</template>

<script setup>
import TaskCard from './TaskCard.vue';

defineProps({
    tasks: { type: Array, required: true },
    pendingStatuses: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
});
defineEmits(['status-change', 'delete']);
</script>
