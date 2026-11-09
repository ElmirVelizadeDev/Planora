<template>
    <div class="admin-task-form-fields">
        <label class="is-wide">Title <span>*</span><input :value="title" maxlength="180" required :aria-invalid="Boolean(errors.title)" @input="$emit('update:title', $event.target.value.trim()); $emit('clear-error', 'title')"><small v-if="errors.title">{{ errors.title }}</small></label>
        <label class="is-wide">Description<textarea :value="description" rows="6" maxlength="10000" :aria-invalid="Boolean(errors.description)" @input="$emit('update:description', $event.target.value); $emit('clear-error', 'description')"></textarea><small v-if="errors.description">{{ errors.description }}</small></label>
        <label>Status<select :value="status" @change="$emit('update:status', $event.target.value)"><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select><small v-if="errors.status">{{ errors.status }}</small></label>
        <label>Priority<select :value="priority" @change="$emit('update:priority', $event.target.value)"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select><small v-if="errors.priority">{{ errors.priority }}</small></label>
        <label>Due date<input :value="dueDate" type="date" :aria-invalid="Boolean(errors.due_date)" @input="$emit('update:dueDate', $event.target.value); $emit('clear-error', 'due_date')"><small v-if="errors.due_date">{{ errors.due_date }}</small></label>
        <slot />
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    status: { type: String, default: 'pending' },
    priority: { type: String, default: 'medium' },
    dueDate: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
});
defineEmits(['update:title', 'update:description', 'update:status', 'update:priority', 'update:dueDate', 'clear-error']);
</script>
