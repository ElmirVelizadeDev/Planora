<template>
    <section class="profile-card profile-overview" aria-labelledby="profile-overview-title">
        <div class="profile-overview__identity">
            <div class="profile-overview__avatar">
                <UserAvatar :name="user.name" :image-src="previewUrl || user.avatar || ''" />
            </div>
            <div class="profile-overview__copy">
                <p class="profile-eyebrow">Account overview</p>
                <h2 id="profile-overview-title">{{ user.name || 'Planora account' }}</h2>
                <p>{{ user.email }}</p>
                <div class="profile-overview__badges">
                    <span class="profile-role-badge">{{ roleLabel(user.role) }}</span>
                    <span class="profile-status-badge" :class="`is-${user.status}`"><i aria-hidden="true" />{{ label(user.status) }}</span>
                </div>
                <RouterLink v-if="!editable" class="profile-button profile-button--secondary profile-overview__manage-link" :to="{ name: 'account.settings' }">Manage account</RouterLink>
            </div>
        </div>

        <div v-if="editable" class="profile-overview__photo">
            <input
                ref="fileInput"
                class="profile-visually-hidden"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                aria-label="Choose a profile photo"
                @change="choosePhoto"
            >
            <p class="profile-photo-hint">JPG, PNG or WebP. Maximum 2 MB and 4096 × 4096 pixels.</p>
            <p v-if="photoError" class="profile-field-error" role="alert">{{ photoError }}</p>
            <p v-else-if="serverError" class="profile-field-error" role="alert">{{ serverError }}</p>
            <div class="profile-overview__photo-actions">
                <button class="profile-button profile-button--secondary" type="button" :disabled="busy" @click="fileInput?.click()">
                    {{ selectedFile ? 'Choose another' : 'Change photo' }}
                </button>
                <button v-if="selectedFile" class="profile-button profile-button--primary" type="button" :disabled="busy" @click="emit('upload', selectedFile)">
                    {{ busy ? 'Uploading…' : 'Upload photo' }}
                </button>
                <button v-if="selectedFile" class="profile-button profile-button--quiet" type="button" :disabled="busy" @click="clearSelection">Cancel</button>
                <button v-else-if="user.avatar" class="profile-button profile-button--quiet-danger" type="button" :disabled="busy" @click="removeDialogOpen = true">
                    {{ busy ? 'Removing…' : 'Remove photo' }}
                </button>
            </div>
        </div>
        <AppConfirmDialog v-if="editable"
            :open="removeDialogOpen"
            title="Remove profile photo?"
            message="Your current profile photo will be removed from your account and the stored image will be deleted. You can upload a new photo at any time."
            confirm-label="Remove photo"
            :danger="true"
            @cancel="removeDialogOpen = false"
            @confirm="confirmRemove"
        />
    </section>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AppConfirmDialog from '../../../shared/components/AppConfirmDialog.vue';
import UserAvatar from '../../../shared/components/UserAvatar.vue';

const props = defineProps({ user: { type: Object, required: true }, busy: { type: Boolean, default: false }, serverError: { type: String, default: '' }, editable: { type: Boolean, default: true } });
const emit = defineEmits(['upload', 'remove']);
const fileInput = ref(null);
const selectedFile = ref(null);
const previewUrl = ref('');
const photoError = ref('');
const removeDialogOpen = ref(false);

function clearSelection() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
    selectedFile.value = null;
    photoError.value = '';
    if (fileInput.value) fileInput.value.value = '';
}

function confirmRemove() {
    removeDialogOpen.value = false;
    emit('remove');
}

async function choosePhoto(event) {
    const file = event.target.files?.[0];
    photoError.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        photoError.value = 'Choose a JPG, PNG or WebP image.';
        event.target.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        photoError.value = 'The photo must be 2 MB or smaller.';
        event.target.value = '';
        return;
    }

    const nextPreview = URL.createObjectURL(file);
    const dimensions = await new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve({ width: image.naturalWidth, height: image.naturalHeight });
        image.onerror = () => resolve(null);
        image.src = nextPreview;
    });
    if (!dimensions || dimensions.width > 4096 || dimensions.height > 4096) {
        URL.revokeObjectURL(nextPreview);
        photoError.value = 'Choose a valid image no larger than 4096 × 4096 pixels.';
        event.target.value = '';
        return;
    }

    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    selectedFile.value = file;
    previewUrl.value = nextPreview;
}

watch(() => props.user.avatar, (next, previous) => {
    if (next !== previous && selectedFile.value) clearSelection();
});
onBeforeUnmount(clearSelection);

function roleLabel(value) {
    return ({ user: 'User', moderator: 'Moderator', admin: 'Administrator' })[value] || 'Account';
}
function label(value) {
    return String(value || 'unknown').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
</script>
