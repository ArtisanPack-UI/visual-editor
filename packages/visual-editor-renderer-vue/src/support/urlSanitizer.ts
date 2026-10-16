/**
 * URL sanitization helpers.
 *
 * Mirrors the PHP `UrlSanitizer::safe()` used by the Blade renderer byte for
 * byte: relative URLs pass through, absolute URLs must use one of the safe
 * schemes, and anything else (e.g. `javascript:`, `data:`, `vbscript:`) is
 * dropped so a stored block tree can't smuggle script execution into the
 * rendered page.
 *
 * The scheme is read the way a browser's URL parser reads it (WHATWG URL):
 * ASCII tab and newline characters are removed anywhere, leading C0 controls
 * and spaces are skipped, and a scheme is an ASCII letter followed by letters,
 * digits, `+`, `-` or `.`, then `:`. The returned URL is trimmed with PHP's
 * `trim()` set (`phpTrim`), not `String.prototype.trim()`, so both renderers
 * keep the same value. The shared cases live in
 * `packages/renderer-markup-parity/url-sanitizer-cases.json`.
 */

const SAFE_SCHEMES: ReadonlyArray<string> = [
    'http',
    'https',
    'mailto',
    'tel',
    'ftp',
    'sms',
];

const SCHEME_PATTERN = /^([A-Za-z][A-Za-z0-9+.-]*):/;

/**
 * PHP `trim()`'s default character set: space, tab, newline, carriage
 * return, NUL and vertical tab.
 */
function phpTrim(value: string): string {
    return value.replace(/^[ \t\n\r\0\x0B]+/, '').replace(/[ \t\n\r\0\x0B]+$/, '');
}

/**
 * The lower-cased scheme a browser would read from `url`, or `null` for a
 * relative URL. Mirrors PHP `UrlSanitizer::scheme()`.
 *
 * @since 1.15.0
 */
export function urlScheme(url: string): string | null {
    // eslint-disable-next-line no-control-regex
    const probe = url.replace(/[\t\n\r]/g, '').replace(/^[\x00-\x20]+/, '');
    const match = SCHEME_PATTERN.exec(probe);

    return match === null ? null : match[1].toLowerCase();
}

export function safeUrl(url: unknown): string {
    if (typeof url !== 'string') {
        return '';
    }

    const trimmed = phpTrim(url);

    if (trimmed === '') {
        return '';
    }

    const scheme = urlScheme(trimmed);

    if (scheme === null) {
        return trimmed;
    }

    return SAFE_SCHEMES.includes(scheme) ? trimmed : '';
}
