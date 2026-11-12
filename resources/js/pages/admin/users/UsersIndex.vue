<template>
    <main class="admin-users" :aria-busy="loading">
        <header class="admin-users-header">
            <div>
                <p class="admin-users-eyebrow">Administration</p>
                <h1>Users</h1>
                <p class="admin-users-description">Manage accounts, access and activity across Planora.</p>
            </div>
            <RouterLink v-if="isAdmin" class="admin-users-primary-button" :to="{ name: 'admin.users.create' }">
                <span aria-hidden="true">＋</span> Add new user
            </RouterLink>
        </header>

        <section class="admin-users-stat-grid" aria-label="User statistics">
            <article v-for="stat in stats" :key="stat.label" class="admin-users-stat-card">
                <span class="admin-users-stat-icon" :class="`is-${stat.tone}`" aria-hidden="true">
                    <svg v-if="stat.icon === 'users'" viewBox="0 0 24 24"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.7a4 4 0 0 1 0 7.4M17 15h.5a3.5 3.5 0 0 1 3.5 3.5V20"/></svg>
                    <svg v-else-if="stat.icon === 'active'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                    <svg v-else-if="stat.icon === 'inactive'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <svg v-else viewBox="0 0 24 24"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.7a4 4 0 0 1 0 7.4M17 15h.5a3.5 3.5 0 0 1 3.5 3.5V20"/></svg>
                </span>
                <strong>{{ formatNumber(stat.value) }}</strong>
                <span>{{ stat.label }}</span>
            </article>
        </section>

        <section class="admin-users-panel" aria-label="User directory">
            <div class="admin-users-toolbar">
                <div class="admin-users-panel-heading">
                    <h2>All users</h2>
                    <p>Search and manage registered accounts</p>
                </div>
                <div class="admin-users-filters">
                    <label class="admin-users-search">
                        <span class="admin-users-search-icon" aria-hidden="true">⌕</span>
                        <span class="sr-only">Search users</span>
                        <input v-model="searchInput" type="search" placeholder="Search name or email" @input="onSearchInput">
                    </label>
                    <label><span class="sr-only">Filter by role</span><select v-model="filters.role" @change="applyFilters"><option value="">All roles</option><option value="user">User</option><option value="moderator">Moderator</option><option value="admin">Admin</option></select></label>
                    <label><span class="sr-only">Filter by status</span><select v-model="filters.status" @change="applyFilters"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option><option value="banned">Banned</option></select></label>
                    <label class="admin-users-date-filter"><span class="sr-only">Registered from</span><input v-model="filters.registered_from" type="date" aria-label="Registered from" @change="applyFilters"></label>
                    <label class="admin-users-date-filter"><span class="sr-only">Registered to</span><input v-model="filters.registered_to" type="date" aria-label="Registered to" @change="applyFilters"></label>
                    <button class="admin-users-secondary-button admin-users-refresh-button" type="button" :disabled="loading" :aria-busy="loading" @click="refreshUsers">
                        <svg class="admin-users-refresh-icon" :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>
                        {{ loading ? 'Refreshing…' : 'Refresh' }}
                    </button>
                    <button v-if="hasFilters" class="admin-users-clear-button" type="button" @click="clearFilters">Clear filters</button>
                </div>
            </div>

            <div v-if="error && !users.length" class="admin-users-feedback is-error" role="alert">
                <span>{{ error }}</span><button type="button" @click="loadUsers">Try again</button>
            </div>
            <div v-else-if="loading && !users.length" class="admin-users-loading" role="status">
                <span class="admin-users-spinner" aria-hidden="true"></span> Loading users…
            </div>
            <div v-else-if="!users.length" class="admin-users-empty">
                <span class="admin-users-empty-icon" aria-hidden="true">♙</span>
                <h3>{{ hasFilters ? 'No users match these filters' : 'No users yet' }}</h3>
                <p>{{ hasFilters ? 'Try adjusting your search or clearing the filters.' : 'New accounts will appear here once they are added.' }}</p>
                <button v-if="hasFilters" type="button" class="admin-users-secondary-button" @click="clearFilters">Clear filters</button>
            </div>
            <template v-else>
                <div class="admin-users-table-wrap" :class="{ 'is-loading': loading }">
                    <table class="admin-users-table">
                        <thead><tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Registered</th><th scope="col">Last active</th><th scope="col" class="admin-users-actions-heading">Actions</th></tr></thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id">
                                <td data-label="User">
                                    <div class="admin-users-identity">
                                        <img v-if="user.avatar" class="admin-users-avatar" :src="user.avatar" :alt="''" loading="lazy">
                                        <span v-else class="admin-users-avatar" aria-hidden="true">{{ initials(user.name) }}</span>
                                        <span class="admin-users-user-copy"><strong>{{ user.name }}</strong><span>{{ user.email }}</span></span>
                                    </div>
                                </td>
                                <td data-label="Role"><span class="admin-users-role">{{ roleLabel(user.role) }}</span></td>
                                <td data-label="Status"><span class="admin-users-status" :class="`is-${user.status}`"><i></i>{{ statusLabel(user.status) }}</span></td>
                                <td data-label="Registered"><time :datetime="user.created_at">{{ formatDate(user.created_at) }}</time></td>
                                <td data-label="Last active"><time v-if="user.last_login_at" :datetime="user.last_login_at">{{ formatRelativeDate(user.last_login_at) }}</time><span v-else class="admin-users-muted">Never</span></td>
                                <td data-label="Actions">
                                    <div class="admin-users-actions">
                                        <button type="button" :aria-label="`View ${user.name}`" title="View user" @click="openViewDialog(user)">View</button>
                                        <button v-if="isAdmin" type="button" :aria-label="`Edit ${user.name}`" title="Edit user" @click="openEditDialog(user)">Edit</button>
                                        <button v-if="isAdmin || (canManageStatus && user.role === 'user')" type="button" :disabled="Boolean(statusActionDisabledReason(user))" :aria-label="`Change status for ${user.name}`" :title="statusActionDisabledReason(user) || 'Change status'" @click="openStatusDialog(user)">Status</button>
                                        <button v-if="isAdmin" class="is-danger" type="button" :disabled="Boolean(deleteActionDisabledReason(user))" :aria-label="`Delete ${user.name}`" :title="deleteActionDisabledReason(user) || 'Delete user'" @click="openDeleteDialog(user)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer class="admin-users-pagination">
                    <p>Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ formatNumber(total) }}</strong> users</p>
                    <div class="admin-users-page-controls" aria-label="Pagination">
                        <button type="button" :disabled="page <= 1 || loading" aria-label="Previous page" @click="goToPage(page - 1)">‹</button>
                        <span>Page <strong aria-current="page">{{ page }}</strong> of {{ lastPage }}</span>
                        <button type="button" :disabled="page >= lastPage || loading" aria-label="Next page" @click="goToPage(page + 1)">›</button>
                    </div>
                </footer>
            </template>
        </section>

        <div v-if="dialog.user" class="admin-users-modal-backdrop" @click.self="closeDialog" @keydown.esc="closeDialog">
            <section class="admin-users-dialog" role="dialog" aria-modal="true" tabindex="-1" :aria-labelledby="dialogTitleId" @keydown.esc="closeDialog">
                <template v-if="dialog.type === 'view'">
                    <p class="admin-users-dialog-eyebrow">User account</p>
                    <template v-if="dialog.loading">
                        <h2 :id="dialogTitleId">Loading user details</h2>
                        <div class="admin-users-dialog-state" role="status"><span class="admin-users-spinner" aria-hidden="true"></span> Loading user details…</div>
                    </template>
                    <template v-else-if="dialog.error">
                        <h2 :id="dialogTitleId">User details unavailable</h2>
                        <div class="admin-users-dialog-state is-error" role="alert">{{ dialog.error }}<button type="button" @click="loadUserDetails">Try again</button></div>
                    </template>
                    <template v-else>
                        <div class="admin-users-view-identity">
                            <img v-if="dialog.user.avatar" class="admin-users-view-avatar" :src="dialog.user.avatar" alt="">
                            <span v-else class="admin-users-view-avatar" aria-hidden="true">{{ initials(dialog.user.name) }}</span>
                            <div><h2 :id="dialogTitleId">{{ dialog.user.name || 'Unnamed user' }}</h2><p>{{ dialog.user.email || 'No email address' }}</p></div>
                            <span class="admin-users-status" :class="`is-${dialog.user.status || 'inactive'}`"><i></i>{{ statusLabel(dialog.user.status) }}</span>
                        </div>

                        <section class="admin-users-detail-section" aria-labelledby="user-personal-details-title">
                            <h3 id="user-personal-details-title">Personal information</h3>
                            <dl class="admin-users-detail-list">
                                <div><dt>First name</dt><dd>{{ dialog.user.first_name || 'Not provided' }}</dd></div>
                                <div><dt>Last name</dt><dd>{{ dialog.user.last_name || 'Not provided' }}</dd></div>
                                <div><dt>Email address</dt><dd>{{ dialog.user.email || 'Not provided' }}</dd></div>
                                <div><dt>Phone number</dt><dd>{{ dialog.user.phone || 'Not provided' }}</dd></div>
                                <div><dt>Username</dt><dd>{{ dialog.user.username || 'Not provided' }}</dd></div>
                                <div><dt>Avatar</dt><dd>{{ dialog.user.avatar ? 'Profile image set' : 'Not provided' }}</dd></div>
                            </dl>
                        </section>
                        <section class="admin-users-detail-section" aria-labelledby="user-account-details-title">
                            <h3 id="user-account-details-title">Account and activity</h3>
                            <dl class="admin-users-detail-list">
                                <div><dt>Role</dt><dd>{{ roleLabel(dialog.user.role) }}</dd></div>
                                <div><dt>Registered</dt><dd>{{ formatDateTime(dialog.user.created_at) }}</dd></div>
                                <div><dt>Last login</dt><dd>{{ dialog.user.last_login_at ? formatDateTime(dialog.user.last_login_at) : 'Never' }}</dd></div>
                                <div><dt>Email verified</dt><dd>{{ dialog.user.email_verified_at ? formatDateTime(dialog.user.email_verified_at) : 'Not verified' }}</dd></div>
                                <div v-if="dialog.user.status_reason"><dt>Status reason</dt><dd>{{ dialog.user.status_reason }}</dd></div>
                                <div v-if="dialog.user.status_changed_at"><dt>Status changed</dt><dd>{{ formatDateTime(dialog.user.status_changed_at) }}<span v-if="dialog.user.status_changed_by?.name"> by {{ dialog.user.status_changed_by.name }}</span></dd></div>
                                <div v-if="dialog.user.updated_at"><dt>Last updated</dt><dd>{{ formatDateTime(dialog.user.updated_at) }}</dd></div>
                                <div v-if="dialog.user.role === 'moderator'"><dt>Moderator permissions</dt><dd>{{ dialog.user.moderator_permissions?.length ? dialog.user.moderator_permissions.join(', ') : 'None assigned' }}</dd></div>
                            </dl>
                        </section>
                        <div class="admin-users-dialog-actions"><button class="admin-users-secondary-button" type="button" @click="closeDialog">Close</button><button v-if="isAdmin" class="admin-users-primary-button" type="button" @click="openEditDialog(dialog.user)">Edit user</button></div>
                    </template>
                </template>
                <template v-else-if="dialog.type === 'edit'">
                    <p class="admin-users-dialog-eyebrow">Account details</p>
                    <h2 :id="dialogTitleId">Edit user</h2>
                    <p class="admin-users-dialog-copy">Update profile details, access role and account status.</p>
                    <div v-if="dialog.error" class="admin-users-dialog-state is-error" role="alert">{{ dialog.error }}</div>
                    <div class="admin-users-dialog-fields">
                        <label class="admin-users-dialog-field">First name<input v-model.trim="dialog.first_name" type="text" maxlength="50" autocomplete="given-name" :aria-invalid="Boolean(dialog.fieldErrors.first_name)" @input="clearDialogError('first_name')"><small v-if="dialog.fieldErrors.first_name" class="admin-users-field-error">{{ dialog.fieldErrors.first_name }}</small></label>
                        <label class="admin-users-dialog-field">Last name<input v-model.trim="dialog.last_name" type="text" maxlength="50" autocomplete="family-name" :aria-invalid="Boolean(dialog.fieldErrors.last_name || dialog.fieldErrors.name)" @input="clearDialogError('last_name'); clearDialogError('name')"><small v-if="dialog.fieldErrors.last_name || dialog.fieldErrors.name" class="admin-users-field-error">{{ dialog.fieldErrors.last_name || dialog.fieldErrors.name }}</small></label>
                        <label class="admin-users-dialog-field">Email address<input v-model.trim="dialog.email" type="email" maxlength="255" autocomplete="email" :aria-invalid="Boolean(dialog.fieldErrors.email)" @input="clearDialogError('email')" @blur="dialog.email = dialog.email.trim().toLowerCase()"><small v-if="dialog.fieldErrors.email" class="admin-users-field-error">{{ dialog.fieldErrors.email }}</small></label>
                        <label class="admin-users-dialog-field">Phone number<input v-model.trim="dialog.phone" type="tel" maxlength="32" autocomplete="tel" :aria-invalid="Boolean(dialog.fieldErrors.phone)" @input="clearDialogError('phone')"><small v-if="dialog.fieldErrors.phone" class="admin-users-field-error">{{ dialog.fieldErrors.phone }}</small></label>
                        <label class="admin-users-dialog-field">Username<input v-model.trim="dialog.username" type="text" maxlength="255" autocomplete="username" :aria-invalid="Boolean(dialog.fieldErrors.username)" @input="clearDialogError('username')"><small v-if="dialog.fieldErrors.username" class="admin-users-field-error">{{ dialog.fieldErrors.username }}</small></label>
                        <div class="admin-users-dialog-field admin-users-dialog-field-wide">
                            <span>Profile avatar</span>
                            <div class="admin-users-avatar-editor admin-avatar-upload">
                                <span class="admin-avatar-upload-preview admin-users-view-avatar">
                                    <img v-if="editAvatarPreview" :src="editAvatarPreview" alt="Profile avatar preview">
                                    <span v-else aria-label="Default avatar initials">{{ initials(dialog.user.name) }}</span>
                                </span>
                                <div class="admin-avatar-upload-controls">
                                    <input id="edit-user-avatar" ref="editAvatarInput" class="admin-avatar-upload-input" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Choose profile avatar" :aria-invalid="Boolean(dialog.fieldErrors.avatar)" @change="onEditAvatarChange">
                                    <div class="admin-avatar-upload-actions">
                                        <label class="admin-avatar-upload-button" for="edit-user-avatar">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5h14v-5"/></svg>
                                            {{ dialog.avatarFile || editAvatarPreview ? 'Change image' : 'Choose image' }}
                                        </label>
                                        <span class="admin-avatar-upload-filename" :title="dialog.avatarFile?.name || (dialog.user.avatar && !dialog.removeAvatar ? 'Current profile image' : 'No file selected')">{{ dialog.avatarFile?.name || (dialog.user.avatar && !dialog.removeAvatar ? 'Current profile image' : 'No file selected') }}</span>
                                    </div>
                                    <button v-if="editAvatarPreview || dialog.avatarFile" class="admin-users-avatar-remove" type="button" @click="removeEditAvatar">Remove avatar</button>
                                    <small>JPG, PNG or WebP. Maximum file size 2 MB.</small>
                                    <small v-if="dialog.fieldErrors.avatar" class="admin-users-field-error">{{ dialog.fieldErrors.avatar }}</small>
                                </div>
                            </div>
                        </div>
                        <label v-if="isAdmin" class="admin-users-dialog-field">Role<select v-model="dialog.role" :disabled="dialog.user.role === 'admin' || !accessOptionsLoaded" :title="dialog.user.role === 'admin' ? 'Administrator account roles cannot be changed.' : !accessOptionsLoaded ? 'Role options are unavailable.' : ''" :aria-invalid="Boolean(dialog.fieldErrors.role)" @change="onEditRoleChange"><option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option></select><small v-if="dialog.user.role === 'admin'">Administrator account roles cannot be changed.</small><small v-if="dialog.fieldErrors.role" class="admin-users-field-error">{{ dialog.fieldErrors.role }}</small></label>
                        <div v-if="isAdmin && dialog.role === 'moderator'" class="admin-users-dialog-field admin-users-dialog-field-wide">
                            <span>Moderator permissions</span>
                            <div class="admin-users-permission-options">
                                <label v-for="permission in moderatorPermissionOptions" :key="permission.key">
                                    <input v-model="dialog.moderator_permissions" type="checkbox" :value="permission.key">
                                    <span><strong>{{ permission.key }}</strong><small>{{ permission.description }}</small></span>
                                </label>
                            </div>
                            <small v-if="!accessOptionsLoaded">Moderator permissions could not be loaded.</small>
                            <small v-if="dialog.fieldErrors.moderator_permissions" class="admin-users-field-error">{{ dialog.fieldErrors.moderator_permissions }}</small>
                        </div>
                        <label class="admin-users-dialog-field">Account status<select v-model="dialog.status" :disabled="Boolean(statusActionDisabledReason(dialog.user))" :title="statusActionDisabledReason(dialog.user) || ''" :aria-invalid="Boolean(dialog.fieldErrors.status)" @change="dialog.confirmed = false; clearDialogError('status')"><option v-for="status in statuses" :key="status" :value="status">{{ statusLabel(status) }}</option></select><small>Current: {{ statusLabel(dialog.originalStatus) }}</small><small v-if="statusActionDisabledReason(dialog.user)">{{ statusActionDisabledReason(dialog.user) }}</small><small v-if="dialog.fieldErrors.status" class="admin-users-field-error">{{ dialog.fieldErrors.status }}</small></label>
                        <label v-if="['suspended', 'banned'].includes(dialog.status)" class="admin-users-dialog-field admin-users-dialog-field-wide">Reason (required, at least 5 characters)<textarea v-model.trim="dialog.reason" minlength="5" maxlength="2000" rows="3" :aria-invalid="Boolean(dialog.fieldErrors.reason)" @input="clearDialogError('reason')"></textarea><small v-if="dialog.fieldErrors.reason" class="admin-users-field-error">{{ dialog.fieldErrors.reason }}</small></label>
                        <label v-if="isCurrentAccount(dialog.user)" class="admin-users-dialog-field admin-users-dialog-field-wide">Current password<input v-model="dialog.currentPassword" type="password" autocomplete="current-password" :aria-invalid="Boolean(dialog.fieldErrors.current_password)" @input="clearDialogError('current_password')"><small v-if="dialog.fieldErrors.current_password" class="admin-users-field-error">{{ dialog.fieldErrors.current_password }}</small></label>
                        <label v-if="isCurrentAccount(dialog.user)" class="admin-users-dialog-field">New password<input v-model="dialog.newPassword" type="password" autocomplete="new-password" minlength="12" maxlength="128" :aria-invalid="Boolean(dialog.fieldErrors.password)" @input="clearDialogError('password')"><small>At least 12 characters, with uppercase, lowercase and a number.</small><small v-if="dialog.fieldErrors.password" class="admin-users-field-error">{{ dialog.fieldErrors.password }}</small></label>
                        <label v-if="isCurrentAccount(dialog.user)" class="admin-users-dialog-field">Confirm new password<input v-model="dialog.passwordConfirmation" type="password" autocomplete="new-password" :aria-invalid="Boolean(dialog.fieldErrors.password_confirmation)" @input="clearDialogError('password_confirmation')"><small v-if="dialog.fieldErrors.password_confirmation" class="admin-users-field-error">{{ dialog.fieldErrors.password_confirmation }}</small></label>
                    </div>
                    <p v-if="isCurrentAccount(dialog.user)" class="admin-users-dialog-note">Password changes require your current password and are recorded securely.</p>
                    <label v-if="dialog.status !== dialog.originalStatus" class="admin-users-confirm-check"><input v-model="dialog.confirmed" type="checkbox"> <span>I confirm changing this account’s status to <strong>{{ statusLabel(dialog.status) }}</strong>.</span></label>
                    <div class="admin-users-dialog-actions"><button class="admin-users-secondary-button" type="button" :disabled="dialog.saving" @click="closeDialog">Cancel</button><button class="admin-users-primary-button" type="button" :disabled="dialog.saving" @click="confirmEdit">{{ dialog.saving ? 'Saving…' : 'Save changes' }}</button></div>
                </template>
                <template v-else-if="dialog.type === 'status'">
                    <p class="admin-users-dialog-eyebrow">Account access</p>
                    <h2 :id="dialogTitleId">Change user status</h2>
                    <p class="admin-users-dialog-copy">Choose the account access state for <strong>{{ dialog.user.name }}</strong>.</p>
                    <div class="admin-users-current-status">Current status <span class="admin-users-status" :class="`is-${dialog.user.status}`"><i></i>{{ statusLabel(dialog.user.status) }}</span></div>
                    <div class="admin-users-status-options" role="radiogroup" aria-label="New account status">
                        <label v-for="status in statuses" :key="status" class="admin-users-status-option" :class="{ 'is-selected': dialog.status === status }">
                            <input v-model="dialog.status" type="radio" :value="status" @change="dialog.confirmed = false; clearDialogError('status')">
                            <span class="admin-users-status" :class="`is-${status}`"><i></i>{{ statusLabel(status) }}</span>
                            <small>{{ statusDescriptions[status] }}</small>
                            <em v-if="status === dialog.user.status">Current</em>
                        </label>
                    </div>
                    <label v-if="['suspended', 'banned'].includes(dialog.status)" class="admin-users-dialog-field admin-users-dialog-field-wide">Reason (required, at least 5 characters)<textarea v-model.trim="dialog.reason" minlength="5" maxlength="2000" rows="3" :aria-invalid="Boolean(dialog.fieldErrors.reason)" @input="clearDialogError('reason')"></textarea><small v-if="dialog.fieldErrors.reason" class="admin-users-field-error">{{ dialog.fieldErrors.reason }}</small></label>
                    <p v-if="dialog.error" class="admin-users-dialog-state is-error" role="alert">{{ dialog.error }}</p>
                    <label class="admin-users-confirm-check"><input v-model="dialog.confirmed" type="checkbox"> <span>I confirm changing this account’s status to <strong>{{ statusLabel(dialog.status) }}</strong>.</span></label>
                    <div class="admin-users-dialog-actions"><button class="admin-users-secondary-button" type="button" :disabled="dialog.saving" @click="closeDialog">Cancel</button><button class="admin-users-primary-button" type="button" :disabled="dialog.saving || !dialog.confirmed" @click="confirmStatus">{{ dialog.saving ? 'Updating…' : 'Confirm status change' }}</button></div>
                </template>
                <template v-else>
                    <p class="admin-users-dialog-eyebrow is-danger">Remove account</p>
                    <h2 :id="dialogTitleId">Delete this user?</h2>
                    <div class="admin-users-delete-identity">
                        <img v-if="dialog.user.avatar" class="admin-users-view-avatar" :src="dialog.user.avatar" alt="">
                        <span v-else class="admin-users-view-avatar" aria-hidden="true">{{ initials(dialog.user.name) }}</span>
                        <div><strong>{{ dialog.user.name || 'Unnamed user' }}</strong><span>{{ dialog.user.email || 'No email address' }}</span></div>
                    </div>
                    <div class="admin-users-delete-warning" role="note">
                        <strong>This account will lose access to Planora.</strong>
                        <span>The account will be soft-deleted and removed from the active user list. An administrator can restore it later.</span>
                    </div>
                    <label class="admin-users-dialog-field admin-users-delete-confirm-field" for="delete-confirmation">Type <strong>DELETE</strong> to confirm
                        <input id="delete-confirmation" v-model="dialog.deleteConfirmation" type="text" maxlength="6" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="DELETE" :aria-invalid="dialog.deleteConfirmation.length > 0 && dialog.deleteConfirmation !== 'DELETE'" @input="dialog.error = ''">
                        <small>The confirmation is case-sensitive.</small>
                    </label>
                    <p v-if="dialog.error" class="admin-users-dialog-state is-error" role="alert">{{ dialog.error }}</p>
                    <div class="admin-users-dialog-actions"><button class="admin-users-secondary-button" type="button" :disabled="dialog.saving" @click="closeDialog">Cancel</button><button class="admin-users-danger-button" type="button" :disabled="dialog.saving || dialog.deleteConfirmation !== 'DELETE'" @click="confirmDelete"><span v-if="dialog.saving" class="admin-users-danger-spinner" aria-hidden="true"></span>{{ dialog.saving ? 'Deleting…' : 'Delete user' }}</button></div>
                </template>
            </section>
        </div>
        <p v-if="notice" class="admin-users-toast" :class="{ 'is-error': noticeIsError }" :role="noticeIsError ? 'alert' : 'status'">{{ notice }}</p>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import api from '../../../shared/services/api.js';
import { validatePersonName, validateSupportedEmail } from '../../../shared/validation.js';

const auth = useAuthStore();
const users = ref([]);
const statsData = ref({ total_users: 0, active_users: 0, inactive_users: 0, new_users_last_30_days: 0 });
const activeAdmins = ref(null);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const error = ref('');
const notice = ref('');
const noticeIsError = ref(false);
const editAvatarInput = ref(null);
const searchInput = ref('');
const search = ref('');
const filters = reactive({ role: '', status: '', registered_from: '', registered_to: '' });
const dialog = reactive({
    type: '', user: null, loading: false, saving: false, error: '', fieldErrors: {},
    first_name: '', last_name: '', email: '', phone: '', username: '', role: '', status: '',
    currentPassword: '', newPassword: '', passwordConfirmation: '', moderator_permissions: [],
    avatarFile: null, avatarPreview: '', removeAvatar: false,
    originalStatus: '', reason: '', confirmed: false, deleteConfirmation: '',
});
let searchTimer;
let noticeTimer;
let loadRequestSequence = 0;
const statuses = ['active', 'inactive', 'suspended', 'banned'];
const roles = ref(['user', 'moderator', 'admin']);
const moderatorPermissionOptions = ref([]);
const accessOptionsLoaded = ref(false);
const statusDescriptions = {
    active: 'Can sign in and use the account.',
    inactive: 'Account is disabled until reactivated.',
    suspended: 'Temporarily restricts account access.',
    banned: 'Blocks access to the account.',
};
const dialogTitleId = 'admin-users-dialog-title';
const isAdmin = computed(() => auth.hasRole('admin'));
const canManageStatus = computed(() => isAdmin.value
    || (auth.hasManagementPermission('users.view') && auth.hasManagementPermission('users.status.manage')));
const usersPerPage = 10;
const editAvatarPreview = computed(() => dialog.avatarPreview || (dialog.removeAvatar ? '' : dialog.user?.avatar || ''));

const hasFilters = computed(() => Boolean(search.value || filters.role || filters.status || filters.registered_from || filters.registered_to));
const stats = computed(() => [
    { label: 'Total users', value: statsData.value.total_users, icon: 'users', tone: 'primary' },
    { label: 'Active users', value: statsData.value.active_users, icon: 'active', tone: 'success' },
    { label: 'Inactive users', value: statsData.value.inactive_users, icon: 'inactive', tone: 'neutral' },
    { label: 'New users', value: statsData.value.new_users_last_30_days, icon: 'new', tone: 'violet' },
]);
const rangeStart = computed(() => total.value ? (page.value - 1) * usersPerPage + 1 : 0);
const rangeEnd = computed(() => Math.min(page.value * usersPerPage, total.value));

function formatNumber(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
function initials(name) { return String(name || 'User').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join(''); }
function roleLabel(role) { return ({ user: 'User', moderator: 'Moderator', admin: 'Admin' })[role] || role; }
function statusLabel(status) { return String(status || 'unknown').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
}
function formatDateTime(value) {
    if (!value) return 'Not provided';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
function formatRelativeDate(value) {
    const elapsed = Date.now() - new Date(value).getTime();
    if (elapsed < 60_000) return 'Just now';
    if (elapsed < 3_600_000) return `${Math.floor(elapsed / 60_000)}m ago`;
    if (elapsed < 86_400_000) return `${Math.floor(elapsed / 3_600_000)}h ago`;
    if (elapsed < 604_800_000) return `${Math.floor(elapsed / 86_400_000)}d ago`;
    return formatDate(value);
}

async function loadUsers({ notifyError = false } = {}) {
    const requestSequence = ++loadRequestSequence;
    loading.value = true;
    error.value = '';
    const params = { page: page.value, per_page: usersPerPage, sort: 'newest' };
    if (search.value) params.search = search.value;
    if (filters.role) params.role = filters.role;
    if (filters.status) params.status = filters.status;
    if (filters.registered_from) params.registered_from = filters.registered_from;
    if (filters.registered_to) params.registered_to = filters.registered_to;
    try {
        const { data } = await api.get('/admin/users', { params });
        if (requestSequence !== loadRequestSequence) return;
        const responseLastPage = Math.max(1, Number(data.meta?.last_page || 1));
        const responsePage = Math.max(1, Number(data.meta?.current_page || page.value));
        if (responsePage > responseLastPage) {
            page.value = responseLastPage;
            await loadUsers();
            return;
        }
        users.value = data.data || [];
        total.value = Number(data.meta?.total || 0);
        activeAdmins.value = data.meta?.active_admins == null ? null : Number(data.meta.active_admins);
        page.value = responsePage;
        lastPage.value = responseLastPage;
        statsData.value = data.meta?.statistics || statsData.value;
    } catch (requestError) {
        if (requestSequence !== loadRequestSequence) return;
        error.value = requestError.response?.status === 403
            ? 'You do not have permission to manage users.'
            : 'User information could not be loaded. Please try again.';
        if (notifyError) showNotice(error.value, true);
        return false;
    } finally {
        if (requestSequence === loadRequestSequence) loading.value = false;
    }
    return true;
}

function refreshUsers() {
    if (loading.value) return;
    return loadUsers({ notifyError: true });
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { search.value = searchInput.value.trim(); page.value = 1; loadUsers(); }, 300);
}
function applyFilters() { page.value = 1; loadUsers(); }
function clearFilters() {
    searchInput.value = '';
    search.value = '';
    filters.role = '';
    filters.status = '';
    filters.registered_from = '';
    filters.registered_to = '';
    page.value = 1;
    loadUsers();
}
function goToPage(value) {
    if (loading.value || value < 1 || value > lastPage.value || value === page.value) return;
    page.value = value;
    loadUsers();
}
function isCurrentAccount(user) { return String(auth.user?.id ?? '') === String(user?.id ?? ''); }
function isLastActiveAdministrator(user) {
    return user?.role === 'admin' && user.status === 'active' && activeAdmins.value !== null && activeAdmins.value <= 1;
}
function statusActionDisabledReason(user) {
    if (isCurrentAccount(user)) return 'You cannot change your own account status.';
    if (isLastActiveAdministrator(user)) return 'The last active administrator cannot be deactivated or deleted.';
    return '';
}
function deleteActionDisabledReason(user) {
    if (isCurrentAccount(user)) return 'You cannot delete your own account.';
    if (isLastActiveAdministrator(user)) return 'The last active administrator cannot be deactivated or deleted.';
    return '';
}
function clearDialogError(field) {
    delete dialog.fieldErrors[field];
    dialog.error = '';
}
function resetDialogErrors() {
    dialog.error = '';
    dialog.fieldErrors = {};
}
function openStatusDialog(user) {
    resetDialogErrors();
    dialog.type = 'status';
    dialog.user = user;
    dialog.status = user.status;
    dialog.originalStatus = user.status;
    dialog.reason = '';
    dialog.confirmed = false;
    dialog.saving = false;
}
function openViewDialog(user) {
    resetDialogErrors();
    dialog.type = 'view';
    dialog.user = user;
    dialog.loading = true;
    loadUserDetails(user.id);
}
async function loadUserDetails(userId = dialog.user?.id) {
    if (!userId) return;
    dialog.loading = true;
    dialog.error = '';
    try {
        const { data } = await api.get(`/admin/users/${userId}`);
        if (dialog.type === 'view' && dialog.user?.id === userId) dialog.user = data.user;
    } catch (requestError) {
        if (dialog.type === 'view' && dialog.user?.id === userId) {
            dialog.error = requestError.response?.status === 403
                ? 'You do not have permission to view this user.'
                : 'User details could not be loaded.';
        }
    } finally {
        if (dialog.type === 'view' && dialog.user?.id === userId) dialog.loading = false;
    }
}
function openEditDialog(user) {
    resetDialogErrors();
    dialog.type = 'edit';
    dialog.user = user;
    const nameParts = String(user.name || '').trim().split(/\s+/).filter(Boolean);
    dialog.first_name = user.first_name ?? nameParts[0] ?? '';
    dialog.last_name = user.last_name ?? nameParts.slice(1).join(' ');
    dialog.email = user.email || '';
    dialog.phone = user.phone || '';
    dialog.username = user.username || '';
    dialog.moderator_permissions = user.role === 'moderator' ? [...(user.moderator_permissions || [])] : [];
    dialog.avatarFile = null;
    dialog.avatarPreview = '';
    dialog.removeAvatar = false;
    dialog.currentPassword = '';
    dialog.newPassword = '';
    dialog.passwordConfirmation = '';
    dialog.role = user.role;
    dialog.status = user.status;
    dialog.originalStatus = user.status;
    dialog.reason = '';
    dialog.confirmed = false;
}
function onEditRoleChange() {
    clearDialogError('role');
    clearDialogError('moderator_permissions');
    if (dialog.role === 'moderator') {
        if (!dialog.moderator_permissions.length && moderatorPermissionOptions.value.some(({ key }) => key === 'users.view')) {
            dialog.moderator_permissions = ['users.view'];
        }
    } else {
        dialog.moderator_permissions = [];
    }
}
async function loadAccessOptions() {
    try {
        const { data } = await api.get('/admin/users/roles-permissions');
        const configuredRoles = Object.keys(data.roles || {});
        if (configuredRoles.length) roles.value = configuredRoles;
        moderatorPermissionOptions.value = Object.entries(data.permissions || {}).map(([key, description]) => ({ key, description }));
        accessOptionsLoaded.value = true;
    } catch {
        moderatorPermissionOptions.value = [];
        accessOptionsLoaded.value = false;
    }
}
function openDeleteDialog(user) {
    resetDialogErrors();
    dialog.type = 'delete';
    dialog.user = user;
    dialog.deleteConfirmation = '';
    dialog.saving = false;
}
function closeDialog() {
    if (!dialog.saving) {
        if (dialog.avatarPreview) URL.revokeObjectURL(dialog.avatarPreview);
        dialog.type = '';
        dialog.user = null;
        dialog.loading = false;
        dialog.confirmed = false;
        dialog.reason = '';
        dialog.status = '';
        dialog.originalStatus = '';
        dialog.deleteConfirmation = '';
        dialog.currentPassword = '';
        dialog.newPassword = '';
        dialog.passwordConfirmation = '';
        dialog.avatarFile = null;
        dialog.avatarPreview = '';
        dialog.removeAvatar = false;
        resetDialogErrors();
    }
}
function showNotice(message, isError = false) {
    notice.value = message;
    noticeIsError.value = isError;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { notice.value = ''; noticeIsError.value = false; }, 3200);
}
function onEditAvatarChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    clearDialogError('avatar');
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        dialog.fieldErrors.avatar = 'Choose a JPG, PNG or WebP image.';
        event.target.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        dialog.fieldErrors.avatar = 'The avatar must be 2 MB or smaller.';
        event.target.value = '';
        return;
    }
    if (dialog.avatarPreview) URL.revokeObjectURL(dialog.avatarPreview);
    dialog.avatarFile = file;
    dialog.avatarPreview = URL.createObjectURL(file);
    dialog.removeAvatar = false;
}
function removeEditAvatar() {
    if (dialog.avatarPreview) URL.revokeObjectURL(dialog.avatarPreview);
    dialog.avatarPreview = '';
    dialog.avatarFile = null;
    dialog.removeAvatar = Boolean(dialog.user?.avatar);
    if (editAvatarInput.value) editAvatarInput.value.value = '';
    clearDialogError('avatar');
}
async function confirmStatus() {
    if (!dialog.user || !dialog.confirmed || dialog.saving) return;
    if (statusActionDisabledReason(dialog.user)) {
        dialog.error = statusActionDisabledReason(dialog.user);
        return;
    }
    if (['suspended', 'banned'].includes(dialog.status) && dialog.reason.trim().length < 5) {
        dialog.fieldErrors.reason = 'Enter a reason of at least 5 characters.';
        return;
    }
    dialog.saving = true;
    try {
        await api.patch(`/admin/users/${dialog.user.id}/status`, { status: dialog.status, ...(dialog.reason ? { reason: dialog.reason.trim() } : {}) });
        dialog.saving = false;
        closeDialog();
        showNotice('User status updated.');
        await loadUsers();
    } catch (requestError) {
        applyDialogValidationErrors(requestError.response?.data?.errors);
        dialog.error = requestError.response?.data?.message || 'The user status could not be updated.';
    } finally { dialog.saving = false; }
}
function validateEditDialog() {
    dialog.fieldErrors = {};
    dialog.error = '';
    dialog.first_name = dialog.first_name.trim().replace(/\s+/gu, ' ');
    dialog.last_name = dialog.last_name.trim().replace(/\s+/gu, ' ');
    dialog.email = dialog.email.trim().toLowerCase();
    const fullName = `${dialog.first_name} ${dialog.last_name}`.trim();
    const nameUnchanged = fullName === String(dialog.user.name || '');
    if (!dialog.first_name.trim() && !dialog.last_name.trim()) dialog.fieldErrors.first_name = 'Enter a first name or last name.';
    if (!nameUnchanged && dialog.first_name && validatePersonName(dialog.first_name)) dialog.fieldErrors.first_name = validatePersonName(dialog.first_name);
    if (!nameUnchanged && dialog.last_name && validatePersonName(dialog.last_name)) dialog.fieldErrors.last_name = validatePersonName(dialog.last_name);
    if (!dialog.email.trim()) dialog.fieldErrors.email = 'Email address is required.';
    else if (dialog.email.toLowerCase() !== String(dialog.user.email || '').toLowerCase() && validateSupportedEmail(dialog.email)) dialog.fieldErrors.email = validateSupportedEmail(dialog.email);
    if (dialog.phone && !/^\+?[0-9().\s-]{7,32}$/.test(dialog.phone)) dialog.fieldErrors.phone = 'Enter a valid phone number using digits and standard phone punctuation.';
    if (dialog.role === 'moderator' && dialog.moderator_permissions.includes('users.status.manage') && !dialog.moderator_permissions.includes('users.view')) {
        dialog.fieldErrors.moderator_permissions = 'User visibility is required for status management.';
    }
    if (!nameUnchanged && Array.from(fullName).length > 50) dialog.fieldErrors.name = 'The full name must be 50 characters or fewer.';
    const passwordRequested = Boolean(dialog.currentPassword || dialog.newPassword || dialog.passwordConfirmation);
    if (passwordRequested) {
        if (!isCurrentAccount(dialog.user)) dialog.fieldErrors.password = 'You can only change your own password.';
        if (!dialog.currentPassword) dialog.fieldErrors.current_password = 'Enter your current password.';
        if (dialog.newPassword.length < 12 || dialog.newPassword.length > 128 || !/[a-z]/.test(dialog.newPassword) || !/[A-Z]/.test(dialog.newPassword) || !/\d/.test(dialog.newPassword)) {
            dialog.fieldErrors.password = 'Use at least 12 characters with uppercase, lowercase and a number.';
        }
        if (dialog.newPassword !== dialog.passwordConfirmation) dialog.fieldErrors.password_confirmation = 'The password confirmation does not match.';
    }
    if (dialog.status !== dialog.originalStatus) {
        if (!dialog.confirmed) dialog.fieldErrors.status = 'Confirm the account status change.';
        if (['suspended', 'banned'].includes(dialog.status) && dialog.reason.trim().length < 5) dialog.fieldErrors.reason = 'Enter a reason of at least 5 characters.';
    }
    return Object.keys(dialog.fieldErrors).length === 0;
}
function applyDialogValidationErrors(validationErrors = {}) {
    for (const [field, messages] of Object.entries(validationErrors)) {
        const mappedField = field === 'name' ? 'last_name' : field;
        const message = Array.isArray(messages) ? messages[0] : String(messages);
        if (['first_name', 'last_name', 'name', 'email', 'phone', 'username', 'avatar', 'role', 'status', 'reason', 'moderator_permissions', 'current_password', 'password', 'password_confirmation'].includes(mappedField)) {
            dialog.fieldErrors[mappedField] = message;
        }
    }
}
async function confirmEdit() {
    if (!dialog.user || !validateEditDialog()) return;
    if (dialog.user.role === 'admin' && dialog.role !== dialog.user.role) {
        dialog.fieldErrors.role = 'Administrator account roles cannot be changed.';
        return;
    }
    if (isCurrentAccount(dialog.user) && dialog.status !== dialog.originalStatus) {
        dialog.fieldErrors.status = 'You cannot change your own account status.';
        return;
    }
    if (dialog.status !== dialog.originalStatus && statusActionDisabledReason(dialog.user)) {
        dialog.fieldErrors.status = statusActionDisabledReason(dialog.user);
        return;
    }
    dialog.saving = true;
    const userId = dialog.user.id;
    let profileSaved = false;
    let roleSaved = false;
    let statusSaved = false;
    let passwordSaved = false;
    try {
        const profilePayload = {
            name: `${dialog.first_name} ${dialog.last_name}`.trim(),
            first_name: dialog.first_name.trim(),
            last_name: dialog.last_name.trim(),
            email: dialog.email.trim(),
            phone: dialog.phone.trim() || null,
            username: dialog.username.trim() || null,
        };
        let profileResponse;
        if (dialog.avatarFile || dialog.removeAvatar) {
            const multipart = new FormData();
            multipart.append('_method', 'PATCH');
            for (const [field, value] of Object.entries(profilePayload)) multipart.append(field, value ?? '');
            if (dialog.avatarFile) multipart.append('avatar', dialog.avatarFile);
            if (dialog.removeAvatar) multipart.append('remove_avatar', '1');
            profileResponse = await api.post(`/admin/users/${userId}`, multipart);
        } else {
            profileResponse = await api.patch(`/admin/users/${userId}`, profilePayload);
        }
        const { data: profileData } = profileResponse;
        dialog.user = profileData.user;
        profileSaved = true;

        if (isAdmin.value && dialog.role !== dialog.user.role) {
            const { data: roleData } = await api.patch(`/admin/users/${userId}/role`, {
                role: dialog.role,
                ...(dialog.role === 'moderator' ? { moderator_permissions: dialog.moderator_permissions } : {}),
            });
            dialog.user = roleData.user;
            roleSaved = true;
        } else if (isAdmin.value && accessOptionsLoaded.value && dialog.role === 'moderator'
            && JSON.stringify([...dialog.moderator_permissions].sort()) !== JSON.stringify([...(dialog.user.moderator_permissions || [])].sort())) {
            const { data: permissionData } = await api.put(`/admin/users/${userId}/permissions`, { permissions: dialog.moderator_permissions });
            dialog.user = permissionData.user;
            roleSaved = true;
        }

        if (dialog.status !== dialog.originalStatus) {
            const { data: statusData } = await api.patch(`/admin/users/${userId}/status`, {
                status: dialog.status,
                ...(['suspended', 'banned'].includes(dialog.status) ? { reason: dialog.reason.trim() } : {}),
            });
            dialog.user = statusData.user;
            statusSaved = true;
        }

        if (dialog.newPassword) {
            await api.post(`/admin/users/${userId}/password`, {
                current_password: dialog.currentPassword,
                password: dialog.newPassword,
                password_confirmation: dialog.passwordConfirmation,
            });
            passwordSaved = true;
        }

        dialog.saving = false;
        closeDialog();
        showNotice('User details updated.');
        await loadUsers();
    } catch (requestError) {
        applyDialogValidationErrors(requestError.response?.data?.errors);
        const partialSave = profileSaved || roleSaved || statusSaved || passwordSaved;
        dialog.error = partialSave
            ? `Some changes were saved before the request failed. ${requestError.response?.data?.message || 'Review the remaining fields and try again.'}`
            : requestError.response?.data?.message || 'User details could not be updated.';
    } finally { dialog.saving = false; }
}
async function confirmDelete() {
    if (!dialog.user || dialog.deleteConfirmation !== 'DELETE' || dialog.saving) return;
    if (deleteActionDisabledReason(dialog.user)) {
        dialog.error = deleteActionDisabledReason(dialog.user);
        return;
    }
    dialog.saving = true;
    try {
        await api.delete(`/admin/users/${dialog.user.id}`);
        dialog.saving = false;
        closeDialog();
        showNotice('User moved to deleted accounts.');
        await loadUsers();
    } catch (requestError) {
        const response = requestError.response;
        const validationMessage = Object.values(response?.data?.errors || {}).flat()[0];
        dialog.error = response?.status === 403
            ? 'You do not have permission to delete this user.'
            : response?.status === 404
                ? 'This user could not be found. The user list may have changed; close this dialog and refresh the list.'
                : validationMessage || response?.data?.message || 'The delete request failed. Check your connection and try again.';
    } finally { dialog.saving = false; }
}

onMounted(() => { loadUsers(); loadAccessOptions(); });
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(noticeTimer);
    if (dialog.avatarPreview) URL.revokeObjectURL(dialog.avatarPreview);
});
</script>

<style src="../../../../css/pages/admin/users/styles/UsersIndex.css" scoped></style>
<style src="../../../../css/pages/admin/users/styles/UserAvatarUpload.css" scoped></style>
