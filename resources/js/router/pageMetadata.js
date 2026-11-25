const DEFAULT_TITLE = 'Planora — Plan tasks and projects';
const DEFAULT_DESCRIPTION = 'Planora helps you organize tasks, projects, categories, priorities, and progress in one workspace.';
const SOCIAL_IMAGE_PATH = '/images/branding/planora-master-512.png';

function setMeta(documentRef, attribute, key, content) {
    let element = documentRef.head.querySelector(`meta[${attribute}="${key}"]`);
    if (!element) {
        element = documentRef.createElement('meta');
        element.setAttribute(attribute, key);
        documentRef.head.appendChild(element);
    }
    element.setAttribute('content', content);
}

export function applyPageMetadata(route, documentRef = globalThis.document, locationRef = globalThis.location) {
    if (!documentRef?.head) return;
    const meta = route?.meta || {};
    const title = typeof meta.pageTitle === 'string' ? meta.pageTitle : DEFAULT_TITLE;
    const description = typeof meta.pageDescription === 'string' ? meta.pageDescription : DEFAULT_DESCRIPTION;
    const noIndex = meta.noIndex === true || meta.publicIndex !== true;

    documentRef.title = title;
    setMeta(documentRef, 'name', 'description', description);
    setMeta(documentRef, 'name', 'robots', noIndex ? 'noindex, nofollow' : 'index, follow');
    setMeta(documentRef, 'property', 'og:type', 'website');
    setMeta(documentRef, 'property', 'og:title', title);
    setMeta(documentRef, 'property', 'og:description', description);
    setMeta(documentRef, 'property', 'og:image', new URL(SOCIAL_IMAGE_PATH, locationRef?.origin || 'http://localhost').href);
    setMeta(documentRef, 'name', 'twitter:card', 'summary');
    setMeta(documentRef, 'name', 'twitter:title', title);
    setMeta(documentRef, 'name', 'twitter:description', description);
    setMeta(documentRef, 'name', 'twitter:image', new URL(SOCIAL_IMAGE_PATH, locationRef?.origin || 'http://localhost').href);

    const canonical = documentRef.head.querySelector('link[rel="canonical"]');
    if (meta.canonical === true && locationRef?.origin && typeof route?.path === 'string') {
        const link = canonical || documentRef.createElement('link');
        link.setAttribute('rel', 'canonical');
        link.setAttribute('href', new URL(route.path, locationRef.origin).href);
        if (!canonical) documentRef.head.appendChild(link);
        setMeta(documentRef, 'property', 'og:url', new URL(route.path, locationRef.origin).href);
    } else {
        canonical?.remove();
        documentRef.head.querySelector('meta[property="og:url"]')?.remove();
    }
}
