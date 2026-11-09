<template>
    <main class="admin-user-create" :aria-busy="submitting">
        <header class="admin-user-create-header">
            <div>
                <p class="admin-user-create-eyebrow">User management</p>
                <h1>Create New User</h1>
                <p class="admin-user-create-description">Add an account and configure its access to Planora.</p>
            </div>
            <RouterLink class="admin-user-create-back" :to="{ name: 'admin.users.index' }"><span aria-hidden="true">←</span> Back to Users</RouterLink>
        </header>

        <div v-if="formError" class="admin-user-create-alert" role="alert">
            <span>{{ formError }}</span>
            <button v-if="!submitting && !roleOptions.length && !loadingAccessOptions" class="is-retry" type="button" @click="loadAccessOptions">Retry</button>
            <button v-else-if="!submitting" type="button" aria-label="Dismiss error" @click="formError = ''">×</button>
        </div>

        <form class="admin-user-create-form" novalidate @submit.prevent="submitForm">
            <section class="admin-user-create-card" aria-labelledby="personal-info-title">
                <div class="admin-user-create-section-heading">
                    <span class="admin-user-create-section-icon" aria-hidden="true">01</span>
                    <div><h2 id="personal-info-title">Personal Information</h2><p>Enter the name and contact details for this account.</p></div>
                </div>

                <div class="admin-user-create-fields">
                    <div class="admin-user-create-field">
                        <label for="first-name">First name <span aria-hidden="true">*</span></label>
                        <input id="first-name" v-model="form.first_name" type="text" autocomplete="given-name" minlength="2" maxlength="50" placeholder="e.g. Alex" :aria-invalid="Boolean(errors.first_name)" :aria-describedby="errors.first_name ? 'first-name-error' : undefined" @input="form.first_name = normalizePersonName(form.first_name); clearError('first_name')">
                        <small v-if="errors.first_name" id="first-name-error" class="admin-user-create-field-error">{{ errors.first_name }}</small>
                    </div>
                    <div class="admin-user-create-field">
                        <label for="last-name">Last name <span aria-hidden="true">*</span></label>
                        <input id="last-name" v-model="form.last_name" type="text" autocomplete="family-name" minlength="2" maxlength="50" placeholder="e.g. Morgan" :aria-invalid="Boolean(errors.last_name || errors.name)" :aria-describedby="errors.last_name ? 'last-name-error' : errors.name ? 'name-error' : undefined" @input="form.last_name = normalizePersonName(form.last_name); clearError('last_name'); clearError('name')">
                        <small v-if="errors.last_name" id="last-name-error" class="admin-user-create-field-error">{{ errors.last_name }}</small>
                        <small v-else-if="errors.name" id="name-error" class="admin-user-create-field-error">{{ errors.name }}</small>
                    </div>
                    <div class="admin-user-create-field admin-user-create-field-wide">
                        <label for="email-address">Email address <span aria-hidden="true">*</span></label>
                        <input id="email-address" v-model="form.email" type="email" autocomplete="email" maxlength="255" placeholder="name@example.com" :aria-invalid="Boolean(errors.email)" :aria-describedby="errors.email ? 'email-error' : undefined" @input="clearError('email')" @blur="form.email = form.email.trim().toLowerCase()">
                        <small v-if="errors.email" id="email-error" class="admin-user-create-field-error">{{ errors.email }}</small>
                    </div>
                    <div class="admin-user-create-field">
                        <label for="username">Username</label>
                        <input id="username" v-model.trim="form.username" type="text" autocomplete="username" maxlength="255" placeholder="Optional username" :aria-invalid="Boolean(errors.username)" @input="clearError('username')">
                        <small v-if="errors.username" class="admin-user-create-field-error">{{ errors.username }}</small>
                    </div>
                    <div class="admin-user-create-field">
                        <label for="phone-number">Phone number</label>
                        <input id="phone-number" v-model.trim="form.phone" type="tel" autocomplete="tel" maxlength="32" placeholder="+1 555 0100" :aria-invalid="Boolean(errors.phone)" @input="clearError('phone')">
                        <small v-if="errors.phone" class="admin-user-create-field-error">{{ errors.phone }}</small>
                    </div>
                    <div class="admin-user-create-field admin-user-create-field-wide">
                        <label>Profile avatar</label>
                        <div class="admin-user-create-avatar admin-avatar-upload">
                            <span class="admin-avatar-upload-preview">
                                <img v-if="avatarPreview" :src="avatarPreview" alt="Selected profile avatar preview">
                                <span v-else aria-label="Default avatar initials">{{ avatarInitials }}</span>
                            </span>
                            <div class="admin-user-create-avatar-controls admin-avatar-upload-controls">
                                <input id="create-user-avatar" ref="avatarInput" class="admin-avatar-upload-input" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Choose profile avatar" :aria-invalid="Boolean(errors.avatar)" @change="onAvatarChange">
                                <div class="admin-avatar-upload-actions">
                                    <label class="admin-avatar-upload-button" for="create-user-avatar">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5h14v-5"/></svg>
                                        {{ form.avatar ? 'Change image' : 'Choose image' }}
                                    </label>
                                    <span class="admin-avatar-upload-filename" :title="form.avatar?.name || 'No file selected'">{{ form.avatar?.name || 'No file selected' }}</span>
                                </div>
                                <button v-if="form.avatar" type="button" class="admin-user-create-cancel" @click="clearAvatar">Remove avatar</button>
                                <small class="admin-user-create-hint">JPG, PNG or WebP. Maximum file size 2 MB.</small>
                                <small v-if="errors.avatar" class="admin-user-create-field-error">{{ errors.avatar }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="admin-user-create-card" aria-labelledby="account-info-title">
                <div class="admin-user-create-section-heading">
                    <span class="admin-user-create-section-icon is-account" aria-hidden="true">02</span>
                    <div><h2 id="account-info-title">Account Information</h2><p>Set sign-in credentials, role and initial account status.</p></div>
                </div>

                <div class="admin-user-create-fields">
                    <div class="admin-user-create-field">
                        <label for="password">Password <span aria-hidden="true">*</span></label>
                        <div class="admin-user-create-password-wrap">
                            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" minlength="12" maxlength="128" placeholder="Create a strong password" :aria-invalid="Boolean(errors.password)" :aria-describedby="errors.password ? 'password-error password-hint' : 'password-hint'" @input="clearError('password')">
                            <button class="admin-user-create-password-toggle" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button>
                        </div>
                        <small id="password-hint" class="admin-user-create-hint">At least 12 characters, with uppercase, lowercase and a number.</small>
                        <small v-if="errors.password" id="password-error" class="admin-user-create-field-error">{{ errors.password }}</small>
                    </div>
                    <div class="admin-user-create-field">
                        <label for="confirm-password">Confirm password <span aria-hidden="true">*</span></label>
                        <div class="admin-user-create-password-wrap">
                            <input id="confirm-password" v-model="form.password_confirmation" :type="showConfirmation ? 'text' : 'password'" autocomplete="new-password" minlength="12" maxlength="128" placeholder="Enter the password again" :aria-invalid="Boolean(errors.password_confirmation)" :aria-describedby="errors.password_confirmation ? 'confirm-password-error' : undefined" @input="clearError('password_confirmation')">
                            <button class="admin-user-create-password-toggle" type="button" :aria-label="showConfirmation ? 'Hide confirmation password' : 'Show confirmation password'" :aria-pressed="showConfirmation" @click="showConfirmation = !showConfirmation">{{ showConfirmation ? 'Hide' : 'Show' }}</button>
                        </div>
                        <small v-if="errors.password_confirmation" id="confirm-password-error" class="admin-user-create-field-error">{{ errors.password_confirmation }}</small>
                    </div>
                    <div class="admin-user-create-field">
                        <label for="role">Role <span aria-hidden="true">*</span></label>
                        <select id="role" v-model="form.role" :disabled="loadingAccessOptions || !roleOptions.length" :aria-invalid="Boolean(errors.role)" :aria-describedby="errors.role ? 'role-error' : undefined" @change="onRoleChange">
                            <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
                        </select>
                        <small v-if="errors.role" id="role-error" class="admin-user-create-field-error">{{ errors.role }}</small>
                    </div>
                    <div class="admin-user-create-field">
                        <label for="account-status">Account status <span aria-hidden="true">*</span></label>
                        <select id="account-status" v-model="form.status" :aria-invalid="Boolean(errors.status)" :aria-describedby="errors.status ? 'status-error' : undefined" @change="clearError('status')">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <small v-if="errors.status" id="status-error" class="admin-user-create-field-error">{{ errors.status }}</small>
                    </div>
                    <div v-if="form.role === 'moderator'" class="admin-user-create-field admin-user-create-field-wide">
                        <span class="admin-user-create-field-label">Moderator permissions</span>
                        <div class="admin-user-create-permissions">
                            <label v-for="permission in permissionOptions" :key="permission.key">
                                <input v-model="form.moderator_permissions" type="checkbox" :value="permission.key">
                                <span><strong>{{ permission.key }}</strong><small>{{ permission.description }}</small></span>
                            </label>
                        </div>
                        <small class="admin-user-create-hint">Permissions are limited to the options configured for moderators.</small>
                        <small v-if="errors.moderator_permissions" class="admin-user-create-field-error">{{ errors.moderator_permissions }}</small>
                    </div>
                </div>
            </section>

            <footer class="admin-user-create-form-footer">
                <p><span aria-hidden="true">*</span> Required fields</p>
                <div>
                    <RouterLink class="admin-user-create-cancel" :to="{ name: 'admin.users.index' }">Cancel</RouterLink>
                    <button class="admin-user-create-submit" type="submit" :disabled="submitting || loadingAccessOptions || !roleOptions.length">
                        <span v-if="submitting" class="admin-user-create-spinner" aria-hidden="true"></span>
                        {{ submitting ? 'Creating user…' : 'Create user' }}
                    </button>
                </div>
            </footer>
        </form>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../../shared/services/api.js';
import { normalizePersonName, validatePersonName, validateSupportedEmail } from '../../../shared/validation.js';

const router = useRouter();
const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    username: '',
    phone: '',
    avatar: null,
    password: '',
    password_confirmation: '',
    role: 'user',
    moderator_permissions: [],
    status: 'active',
});
const errors = reactive({});
const submitting = ref(false);
const showPassword = ref(false);
const showConfirmation = ref(false);
const formError = ref('');
const avatarPreview = ref('');
const avatarInput = ref(null);
const avatarInitials = computed(() => `${form.first_name[0] || ''}${form.last_name[0] || ''}`.toUpperCase() || 'U');
const roleOptions = ref([]);
const permissionOptions = ref([]);
const loadingAccessOptions = ref(true);

const roleNames = { user: 'User', moderator: 'Moderator', admin: 'Admin' };

function clearError(field) {
    delete errors[field];
    formError.value = '';
}

function validateForm() {
    Object.keys(errors).forEach((field) => delete errors[field]);
    formError.value = '';

    form.first_name = normalizePersonName(form.first_name);
    form.last_name = normalizePersonName(form.last_name);
    form.email = form.email.trim().toLowerCase();
    if (!form.first_name) errors.first_name = 'First name is required.';
    else if (validatePersonName(form.first_name)) errors.first_name = validatePersonName(form.first_name);
    if (!form.last_name) errors.last_name = 'Last name is required.';
    else if (validatePersonName(form.last_name)) errors.last_name = validatePersonName(form.last_name);
    if (!form.email) errors.email = 'Email address is required.';
    else if (validateSupportedEmail(form.email)) errors.email = validateSupportedEmail(form.email);
    if (form.phone && !/^\+?[0-9().\s-]{7,32}$/.test(form.phone)) errors.phone = 'Enter a valid phone number using digits and standard phone punctuation.';
    if (form.role === 'moderator' && form.moderator_permissions.includes('users.status.manage') && !form.moderator_permissions.includes('users.view')) {
        errors.moderator_permissions = 'User visibility is required for status management.';
    }
    if (!form.password) errors.password = 'Password is required.';
    else if (form.password.length < 12 || form.password.length > 128 || !/[A-Z]/.test(form.password) || !/[a-z]/.test(form.password) || !/\d/.test(form.password)) {
        errors.password = 'Use at least 12 characters, including uppercase, lowercase and a number.';
    }
    if (!form.password_confirmation) errors.password_confirmation = 'Please confirm the password.';
    else if (form.password !== form.password_confirmation) errors.password_confirmation = 'Passwords do not match.';

    if (Array.from(`${form.first_name} ${form.last_name}`.trim()).length > 50) {
        errors.last_name = 'The full name must be 50 characters or fewer.';
    }

    return Object.keys(errors).length === 0;
}

function applyServerErrors(validationErrors = {}) {
    for (const [field, messages] of Object.entries(validationErrors)) {
        const firstMessage = Array.isArray(messages) ? messages[0] : String(messages);
        const target = field === 'name'
            ? 'last_name'
            : field === 'password' && /confirm/i.test(firstMessage)
                ? 'password_confirmation'
                : field;
        if (['email', 'username', 'phone', 'avatar', 'password', 'password_confirmation', 'role', 'status', 'first_name', 'last_name', 'moderator_permissions'].includes(target)) {
            errors[target] = firstMessage;
        }
    }
}

function revokeAvatarPreview() {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
    avatarPreview.value = '';
}

function onAvatarChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    clearError('avatar');
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        errors.avatar = 'Choose a JPG, PNG or WebP image.';
        event.target.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        errors.avatar = 'The avatar must be 2 MB or smaller.';
        event.target.value = '';
        return;
    }
    revokeAvatarPreview();
    form.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
}

function clearAvatar() {
    form.avatar = null;
    revokeAvatarPreview();
    if (avatarInput.value) avatarInput.value.value = '';
    clearError('avatar');
}

function onRoleChange() {
    clearError('role');
    delete errors.moderator_permissions;
    if (form.role === 'moderator') {
        if (!form.moderator_permissions.length && permissionOptions.value.some(({ key }) => key === 'users.view')) {
            form.moderator_permissions = ['users.view'];
        }
    } else {
        form.moderator_permissions = [];
    }
}

async function loadAccessOptions() {
    loadingAccessOptions.value = true;
    try {
        const { data } = await api.get('/admin/users/roles-permissions');
        roleOptions.value = Object.keys(data.roles || {}).map((value) => ({ value, label: roleNames[value] || value }));
        permissionOptions.value = Object.entries(data.permissions || {}).map(([key, description]) => ({ key, description }));
        if (!roleOptions.value.some(({ value }) => value === form.role)) form.role = roleOptions.value[0]?.value || '';
        formError.value = '';
    } catch (requestError) {
        formError.value = requestError.response?.data?.message || 'Role and permission options could not be loaded. Please try again.';
    } finally {
        loadingAccessOptions.value = false;
    }
}

async function submitForm() {
    if (submitting.value || !validateForm()) return;
    submitting.value = true;

    try {
        const payload = new FormData();
        payload.append('name', `${form.first_name} ${form.last_name}`.trim());
        payload.append('first_name', form.first_name);
        payload.append('last_name', form.last_name);
        payload.append('email', form.email);
        if (form.username) payload.append('username', form.username);
        if (form.phone) payload.append('phone', form.phone);
        if (form.avatar) payload.append('avatar', form.avatar);
        payload.append('password', form.password);
        payload.append('password_confirmation', form.password_confirmation);
        payload.append('role', form.role);
        payload.append('status', form.status);
        form.moderator_permissions.forEach((permission) => payload.append('moderator_permissions[]', permission));
        await api.post('/admin/users', payload);
        await router.push({ name: 'admin.users.index' });
    } catch (requestError) {
        if (requestError.response?.status === 422) {
            applyServerErrors(requestError.response.data?.errors);
            formError.value = Object.keys(errors).length
                ? 'Please review the highlighted fields.'
                : requestError.response.data?.message || 'Please review the submitted information.';
        } else if (requestError.response?.status === 403) {
            formError.value = 'You do not have permission to create users.';
        } else {
            formError.value = requestError.response?.data?.message || 'The user could not be created. Your entered information has been kept.';
        }
    } finally {
        submitting.value = false;
    }
}

onMounted(loadAccessOptions);
onBeforeUnmount(revokeAvatarPreview);
</script>

<style src="../../../../css/pages/admin/users/styles/UsersCreate.css" scoped></style>
<style src="../../../../css/pages/admin/users/styles/UserAvatarUpload.css" scoped></style>
