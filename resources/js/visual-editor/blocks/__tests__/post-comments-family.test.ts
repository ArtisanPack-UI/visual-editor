/**
 * Comments family Pass 2 (#519) — block.json + save + transforms contract.
 *
 * Covers the 8 Pass 2 forks (post-level comment metadata +
 * pagination cluster) in one parametrized suite: namespace +
 * textdomain on block.json, the dynamic (`null`) save for the 6
 * display blocks + post-comments-form, the delegating
 * (`InnerBlocks.Content`) save for the comments-pagination
 * wrapper, the bidirectional `core/* ↔ artisanpack/*` transforms,
 * and the wrapper innerBlocks-preservation contract.
 */

import { describe, it, expect, vi } from 'vitest';
import type { ReactElement } from 'react';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (
        name: string,
        attributes?: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: innerBlocks ?? [],
    }),
}));

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: Object.assign(
        () => null,
        { Content: () => null }
    ),
}));

import postCommentsFormMeta from '../post-comments-form/block.json';
import postCommentsCountMeta from '../post-comments-count/block.json';
import postCommentsLinkMeta from '../post-comments-link/block.json';
import postCommentsTitleMeta from '../post-comments-title/block.json';
import commentsPaginationMeta from '../comments-pagination/block.json';
import commentsPaginationNextMeta from '../comments-pagination-next/block.json';
import commentsPaginationNumbersMeta from '../comments-pagination-numbers/block.json';
import commentsPaginationPreviousMeta from '../comments-pagination-previous/block.json';

import postCommentsFormTransforms from '../post-comments-form/transforms';
import postCommentsCountTransforms from '../post-comments-count/transforms';
import postCommentsLinkTransforms from '../post-comments-link/transforms';
import postCommentsTitleTransforms from '../post-comments-title/transforms';
import commentsPaginationTransforms from '../comments-pagination/transforms';
import commentsPaginationNextTransforms from '../comments-pagination-next/transforms';
import commentsPaginationNumbersTransforms from '../comments-pagination-numbers/transforms';
import commentsPaginationPreviousTransforms from '../comments-pagination-previous/transforms';

import postCommentsFormSave from '../post-comments-form/save';
import postCommentsCountSave from '../post-comments-count/save';
import postCommentsLinkSave from '../post-comments-link/save';
import postCommentsTitleSave from '../post-comments-title/save';
import commentsPaginationSave from '../comments-pagination/save';
import commentsPaginationNextSave from '../comments-pagination-next/save';
import commentsPaginationNumbersSave from '../comments-pagination-numbers/save';
import commentsPaginationPreviousSave from '../comments-pagination-previous/save';

interface BlockTransform {
    type: string;
    blocks: string[];
    transform: (
        attrs: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => { name: string; attributes: Record<string, unknown>; innerBlocks: unknown[] };
}
interface TransformsModule {
    from: BlockTransform[];
    to: BlockTransform[];
}

const PASS_2_BLOCKS = [
    { slug: 'post-comments-form', meta: postCommentsFormMeta, transforms: postCommentsFormTransforms },
    { slug: 'post-comments-count', meta: postCommentsCountMeta, transforms: postCommentsCountTransforms },
    { slug: 'post-comments-link', meta: postCommentsLinkMeta, transforms: postCommentsLinkTransforms },
    { slug: 'post-comments-title', meta: postCommentsTitleMeta, transforms: postCommentsTitleTransforms },
    { slug: 'comments-pagination', meta: commentsPaginationMeta, transforms: commentsPaginationTransforms },
    { slug: 'comments-pagination-next', meta: commentsPaginationNextMeta, transforms: commentsPaginationNextTransforms },
    { slug: 'comments-pagination-numbers', meta: commentsPaginationNumbersMeta, transforms: commentsPaginationNumbersTransforms },
    { slug: 'comments-pagination-previous', meta: commentsPaginationPreviousMeta, transforms: commentsPaginationPreviousTransforms },
] as const;

// post-comments-form is server-rendered (interactive <form> emitted at
// request time) and the 6 display blocks return null. Only
// comments-pagination is a true wrapper with InnerBlocks.Content.
const DYNAMIC_SAVES = [
    { slug: 'post-comments-form', save: postCommentsFormSave },
    { slug: 'post-comments-count', save: postCommentsCountSave },
    { slug: 'post-comments-link', save: postCommentsLinkSave },
    { slug: 'post-comments-title', save: postCommentsTitleSave },
    { slug: 'comments-pagination-next', save: commentsPaginationNextSave },
    { slug: 'comments-pagination-numbers', save: commentsPaginationNumbersSave },
    { slug: 'comments-pagination-previous', save: commentsPaginationPreviousSave },
] as const;

const WRAPPER_SAVES = [
    { slug: 'comments-pagination', save: commentsPaginationSave },
] as const;

describe('Comments family Pass 2 block.json', () => {
    it.each(PASS_2_BLOCKS)(
        '$slug declares the artisanpack namespace + textdomain',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            expect(meta.category).toBe('theme');
        }
    );
});

describe('Comments family Pass 2 dynamic save', () => {
    it.each(DYNAMIC_SAVES)('$slug save returns null (server-rendered)', ({ save }) => {
        expect((save as () => unknown)()).toBeNull();
    });
});

describe('Comments family Pass 2 wrapper save', () => {
    it.each(WRAPPER_SAVES)(
        '$slug save delegates to InnerBlocks.Content',
        ({ save }) => {
            const result = (save as () => ReactElement | null)();
            expect(result).not.toBeNull();
        }
    );
});

describe('Comments family Pass 2 transforms', () => {
    it.each(PASS_2_BLOCKS)(
        '$slug ships bidirectional core/* ↔ artisanpack/* transforms',
        ({ slug, transforms }) => {
            const t = transforms as TransformsModule;
            const from = t.from.find((e) => e.blocks?.includes(`core/${slug}`));
            const to = t.to.find((e) => e.blocks?.includes(`core/${slug}`));

            expect(from).toBeDefined();
            expect(to).toBeDefined();

            expect(from!.transform({ a: 1 })).toMatchObject({
                name: `artisanpack/${slug}`,
                attributes: { a: 1 },
            });
            expect(to!.transform({ b: 2 })).toMatchObject({
                name: `core/${slug}`,
                attributes: { b: 2 },
            });
        }
    );
});

describe('Comments family Pass 2 wrapper transforms preserve innerBlocks', () => {
    const WRAPPERS = [
        { slug: 'post-comments-form', transforms: postCommentsFormTransforms },
        { slug: 'comments-pagination', transforms: commentsPaginationTransforms },
    ] as const;

    it.each(WRAPPERS)(
        '$slug from-transform threads innerBlocks through',
        ({ slug, transforms }) => {
            const t = transforms as TransformsModule;
            const from = t.from.find((e) => e.blocks?.includes(`core/${slug}`))!;
            const inner = [{ name: 'placeholder', attributes: {}, innerBlocks: [] }];
            const result = from.transform({}, inner);
            expect(result.innerBlocks).toEqual(inner);
        }
    );

    it.each(WRAPPERS)(
        '$slug to-transform threads innerBlocks through',
        ({ slug, transforms }) => {
            const t = transforms as TransformsModule;
            const to = t.to.find((e) => e.blocks?.includes(`core/${slug}`))!;
            const inner = [{ name: 'placeholder', attributes: {}, innerBlocks: [] }];
            const result = to.transform({}, inner);
            expect(result.innerBlocks).toEqual(inner);
        }
    );
});

describe('Comments family Pass 2 context wiring', () => {
    it('comments-pagination provides comments/paginationArrow', () => {
        const provides = (commentsPaginationMeta as { providesContext?: Record<string, string> }).providesContext;
        expect(provides).toBeDefined();
        expect(provides!['comments/paginationArrow']).toBe('paginationArrow');
    });

    it('comments-pagination providesContext keys are backed by declared attributes', () => {
        const provides = (commentsPaginationMeta as { providesContext?: Record<string, string> })
            .providesContext ?? {};
        const attrs = (commentsPaginationMeta as { attributes?: Record<string, unknown> })
            .attributes ?? {};
        for (const attributeName of Object.values(provides)) {
            expect(attrs).toHaveProperty(attributeName);
        }
    });

    it.each([
        { slug: 'comments-pagination-next', meta: commentsPaginationNextMeta },
        { slug: 'comments-pagination-previous', meta: commentsPaginationPreviousMeta },
    ])('$slug consumes comments/paginationArrow', ({ meta }) => {
        expect(meta.usesContext).toContain('comments/paginationArrow');
    });
});
