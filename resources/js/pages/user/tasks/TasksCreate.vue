<template>
    <UserTaskForm
        mode="create"
        page-title="Create task"
        page-description="Capture the work you want to keep track of."
        @saved="handleSaved"
    />
</template>

<script setup>
import { useRouter } from 'vue-router';
import UserTaskForm from '../../../features/tasks/user/components/UserTaskForm.vue';

const router = useRouter();

function handleSaved(task) {
    router.push({ name: 'tasks.show', params: { taskId: task.id }, query: { notice: 'created' } });
}
</script>
