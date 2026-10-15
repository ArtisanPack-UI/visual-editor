import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { safeUrl } from '../src/support/urlSanitizer';
import { safeUrl as vueSafeUrl } from '../../visual-editor-renderer-vue/src/support/urlSanitizer';

describe('safeUrl', () => {
    it('returns an empty string for non-string input', () => {
        expect(safeUrl(42)).toBe('');
        expect(safeUrl(null)).toBe('');
        expect(safeUrl(undefined)).toBe('');
        expect(safeUrl({})).toBe('');
    });

    it('returns an empty string for blank input', () => {
        expect(safeUrl('')).toBe('');
        expect(safeUrl('   ')).toBe('');
    });

    it('passes relative URLs through unchanged', () => {
        expect(safeUrl('/page')).toBe('/page');
        expect(safeUrl('foo/bar')).toBe('foo/bar');
        expect(safeUrl('#anchor')).toBe('#anchor');
    });

    it('allows http, https, mailto, tel, ftp, and sms schemes', () => {
        expect(safeUrl('https://example.test')).toBe('https://example.test');
        expect(safeUrl('http://example.test')).toBe('http://example.test');
        expect(safeUrl('mailto:me@example.test')).toBe('mailto:me@example.test');
        expect(safeUrl('tel:+15555551212')).toBe('tel:+15555551212');
        expect(safeUrl('ftp://example.test/file.zip')).toBe('ftp://example.test/file.zip');
        expect(safeUrl('sms:+15555551212')).toBe('sms:+15555551212');
    });

    it('drops javascript, data, and vbscript schemes', () => {
        expect(safeUrl('javascript:alert(1)')).toBe('');
        expect(safeUrl('JAVASCRIPT:alert(1)')).toBe('');
        expect(safeUrl('data:text/html,abc')).toBe('');
        expect(safeUrl('vbscript:msgbox')).toBe('');
    });
});

/*
 * Shared with the Blade `UrlSanitizerTest`: the expected values are what
 * PHP `UrlSanitizer::safe()` returns, so the twins can't drift. React and
 * Vue ship byte-identical copies; both are exercised.
 */
const sharedCases: Array<[string, string]> = JSON.parse(
    readFileSync(
        resolve(dirname(fileURLToPath(import.meta.url)), '../../renderer-markup-parity/url-sanitizer-cases.json'),
        'utf8'
    )
).cases;

describe.each([
    ['react', safeUrl],
    ['vue', vueSafeUrl],
])('%s safeUrl matches PHP UrlSanitizer::safe()', (_renderer, sanitize) => {
    it.each(sharedCases)('%j → %j', (input, expected) => {
        expect(sanitize(input)).toBe(expected);
    });
});
