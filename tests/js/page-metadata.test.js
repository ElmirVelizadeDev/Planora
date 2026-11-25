import assert from 'node:assert/strict';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';
import { applyPageMetadata } from '../../resources/js/router/pageMetadata.js';
import { readFileSync } from 'node:fs';

class FakeElement {
    constructor(tagName) { this.tagName = tagName; this.attributes = {}; this.parent = null; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] ?? null; }
    remove() { if (this.parent) this.parent.elements = this.parent.elements.filter((element) => element !== this); }
}

class FakeHead {
    constructor() { this.elements = []; }
    appendChild(element) { element.parent = this; this.elements.push(element); return element; }
    querySelector(selector) {
        const match = selector.match(/^(meta|link)\[(name|property|rel)="([^"]+)"\]$/);
        if (!match) return null;
        return this.elements.find((element) => element.tagName === match[1] && element.getAttribute(match[2]) === match[3]) || null;
    }
}

function makeDocument() {
    return { title: '', head: new FakeHead(), createElement: (tagName) => new FakeElement(tagName) };
}

const publicRoute = (name) => routes.flatMap((route) => route.children || [route]).find((route) => route.name === name);

test('Home metadata has a static title, description, social cards, and canonical for the active origin', () => {
    const documentRef = makeDocument();
    applyPageMetadata({ ...publicRoute('home'), path: '/' }, documentRef, { origin: 'https://planora.example' });
    assert.equal(documentRef.title, 'Planora — Plan tasks and projects');
    assert.equal(documentRef.head.querySelector('meta[name="description"]').getAttribute('content').includes('projects'), true);
    assert.equal(documentRef.head.querySelector('meta[property="og:title"]').getAttribute('content'), documentRef.title);
    assert.equal(documentRef.head.querySelector('meta[name="twitter:card"]').getAttribute('content'), 'summary');
    assert.equal(documentRef.head.querySelector('meta[name="robots"]').getAttribute('content'), 'index, follow');
    assert.equal(documentRef.head.querySelector('link[rel="canonical"]').getAttribute('href'), 'https://planora.example/');
});

test('metadata updates on client navigation and removes canonical on authentication pages', () => {
    const documentRef = makeDocument();
    const origin = { origin: 'https://planora.example' };
    applyPageMetadata({ ...publicRoute('privacy-policy'), path: '/privacy-policy' }, documentRef, origin);
    assert.equal(documentRef.title, 'Privacy Policy — Planora');
    assert.equal(documentRef.head.querySelector('link[rel="canonical"]').getAttribute('href'), 'https://planora.example/privacy-policy');

    applyPageMetadata({ ...publicRoute('login'), path: '/login' }, documentRef, origin);
    assert.equal(documentRef.title, 'Sign in — Planora');
    assert.equal(documentRef.head.querySelector('meta[name="robots"]').getAttribute('content'), 'noindex, nofollow');
    assert.equal(documentRef.head.querySelector('link[rel="canonical"]'), null);
    assert.equal(documentRef.head.querySelector('meta[property="og:url"]'), null);
});

test('private or unknown routes default to noindex and metadata contains no user-specific state', () => {
    const documentRef = makeDocument();
    applyPageMetadata({ path: '/profile', meta: {} }, documentRef, { origin: 'https://planora.example' });
    assert.equal(documentRef.head.querySelector('meta[name="robots"]').getAttribute('content'), 'noindex, nofollow');
    assert.equal(documentRef.head.querySelector('link[rel="canonical"]'), null);
    assert.equal(documentRef.head.querySelector('meta[property="og:description"]').getAttribute('content').includes('user'), false);
});

test('Laravel shell starts with path-specific public metadata and noindex defaults for other paths', () => {
    const shell = readFileSync(new URL('../../resources/views/welcome.blade.php', import.meta.url), 'utf8');
    assert.match(shell, /'terms-and-conditions'\s*=>\s*\[/);
    assert.match(shell, /'privacy-policy'\s*=>\s*\[/);
    assert.match(shell, /'robots'\s*=>\s*'noindex, nofollow'/);
    assert.match(shell, /<title>\{\{ \$initialMetadata\['title'\] \}\}<\/title>/);
    assert.match(shell, /og:image/);
    assert.match(shell, /twitter:image/);
});
