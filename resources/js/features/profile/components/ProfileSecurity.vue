<template>
    <section class="profile-card" aria-labelledby="profile-security-title">
        <div class="profile-card__heading">
            <div><p class="profile-eyebrow">Account security</p><h2 id="profile-security-title">Change password</h2></div>
            <span class="profile-security-icon" aria-hidden="true">⌑</span>
        </div>
        <p class="profile-section-copy">Use your current password to choose a new one. Other signed-in sessions will be invalidated.</p>
        <form class="profile-form profile-security-form" @submit.prevent="save">
            <label v-for="field in fields" :key="field.key" class="profile-field">
                <span>{{ field.label }}</span>
                <span class="profile-password-control">
                    <input
                        v-model="form[field.key]"
                        :type="visible[field.key] ? 'text' : 'password'"
                        :autocomplete="field.autocomplete"
                        :maxlength="field.key === 'current_password' ? 128 : 128"
                        required
                        :aria-invalid="Boolean(errors[field.key])"
                        @input="emit('clear-error', field.key)"
                    >
                    <button type="button" class="profile-password-toggle" :aria-label="`${visible[field.key] ? 'Hide' : 'Show'} ${field.label.toLowerCase()}`" @click="visible[field.key] = !visible[field.key]">
                        {{ visible[field.key] ? 'Hide' : 'Show' }}
                    </button>
                </span>
                <small v-if="errors[field.key]" class="profile-field-error">{{ errors[field.key] }}</small>
            </label>
            <div class="profile-password-guidance" aria-live="polite">
                <span :class="{ 'is-valid': form.password.length >= 12 }"><i aria-hidden="true">{{ form.password.length >= 12 ? '✓' : '•' }}</i>At least 12 characters</span>
                <span :class="{ 'is-valid': form.password.length > 0 && form.password === form.password_confirmation }"><i aria-hidden="true">{{ form.password.length > 0 && form.password === form.password_confirmation ? '✓' : '•' }}</i>Passwords match</span>
                <small>Common or compromised passwords are not accepted. Configured security requirements also apply.</small>
            </div>
            <div class="profile-form__footer">
                <span class="profile-form__note">Your password is never included in account responses.</span>
                <button class="profile-button profile-button--primary" type="submit" :disabled="busy || !canSubmit">
                    {{ busy ? 'Updating…' : 'Update password' }}
                </button>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

const props = defineProps({ errors: { type: Object, default: () => ({}) }, busy: Boolean, resetKey: { type: Number, default: 0 } });
const emit = defineEmits(['save', 'clear-error']);
const fields = [
    { key: 'current_password', label: 'Current password', autocomplete: 'current-password' },
    { key: 'password', label: 'New password', autocomplete: 'new-password' },
    { key: 'password_confirmation', label: 'Confirm new password', autocomplete: 'new-password' },
];
const form = reactive({ current_password: '', password: '', password_confirmation: '' });
const visible = reactive({ current_password: false, password: false, password_confirmation: false });
const canSubmit = computed(() => form.current_password && form.password.length >= 12 && form.password.length <= 128 && form.password === form.password_confirmation);

watch(() => props.resetKey, () => {
    Object.assign(form, { current_password: '', password: '', password_confirmation: '' });
    Object.keys(visible).forEach((key) => { visible[key] = false; });
});
function save() { emit('save', { ...form }); }
</script>
