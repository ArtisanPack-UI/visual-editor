import { describe, expect, it } from 'vitest';
import { safeUrl } from '../src/support/urlSanitizer';

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
    });

    it('drops javascript, data, and vbscript schemes', () => {
        expect(safeUrl('javascript:alert(1)')).toBe('');
        expect(safeUrl('JAVASCRIPT:alert(1)')).toBe('');
        expect(safeUrl('data:text/html,abc')).toBe('');
        expect(safeUrl('vbscript:msgbox')).toBe('');
    });
});
