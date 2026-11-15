import { computed, reactive, ref, watch } from 'vue';

export const APPEARANCE_DEFAULTS = Object.freeze({ theme: 'light', accent: '#5B6CFA', density: 'comfortable', sidebar: 'expanded' });
export const APPEARANCE_ACCENTS = Object.freeze([
    { value: '#5B6CFA', label: 'Planora Indigo' },
    { value: '#2563EB', label: 'Blue' },
    { value: '#0F766E', label: 'Teal' },
    { value: '#7C3AED', label: 'Violet' },
]);

const preferences = reactive({ ...APPEARANCE_DEFAULTS });
const ownerId = ref(null);
const ownerKey = (id) => `planora.appearance.v1.${String(id)}`;
const validAccent = (value) => APPEARANCE_ACCENTS.some((option) => option.value === value);
const validPreferences = (value) => ({
    theme: ['light', 'dark'].includes(value?.theme) ? value.theme : APPEARANCE_DEFAULTS.theme,
    accent: validAccent(value?.accent) ? value.accent : APPEARANCE_DEFAULTS.accent,
    density: ['comfortable', 'compact'].includes(value?.density) ? value.density : APPEARANCE_DEFAULTS.density,
    sidebar: ['expanded', 'collapsed'].includes(value?.sidebar) ? value.sidebar : APPEARANCE_DEFAULTS.sidebar,
});

export function appearanceAccentTokens(accent, theme = 'light') {
    const isPlanoraIndigo = accent === APPEARANCE_DEFAULTS.accent;
    const isDark = theme === 'dark';
    return {
        '--color-accent': accent,
        '--color-accent-hover': `color-mix(in srgb, ${accent} ${isDark ? '90%' : '92%'}, ${isDark || isPlanoraIndigo ? 'white' : 'black'})`,
        '--color-accent-active': `color-mix(in srgb, ${accent} ${isDark ? '82%' : '84%'}, ${isDark || isPlanoraIndigo ? 'white' : 'black'})`,
        '--color-accent-subtle': isDark ? `color-mix(in srgb, ${accent} 18%, var(--color-surface))` : `color-mix(in srgb, ${accent} 9%, white)`,
        '--color-accent-border': isDark ? `color-mix(in srgb, ${accent} 48%, var(--color-surface))` : `color-mix(in srgb, ${accent} 42%, white)`,
        '--color-accent-foreground': '#ffffff',
        '--color-accent-text': isDark ? `color-mix(in srgb, ${accent} 30%, white)` : isPlanoraIndigo ? '#4858e3' : accent,
        '--color-accent-profile-hover': `color-mix(in srgb, ${accent} ${isDark ? '90%' : '78%'}, ${isDark ? 'white' : 'black'})`,
        '--color-accent-shadow': `color-mix(in srgb, ${accent} ${isDark ? '24%' : '18%'}, transparent)`,
        '--color-focus-ring': `color-mix(in srgb, ${accent} ${isDark ? '50%' : '30%'}, transparent)`,
        '--color-selected-background': 'var(--color-accent-subtle)',
    };
}

function applyPreferences(value) {
    const next = validPreferences(value);
    Object.assign(preferences, next);
    document.documentElement.dataset.theme = next.theme;
    document.documentElement.style.colorScheme = next.theme;
    Object.entries(appearanceAccentTokens(next.accent, next.theme)).forEach(([token, tokenValue]) => {
        document.documentElement.style.setProperty(token, tokenValue);
    });
    document.documentElement.style.setProperty('--density-card-padding', next.density === 'compact' ? '16px' : '22px');
    document.documentElement.style.setProperty('--density-form-gap', next.density === 'compact' ? '12px' : '20px');
    document.documentElement.style.setProperty('--density-table-row-height', next.density === 'compact' ? '44px' : '56px');
    document.documentElement.style.setProperty('--density-nav-item-height', next.density === 'compact' ? '37px' : '43px');
    document.documentElement.dataset.density = next.density;
}

watch(preferences, (value) => applyPreferences(value), { deep: true, flush: 'sync' });

export function initializeAppearancePreferences(id) {
    if (id === null || id === undefined) return;
    if (ownerId.value === id) return;
    ownerId.value = id;
    let stored = null;
    try { stored = JSON.parse(localStorage.getItem(ownerKey(id)) || 'null'); } catch { /* Use defaults if storage is unavailable or malformed. */ }
    applyPreferences(stored || APPEARANCE_DEFAULTS);
}

export function resetAppearanceSession() {
    ownerId.value = null;
    applyPreferences(APPEARANCE_DEFAULTS);
}

export function useAppearancePreferences() {
    const saved = ref({ ...APPEARANCE_DEFAULTS });
    const initialized = ref(false);

    function load(id) {
        initializeAppearancePreferences(id);
        if (ownerId.value !== id) return;
        saved.value = { ...preferences };
        initialized.value = true;
    }

    function save() {
        if (!initialized.value || ownerId.value === null) return false;
        const next = validPreferences(preferences);
        try { localStorage.setItem(ownerKey(ownerId.value), JSON.stringify(next)); }
        catch { return false; }
        saved.value = next;
        applyPreferences(next);
        return true;
    }

    function restoreSaved() { applyPreferences(saved.value); }

    const dirty = computed(() => JSON.stringify(preferences) !== JSON.stringify(saved.value));
    return { preferences, saved, initialized, dirty, load, save, restoreSaved, apply: applyPreferences };
}

if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
        if (ownerId.value !== null && event.key === ownerKey(ownerId.value)) {
            try { applyPreferences(JSON.parse(event.newValue || '{}')); } catch { applyPreferences(APPEARANCE_DEFAULTS); }
            window.dispatchEvent(new Event('planora:appearance-updated'));
        }
    });
}
