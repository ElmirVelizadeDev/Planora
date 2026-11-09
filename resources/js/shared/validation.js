export const supportedEmailDomains = [
    'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com',
    'yahoo.com', 'yahoo.co.uk', 'icloud.com', 'me.com', 'mac.com',
    'proton.me', 'protonmail.com', 'gmx.com', 'gmx.de', 'zoho.com',
    'yandex.com', 'yandex.ru',
];

export function normalizePersonName(value) {
    return value.replace(/\s+/gu, ' ').trim();
}

export function validatePersonName(value) {
    const normalized = normalizePersonName(value);
    if (Array.from(normalized).length < 2 || Array.from(normalized).length > 50) {
        return 'Name must be between 2 and 50 characters.';
    }
    if (!/^[\p{L}]+(?:[ '-][\p{L}]+)*$/u.test(normalized)) {
        return 'Use letters, spaces, hyphens, or apostrophes only.';
    }
    return '';
}

export function validateSupportedEmail(value) {
    const email = value.trim().toLowerCase();
    const at = email.lastIndexOf('@');
    const local = email.slice(0, at);
    const domain = email.slice(at + 1);
    if (email.length > 255 || !local || local.length > 64 || !/^[a-z0-9.!#$%&'*+\-/=?^_`{|}~]+$/i.test(local)
        || local.startsWith('.') || local.endsWith('.') || local.includes('..')
        || !supportedEmailDomains.includes(domain)) {
        return 'Enter a valid email from Gmail, Outlook, Hotmail, Live, Yahoo, iCloud, Proton, GMX, Zoho, or Yandex.';
    }
    return '';
}
