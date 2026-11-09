<template>
    <UserTaskForm
        mode="edit"
        :task-id="route.params.taskId"
        page-title="Edit task"
        page-description="Update the details and priority for this task."
        @saved="handleSaved"
    />
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router';
import UserTaskForm from '../../../features/tasks/user/components/UserTaskForm.vue';

const route = useRoute();
const router = useRouter();

function handleSaved(task) {
    router.push({ name: 'tasks.show', params: { taskId: task.id }, query: { notice: 'updated' } });
}
</script>
