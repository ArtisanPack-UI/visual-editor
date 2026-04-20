/**
 * URL sanitization helpers.
 *
 * Mirrors the PHP `UrlSanitizer::safe()` used by the Blade renderer: relative
 * URLs pass through, absolute URLs must use one of the safe schemes, and
 * anything else (e.g. `javascript:`, `data:`, `vbscript:`) is dropped so a
 * stored block tree can't smuggle script execution into the rendered page.
 */

const SAFE_SCHEMES: ReadonlyArray<string> = [
    'http',
    'https',
    'mailto',
    'tel',
    'ftp',
    'sms',
];

const SCHEME_PATTERN = /^([a-z][a-z0-9+\-.]*):/i;

export function safeUrl(url: unknown): string {
    if (typeof url !== 'string') {
        return '';
    }

    const trimmed = url.trim();

    if (trimmed === '') {
        return '';
    }

    const match = SCHEME_PATTERN.exec(trimmed);

    if (match === null) {
        return trimmed;
    }

    return SAFE_SCHEMES.includes(match[1].toLowerCase()) ? trimmed : '';
}
