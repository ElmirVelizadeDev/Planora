import assert from 'node:assert/strict';
import test from 'node:test';
import { computed, reactive, ref } from 'vue';
import { settingsAreDirty, settingsSaveStatus } from '../../resources/js/features/settings/admin/composables/settingsState.js';

test('reactive settings edits mark the form dirty and reverting restores the saved state', () => {
    const values = reactive({ workspace_name: 'Planora', timezone: 'UTC', enabled: true });
    const savedValues = ref({ workspace_name: 'Planora', timezone: 'UTC', enabled: true });
    const dirty = computed(() => settingsAreDirty(values, savedValues.value));

    assert.equal(dirty.value, false);
    values.workspace_name = 'Product Studio';
    assert.equal(dirty.value, true);
    values.workspace_name = 'Planora';
    assert.equal(dirty.value, false);
    values.enabled = false;
    assert.equal(dirty.value, true);
    savedValues.value = { workspace_name: 'Planora', timezone: 'UTC', enabled: false };
    assert.equal(dirty.value, false);
});

test('settings save bar status reflects saving, unsaved, and saved states', () => {
    assert.equal(settingsSaveStatus(true, true), 'Saving…');
    assert.equal(settingsSaveStatus(true, false), 'Unsaved changes.');
    assert.equal(settingsSaveStatus(false, false), 'All changes are saved.');
});
