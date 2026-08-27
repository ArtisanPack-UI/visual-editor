/**
 * Dynamic-link value helpers — unit test (#662).
 *
 * @since 1.7.0
 */

import { describe, expect, it } from 'vitest';

import { activeLinkRange, buildLinkFormat, normalizeLinkUrl, type RichTextValueLike } from '../value';

describe('normalizeLinkUrl', () => {
    it('prepends https:// to a bare host', () => {
        expect(normalizeLinkUrl('example.com')).toBe('https://example.com');
        expect(normalizeLinkUrl('  example.com  ')).toBe('https://example.com');
    });

    it('leaves already-usable hrefs untouched', () => {
        expect(normalizeLinkUrl('https://x.test')).toBe('https://x.test');
        expect(normalizeLinkUrl('http://x.test')).toBe('http://x.test');
        expect(normalizeLinkUrl('#anchor')).toBe('#anchor');
        expect(normalizeLinkUrl('/relative/path')).toBe('/relative/path');
        expect(normalizeLinkUrl('foo@bar.com')).toBe('foo@bar.com');
        expect(normalizeLinkUrl('')).toBe('');
    });

    it('preserves Dynamic Content tokens (bare and scheme-prefixed)', () => {
        expect(normalizeLinkUrl('{{business_info.website}}')).toBe('{{business_info.website}}');
        expect(normalizeLinkUrl('mailto:{{business_info.email}}')).toBe(
            'mailto:{{business_info.email}}'
        );
        expect(normalizeLinkUrl('tel:{{business_info.phone}}')).toBe('tel:{{business_info.phone}}');
    });
});

describe('buildLinkFormat', () => {
    it('emits a bare core/link format for a same-tab link, normalizing the URL', () => {
        expect(buildLinkFormat({ url: 'example.com' })).toEqual({
            type: 'core/link',
            attributes: { url: 'https://example.com' },
        });
    });

    it('preserves a token URL', () => {
        expect(buildLinkFormat({ url: '{{business_info.website}}' })).toEqual({
            type: 'core/link',
            attributes: { url: '{{business_info.website}}' },
        });
    });

    it('adds target/rel for an open-in-new-tab link', () => {
        expect(buildLinkFormat({ url: 'mailto:{{business_info.email}}', opensInNewTab: true })).toEqual({
            type: 'core/link',
            attributes: {
                url: 'mailto:{{business_info.email}}',
                target: '_blank',
                rel: 'noreferrer noopener',
            },
        });
    });
});

describe('activeLinkRange', () => {
    const link = [{ type: 'core/link', attributes: { url: '#' } }];

    /** Build a value whose characters 1..3 carry a core/link format. */
    function value(start: number): RichTextValueLike {
        return {
            text: 'abcde',
            start,
            end: start,
            formats: [undefined, link, link, link, undefined],
        };
    }

    it('finds the contiguous link run when the caret is inside it', () => {
        expect(activeLinkRange(value(2))).toEqual([1, 4]);
    });

    it('finds the run when the caret sits at the link end boundary', () => {
        // caret at index 4 → probes the char to its left (index 3).
        expect(activeLinkRange(value(4))).toEqual([1, 4]);
    });

    it('returns null when the caret is off any link', () => {
        expect(activeLinkRange(value(0))).toBeNull();
        expect(activeLinkRange(value(5))).toBeNull();
    });

    it('does not merge two adjacent links with different URLs', () => {
        const linkA = [{ type: 'core/link', attributes: { url: 'https://a.test' } }];
        const linkB = [{ type: 'core/link', attributes: { url: 'https://b.test' } }];
        // 'abcdef': chars 1-2 = link A, chars 3-4 = link B.
        const val = (start: number): RichTextValueLike => ({
            text: 'abcdef',
            start,
            end: start,
            formats: [undefined, linkA, linkA, linkB, linkB, undefined],
        });

        // Caret inside A stays within A's run.
        expect(activeLinkRange(val(2))).toEqual([1, 3]);
        // Caret inside B stays within B's run.
        expect(activeLinkRange(val(4))).toEqual([3, 5]);
        // Caret on the A|B boundary targets the run to the right (B).
        expect(activeLinkRange(val(3))).toEqual([3, 5]);
    });
});
