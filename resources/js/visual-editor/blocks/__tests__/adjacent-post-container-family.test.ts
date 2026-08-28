/**
 * Adjacent-post container family contract (#499).
 *
 * block.json + save contract for the two wrapper blocks:
 *
 *   - next-post
 *   - previous-post
 *
 * Both blocks share the same shape: they host inner blocks inside a
 * wrapper `<div>`, so `save()` reproduces that wrapper (carrying the
 * block's `wp-block-artisanpack-{slug} navigation-post` className) and
 * surfaces the inner tree via `useInnerBlocksProps.save`, matching the
 * markup the theme's `single-post` template persists (#747), `theme`
 * category, `artisanpack` namespace + `artisanpack-visual-editor`
 * textdomain, and a `postType` / `queryId` providesContext map so
 * inner post-* children render against the resolved adjacent post
 * upstream.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(() => ({}), {
        save: (props: { className?: string }) => ({ ...props }),
    }),
    useInnerBlocksProps: Object.assign(() => ({}), {
        save: (blockProps: Record<string, unknown>) => ({ ...blockProps }),
    }),
    InnerBlocks: Object.assign(() => null, { Content: () => null }),
}));

import nextPostMeta from '../next-post/block.json';
import previousPostMeta from '../previous-post/block.json';

import nextPostSave from '../next-post/save';
import previousPostSave from '../previous-post/save';

const FAMILY = [
    {
        slug: 'next-post',
        meta: nextPostMeta,
        save: nextPostSave,
        className: 'wp-block-artisanpack-next-post navigation-post',
    },
    {
        slug: 'previous-post',
        meta: previousPostMeta,
        save: previousPostSave,
        className: 'wp-block-artisanpack-previous-post navigation-post',
    },
] as const;

describe('adjacent-post container family block.json', () => {
    it.each(FAMILY)(
        '$slug declares the artisanpack namespace + textdomain',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            expect(meta.category).toBe('theme');
        }
    );

    it.each(FAMILY)(
        '$slug provides postType / queryId context for inner post-* children',
        ({ meta }) => {
            const providesContext = (meta as { providesContext?: Record<string, string> })
                .providesContext;
            expect(providesContext).toBeDefined();
            expect(providesContext?.postType).toBe('postType');
            expect(providesContext?.queryId).toBe('queryId');
        }
    );

    it.each(FAMILY)('$slug declares a default postType of "post"', ({ meta }) => {
        const attrs = (meta as { attributes?: Record<string, { default?: unknown }> })
            .attributes;
        expect(attrs?.postType?.default).toBe('post');
    });
});

describe('adjacent-post container family save contract', () => {
    it.each(FAMILY)(
        '$slug.save reproduces the wrapper div carrying its block className (#747)',
        ({ save, className }) => {
            const element = save();
            expect(element).not.toBeNull();
            expect(element.type).toBe('div');
            expect(element.props.className).toBe(className);
        }
    );
});
