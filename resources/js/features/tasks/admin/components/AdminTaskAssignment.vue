<template>
    <label>Owner <span>*</span><select :value="ownerId" required :aria-invalid="Boolean(errors.owner_id)" @change="$emit('update:ownerId', $event.target.value)"><option value="" disabled>Select task owner</option><option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }} · {{ user.email }}</option></select><small v-if="errors.owner_id">{{ errors.owner_id }}</small></label>
    <label>Assigned user<select :value="assignedUserId" :aria-invalid="Boolean(errors.assigned_user_id)" @change="$emit('update:assignedUserId', $event.target.value)"><option value="">Unassigned</option><option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }} · {{ user.email }}</option></select><small v-if="errors.assigned_user_id">{{ errors.assigned_user_id }}</small></label>
</template>

<script setup>
defineProps({
    ownerId: { type: [String, Number], default: '' },
    assignedUserId: { type: [String, Number], default: '' },
    users: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});
defineEmits(['update:ownerId', 'update:assignedUserId']);
</script>
