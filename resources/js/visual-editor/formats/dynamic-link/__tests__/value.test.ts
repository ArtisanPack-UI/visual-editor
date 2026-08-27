/**
 * Dynamic-link value helpers — unit test (#662).
 *
 * @since 1.7.0
 */

import { describe, expect, it } from 'vitest';

import { activeLinkRange, buildLinkFormat, type RichTextValueLike } from '../value';

describe('buildLinkFormat', () => {
    it('emits a bare core/link format for a same-tab link', () => {
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
});
