<template>
    <section class="profile-card" aria-labelledby="profile-personal-title">
        <div class="profile-card__heading">
            <div><p class="profile-eyebrow">Personal information</p><h2 id="profile-personal-title">Your details</h2></div>
            <span class="profile-card__hint">Only your account is updated</span>
        </div>
        <form class="profile-form" @submit.prevent="save">
            <div class="profile-form__grid">
                <label class="profile-field">
                    <span>First name <b aria-hidden="true">*</b></span>
                    <input v-model.trim="form.first_name" autocomplete="given-name" maxlength="50" required :aria-invalid="Boolean(errors.first_name)" @input="clearError('first_name')">
                    <small v-if="errors.first_name" class="profile-field-error">{{ errors.first_name }}</small>
                </label>
                <label class="profile-field">
                    <span>Last name</span>
                    <input v-model.trim="form.last_name" autocomplete="family-name" maxlength="50" :aria-invalid="Boolean(errors.last_name)" @input="clearError('last_name')">
                    <small v-if="errors.last_name" class="profile-field-error">{{ errors.last_name }}</small>
                </label>
                <label class="profile-field profile-field--wide">
                    <span>Email address</span>
                    <input :value="user.email" type="email" autocomplete="email" readonly>
                    <small>Email changes require a verification flow that is not enabled for this account.</small>
                </label>
                <label class="profile-field profile-field--wide">
                    <span>Phone number <small>Optional</small></span>
                    <input v-model.trim="form.phone" type="tel" autocomplete="tel" maxlength="32" placeholder="+1 555 010 2040" :aria-invalid="Boolean(errors.phone)" @input="clearError('phone')">
                    <small v-if="errors.phone" class="profile-field-error">{{ errors.phone }}</small>
                </label>
            </div>
            <div class="profile-form__footer">
                <span class="profile-form__note">Your full name appears across Planora.</span>
                <button class="profile-button profile-button--primary" type="submit" :disabled="busy || !dirty">
                    {{ busy ? 'Saving…' : 'Save changes' }}
                </button>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['save', 'clear-error']);
const form = reactive({ first_name: '', last_name: '', phone: '' });
const initial = reactive({ first_name: '', last_name: '', phone: '' });
const dirty = computed(() => Object.keys(form).some((key) => form[key] !== initial[key]));

watch(() => props.user, (user) => {
    const names = nameParts(user);
    Object.assign(initial, { ...names, phone: user.phone || '' });
    Object.assign(form, initial);
}, { immediate: true });

function nameParts(user) {
    if (user.first_name || user.last_name) {
        return { first_name: user.first_name || '', last_name: user.last_name || '' };
    }
    const parts = String(user.name || '').trim().split(/\s+/).filter(Boolean);
    return { first_name: parts.shift() || '', last_name: parts.join(' ') };
}
function clearError(field) { emit('clear-error', field); }
function save() {
    emit('save', { first_name: form.first_name, last_name: form.last_name, phone: form.phone || null });
}
</script>
