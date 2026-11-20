import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('header search uses one custom clear control and suppresses native search decorations', () => {
    const component = source('../../resources/js/shared/components/layout/SearchBar.vue');
    const styles = source('../../resources/css/components/WorkspaceHeader.css');

    assert.equal((component.match(/class="workspace-search-clear"/g) || []).length, 1);
    assert.match(component, /<button v-if="query" class="workspace-search-clear"/);
    assert.match(styles, /input\[type="search"\]\s*\{[^}]*appearance:\s*none/s);
    assert.match(styles, /::-webkit-search-cancel-button[^}]*display:\s*none/s);
    assert.match(styles, /::-ms-clear\s*\{\s*display:\s*none/s);
});

test('clearing header search resets query state and restores input focus', () => {
    const component = source('../../resources/js/shared/components/layout/SearchBar.vue');

    assert.match(component, /function clearSearch\(\)\s*\{[\s\S]*?query\.value = '';[\s\S]*?resetResults\(\);[\s\S]*?openSearch\(\);\s*\}/);
    assert.match(component, /focusTimer = window\.requestAnimationFrame\(\(\) => inputRef\.value\?\.focus\(\)\)/);
});

test('mobile search dismissal remains available without rendering a second X icon', () => {
    const component = source('../../resources/js/shared/components/layout/SearchBar.vue');

    assert.match(component, /class="workspace-search-close" type="button" aria-label="Close search" @click="closeSearch\(true\)"/);
    assert.match(component, /<path d="m6 9 6 6 6-6" \/>/);
});
