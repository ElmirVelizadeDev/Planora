export function settingsAreDirty(values, savedValues) {
    return JSON.stringify(values) !== JSON.stringify(savedValues);
}

export function settingsSaveStatus(dirty, saving) {
    if (saving) return 'Saving…';
    return dirty ? 'Unsaved changes.' : 'All changes are saved.';
}
