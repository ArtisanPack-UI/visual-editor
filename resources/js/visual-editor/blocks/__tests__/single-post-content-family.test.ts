/**
 * Single-post content cluster contract (#501).
 *
 * block.json + save contract for the four blocks that compose the
 * single-post content family:
 *
 *   - single-content       (dynamic — save returns null)
 *   - related-posts        (dynamic — save returns null)
 *   - author-social-icons  (dynamic — save returns null)
 *   - social-share-content (dynamic — save returns null)
 *
 * All four live under the `artisanpack` namespace + category and the
 * `artisanpack-visual-editor` textdomain.
 */

import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: Object.assign(() => null, { Content: () => null }),
    RichText: Object.assign(() => null, { Content: () => null }),
    useBlockProps: Object.assign(() => ({}), { save: () => ({}) }),
}));

import singleContentMeta from '../single-content/block.json';
import relatedPostsMeta from '../related-posts/block.json';
import authorSocialIconsMeta from '../author-social-icons/block.json';
import socialShareContentMeta from '../social-share-content/block.json';

import singleContentSave from '../single-content/save';
import relatedPostsSave from '../related-posts/save';
import authorSocialIconsSave from '../author-social-icons/save';
import socialShareContentSave from '../social-share-content/save';

const FAMILY = [
    { slug: 'single-content', meta: singleContentMeta },
    { slug: 'related-posts', meta: relatedPostsMeta },
    { slug: 'author-social-icons', meta: authorSocialIconsMeta },
    { slug: 'social-share-content', meta: socialShareContentMeta },
] as const;

describe('single-post content cluster block.json', () => {
    it.each(FAMILY)(
        '$slug declares the artisanpack namespace + textdomain + category',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            expect(meta.category).toBe('artisanpack');
        }
    );

    it('single-content declares postId + postType attributes with safe defaults', () => {
        const attrs = (singleContentMeta as {
            attributes?: Record<string, { default?: unknown }>;
        }).attributes;
        expect(attrs?.postId?.default).toBe(0);
        expect(attrs?.postType?.default).toBe('post');
    });

    it('single-content provides postId + postType to inner blocks', () => {
        const providesContext = (singleContentMeta as {
            providesContext?: Record<string, string>;
        }).providesContext;
        expect(providesContext?.postId).toBe('postId');
        expect(providesContext?.postType).toBe('postType');
    });

    it('related-posts declares numPosts + numColumns attributes with safe defaults', () => {
        const attrs = (relatedPostsMeta as {
            attributes?: Record<string, { default?: unknown }>;
        }).attributes;
        expect(attrs?.numPosts?.default).toBe(3);
        expect(attrs?.numColumns?.default).toBe(1);
    });

    it('author-social-icons declares socialIcons + iconStyle + layout attrs', () => {
        const attrs = (authorSocialIconsMeta as {
            attributes?: Record<
                string,
                { default?: unknown; enum?: ReadonlyArray<string> }
            >;
        }).attributes;
        expect(attrs?.socialIcons?.default).toEqual([]);
        expect(attrs?.iconStyle?.default).toBe('show-label-icon');
        expect(attrs?.iconStyle?.enum).toEqual([
            'show-label-icon',
            'show-icon',
            'show-label',
        ]);
        expect(attrs?.iconsDirection?.default).toBe('vertical');
        expect(attrs?.iconsStretch?.default).toBe('full-width');
        expect(attrs?.iconsBorderRadius?.default).toBe(0);
    });

    it('social-share-content declares socialIcons + iconStyle + layout attrs', () => {
        const attrs = (socialShareContentMeta as {
            attributes?: Record<
                string,
                { default?: unknown; enum?: ReadonlyArray<string> }
            >;
        }).attributes;
        expect(attrs?.socialIcons?.default).toEqual([]);
        expect(attrs?.iconStyle?.default).toBe('show-label-icon');
        expect(attrs?.iconStyle?.enum).toEqual([
            'show-label-icon',
            'show-icon',
            'show-label',
        ]);
        expect(attrs?.iconsDirection?.default).toBe('vertical');
        expect(attrs?.iconsStretch?.default).toBe('full-width');
        expect(attrs?.iconsBorderRadius?.default).toBe(0);
    });
});

describe('single-post content cluster save contract', () => {
    it.each([
        ['single-content', singleContentSave],
        ['related-posts', relatedPostsSave],
        ['author-social-icons', authorSocialIconsSave],
        ['social-share-content', socialShareContentSave],
    ] as const)('%s save returns null (dynamic block)', (_slug, save) => {
        expect((save as () => null)()).toBeNull();
    });
});
