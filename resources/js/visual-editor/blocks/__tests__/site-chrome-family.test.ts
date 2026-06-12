/**
 * Site-chrome cluster contract (#500).
 *
 * block.json + save contract for the three site-wide chrome blocks:
 *
 *   - copyright (dynamic — save returns null)
 *   - marquee (static — save returns the wrapper markup so
 *     `source: 'html', selector: 'p'` round-trips through Gutenberg)
 *   - comments-number (dynamic — save returns null)
 *
 * All three live under the `artisanpack` namespace + category and the
 * `artisanpack-visual-editor` textdomain.
 */

import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: Object.assign(() => null, { Content: () => null }),
    RichText: Object.assign(() => null, { Content: () => null }),
    useBlockProps: Object.assign(() => ({}), { save: () => ({}) }),
}));

import copyrightMeta from '../copyright/block.json';
import marqueeMeta from '../marquee/block.json';
import commentsNumberMeta from '../comments-number/block.json';

import copyrightSave from '../copyright/save';
import marqueeSave from '../marquee/save';
import commentsNumberSave from '../comments-number/save';

const FAMILY = [
    { slug: 'copyright', meta: copyrightMeta },
    { slug: 'marquee', meta: marqueeMeta },
    { slug: 'comments-number', meta: commentsNumberMeta },
] as const;

describe('site-chrome cluster block.json', () => {
    it.each(FAMILY)(
        '$slug declares the artisanpack namespace + textdomain + category',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            expect(meta.category).toBe('artisanpack');
        }
    );

    it('copyright declares copyrightType + copyrightText attributes with safe defaults', () => {
        const attrs = (copyrightMeta as { attributes?: Record<string, { default?: unknown; enum?: unknown }> })
            .attributes;
        expect(attrs?.copyrightType?.default).toBe('icon-text');
        expect(attrs?.copyrightType?.enum).toEqual([
            'icon-text',
            'icon-only',
            'text-only',
        ]);
        expect(attrs?.copyrightText?.default).toBe('Copyright');
    });

    it('marquee declares the marqueeContent / width / speed attributes with safe defaults', () => {
        const attrs = (marqueeMeta as {
            attributes?: Record<string, { default?: unknown; source?: unknown; selector?: unknown }>;
        }).attributes;
        expect(attrs?.marqueeWidth?.default).toBe(100);
        expect(attrs?.marqueeSpeed?.default).toBe(5);
        expect(attrs?.marqueeContent?.default).toBe('');
        expect(attrs?.marqueeContent?.source).toBe('html');
        expect(attrs?.marqueeContent?.selector).toBe('p');
    });

    it('comments-number declares singular + plural labels and the postId context', () => {
        const attrs = (commentsNumberMeta as { attributes?: Record<string, { default?: unknown }> })
            .attributes;
        expect(attrs?.singularCommentText?.default).toBe('Comment');
        expect(attrs?.pluralCommentText?.default).toBe('Comments');

        const usesContext = (commentsNumberMeta as { usesContext?: ReadonlyArray<string> })
            .usesContext;
        expect(usesContext).toContain('postId');
    });
});

describe('site-chrome cluster save contract', () => {
    it('copyright.save returns null (dynamic block)', () => {
        expect((copyrightSave as () => null)()).toBeNull();
    });

    it('comments-number.save returns null (dynamic block)', () => {
        expect((commentsNumberSave as () => null)()).toBeNull();
    });

    it('marquee.save returns a non-null element (static block)', () => {
        // The static save renders the wrapper + RichText so the
        // `source: 'html', selector: 'p'` parser round-trips the
        // saved content. The element factory returns a React element,
        // not null — that's the contract for static blocks.
        const element = (marqueeSave as (props: {
            attributes: {
                marqueeContent: string;
                marqueeWidth: number;
                marqueeSpeed: number;
            };
        }) => unknown)({
            attributes: {
                marqueeContent: 'Breaking news',
                marqueeWidth: 50,
                marqueeSpeed: 10,
            },
        });
        expect(element).not.toBeNull();
    });
});
