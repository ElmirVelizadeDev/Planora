import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const { APPEARANCE_DEFAULTS, APPEARANCE_ACCENTS, appearanceAccentTokens, getGuestTheme, initializeAppearancePreferences, resetAppearanceSession, setGuestTheme, useAppearancePreferences } = await import('../../resources/js/features/appearance/composables/appearancePreferences.js');
const values = new Map();
const cssVariables = new Map();
globalThis.localStorage = {
    getItem(key) { return values.has(key) ? values.get(key) : null; },
    setItem(key, value) { values.set(key, String(value)); },
};
globalThis.document = { documentElement: { style: { setProperty(key, value) { cssVariables.set(key, value); } }, dataset: {} } };

test('appearance settings use one authenticated route without a role restriction', () => {
    const appearanceRoutes = routes.filter((route) => route.path === '/settings/appearance');
    assert.equal(appearanceRoutes.length, 1);
    assert.equal(appearanceRoutes[0].meta.requiresAuth, true);
    assert.equal(appearanceRoutes[0].meta.requiresRole, undefined);
    assert.equal(appearanceRoutes[0].children.filter((route) => route.name === 'settings.appearance').length, 1);
});

test('appearance selections preview immediately and save per authenticated account', () => {
    const accountOne = useAppearancePreferences();
    accountOne.load(41);
    assert.deepEqual({ ...accountOne.preferences }, APPEARANCE_DEFAULTS);

    accountOne.preferences.accent = '#0F766E';
    accountOne.preferences.theme = 'dark';
    accountOne.preferences.density = 'compact';
    assert.equal(cssVariables.get('--color-accent'), '#0F766E');
    assert.equal(cssVariables.get('--color-accent-hover'), 'color-mix(in srgb, #0F766E 90%, white)');
    assert.equal(document.documentElement.dataset.theme, 'dark');
    assert.equal(document.documentElement.style.colorScheme, 'dark');
    assert.equal(cssVariables.get('--color-selected-background'), 'var(--color-accent-subtle)');
    assert.equal(cssVariables.get('--density-card-padding'), '16px');
    assert.equal(accountOne.dirty.value, true);
    assert.equal(accountOne.save(), true);
    assert.equal(accountOne.dirty.value, false);

    const accountTwo = useAppearancePreferences();
    accountTwo.load(73);
    assert.deepEqual({ ...accountTwo.preferences }, APPEARANCE_DEFAULTS);
    accountTwo.preferences.sidebar = 'collapsed';
    assert.equal(accountTwo.save(), true);

    accountOne.load(41);
    assert.deepEqual({ ...accountOne.preferences }, { theme: 'dark', accent: '#0F766E', density: 'compact', sidebar: 'expanded' });
});

test('all four existing accent presets generate the complete shared token set', () => {
    const required = [
        '--color-accent', '--color-accent-hover', '--color-accent-active', '--color-accent-subtle',
        '--color-accent-border', '--color-accent-foreground', '--color-focus-ring', '--color-selected-background',
    ];
    assert.equal(APPEARANCE_ACCENTS.length, 4);
    for (const { value } of APPEARANCE_ACCENTS) {
        const tokens = appearanceAccentTokens(value);
        for (const token of required) assert.ok(tokens[token], `${value} should define ${token}`);
        assert.equal(tokens['--color-accent'], value);
    }
    for (const { value } of APPEARANCE_ACCENTS) {
        assert.equal(appearanceAccentTokens(value)['--color-accent-foreground'], '#ffffff');
        assert.equal(appearanceAccentTokens(value, 'dark')['--color-accent'], value);
        assert.equal(appearanceAccentTokens(value, 'dark')['--color-accent-foreground'], '#ffffff');
        assert.notEqual(appearanceAccentTokens(value, 'dark')['--color-accent-subtle'], appearanceAccentTokens(value, 'light')['--color-accent-subtle']);
    }
});

test('ending an appearance session restores Light without deleting account preferences', () => {
    initializeAppearancePreferences(41);
    resetAppearanceSession();
    assert.equal(document.documentElement.dataset.theme, 'light');
    assert.deepEqual(JSON.parse(values.get('planora.appearance.v1.41')), { theme: 'dark', accent: '#0F766E', density: 'compact', sidebar: 'expanded' });
});

test('guest theme persists separately and never replaces an authenticated account theme', () => {
    resetAppearanceSession();
    assert.equal(getGuestTheme(), 'light');
    assert.equal(setGuestTheme('dark'), true);
    assert.equal(document.documentElement.dataset.theme, 'dark');
    assert.equal(values.get('planora.guest-theme.v1'), 'dark');
    assert.match(document.cookie, /planora_guest_theme=dark/);

    const account = useAppearancePreferences();
    account.load(41);
    assert.equal(account.preferences.theme, 'dark');
    assert.equal(setGuestTheme('light'), false);
    assert.deepEqual(JSON.parse(values.get('planora.appearance.v1.41')), { theme: 'dark', accent: '#0F766E', density: 'compact', sidebar: 'expanded' });

    resetAppearanceSession();
    assert.equal(document.documentElement.dataset.theme, 'dark');
});

test('public theme selector is keyboard-accessible and server shells initialize guest theme before app styles', async () => {
    const navbar = await readFile(new URL('../../resources/js/shared/components/public/PublicNavbar.vue', import.meta.url), 'utf8');
    const authenticatedHome = await readFile(new URL('../../resources/js/shared/components/public/AuthenticatedHome.vue', import.meta.url), 'utf8');
    const themeSelector = await readFile(new URL('../../resources/js/shared/components/public/ThemeSelector.vue', import.meta.url), 'utf8');
    const shell = await readFile(new URL('../../resources/views/welcome.blade.php', import.meta.url), 'utf8');
    const errorShell = await readFile(new URL('../../resources/views/errors/page.blade.php', import.meta.url), 'utf8');
    const css = await readFile(new URL('../../resources/css/pages/public/home.css', import.meta.url), 'utf8');
    assert.match(navbar, /<ThemeSelector :theme="theme" @select="selectTheme" \/>/);
    assert.match(authenticatedHome, /<ThemeSelector :theme="preferences\.theme" @select="selectTheme" \/>/);
    assert.match(authenticatedHome, /preferences\.theme = theme;\s+save\(\);/);
    assert.match(themeSelector, /role="group" aria-label="Choose color theme"/);
    assert.match(themeSelector, /aria-label="Light mode"/);
    assert.match(themeSelector, /aria-label="Dark mode"/);
    assert.match(shell, /planora\.guest-theme\.v1/);
    assert.match(shell, /document\.documentElement\.dataset\.theme = theme/);
    assert.match(errorShell, /request\(\)->cookie\('planora_guest_theme'\)/);
    assert.match(errorShell, /:root\[data-theme="dark"\]/);
    assert.match(css, /\.public-theme-option:focus-visible/);
});

test('Appearance primary action keeps a white label in every interactive state', async () => {
    const css = await readFile(new URL('../../resources/css/pages/app/appearance.css', import.meta.url), 'utf8');
    const stateRule = css.match(/\.appearance-page \.appearance-button\.appearance-button--primary,[\s\S]*?\.appearance-page \.appearance-button\.appearance-button--primary:disabled \{ color: #fff; \}/);
    assert.ok(stateRule, 'a specific white-text rule should cover the primary button states');
    for (const state of [':hover', ':active', ':focus-visible', ':disabled']) assert.ok(stateRule[0].includes(`.appearance-button--primary${state}`));
});

test('appearance storage rejects unsupported preference values', () => {
    values.set('planora.appearance.v1.99', JSON.stringify({ theme: 'ultraviolet', accent: '#000000', density: 'tiny', sidebar: 'hidden' }));
    const account = useAppearancePreferences();
    account.load(99);
    assert.deepEqual({ ...account.preferences }, APPEARANCE_DEFAULTS);
});
