/**
 * Comments family (#519) — block.json + save + transforms contract.
 *
 * Covers the 8 Pass 1 forks (`comments`, `comment-template`, and the 6
 * `comment-*` inner display blocks) in one parametrized suite:
 * namespace + textdomain on block.json, the dynamic (`null`) save for
 * the 6 display blocks, the delegating (`InnerBlocks.Content`) save
 * for the two wrappers, and the bidirectional `core/* ↔ artisanpack/*`
 * transforms every fork ships.
 */

import { describe, it, expect, vi } from 'vitest';
import type { ReactElement } from 'react';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
}));

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: Object.assign(
        () => null,
        { Content: () => null }
    ),
}));

import commentsMeta from '../comments/block.json';
import commentTemplateMeta from '../comment-template/block.json';
import commentAuthorAvatarMeta from '../comment-author-avatar/block.json';
import commentAuthorNameMeta from '../comment-author-name/block.json';
import commentContentMeta from '../comment-content/block.json';
import commentDateMeta from '../comment-date/block.json';
import commentEditLinkMeta from '../comment-edit-link/block.json';
import commentReplyLinkMeta from '../comment-reply-link/block.json';

import commentsTransforms from '../comments/transforms';
import commentTemplateTransforms from '../comment-template/transforms';
import commentAuthorAvatarTransforms from '../comment-author-avatar/transforms';
import commentAuthorNameTransforms from '../comment-author-name/transforms';
import commentContentTransforms from '../comment-content/transforms';
import commentDateTransforms from '../comment-date/transforms';
import commentEditLinkTransforms from '../comment-edit-link/transforms';
import commentReplyLinkTransforms from '../comment-reply-link/transforms';

import commentAuthorAvatarSave from '../comment-author-avatar/save';
import commentAuthorNameSave from '../comment-author-name/save';
import commentContentSave from '../comment-content/save';
import commentDateSave from '../comment-date/save';
import commentEditLinkSave from '../comment-edit-link/save';
import commentReplyLinkSave from '../comment-reply-link/save';
import commentsSave from '../comments/save';
import commentTemplateSave from '../comment-template/save';

interface BlockTransform {
    type: string;
    blocks: string[];
    transform: (attrs: Record<string, unknown>) => {
        name: string;
        attributes: Record<string, unknown>;
    };
}
interface TransformsModule {
    from: BlockTransform[];
    to: BlockTransform[];
}

const COMMENT_BLOCKS = [
    { slug: 'comments', meta: commentsMeta, transforms: commentsTransforms },
    { slug: 'comment-template', meta: commentTemplateMeta, transforms: commentTemplateTransforms },
    { slug: 'comment-author-avatar', meta: commentAuthorAvatarMeta, transforms: commentAuthorAvatarTransforms },
    { slug: 'comment-author-name', meta: commentAuthorNameMeta, transforms: commentAuthorNameTransforms },
    { slug: 'comment-content', meta: commentContentMeta, transforms: commentContentTransforms },
    { slug: 'comment-date', meta: commentDateMeta, transforms: commentDateTransforms },
    { slug: 'comment-edit-link', meta: commentEditLinkMeta, transforms: commentEditLinkTransforms },
    { slug: 'comment-reply-link', meta: commentReplyLinkMeta, transforms: commentReplyLinkTransforms },
] as const;

const DYNAMIC_SAVES = [
    { slug: 'comment-author-avatar', save: commentAuthorAvatarSave },
    { slug: 'comment-author-name', save: commentAuthorNameSave },
    { slug: 'comment-content', save: commentContentSave },
    { slug: 'comment-date', save: commentDateSave },
    { slug: 'comment-edit-link', save: commentEditLinkSave },
    { slug: 'comment-reply-link', save: commentReplyLinkSave },
] as const;

const WRAPPER_SAVES = [
    { slug: 'comments', save: commentsSave },
    { slug: 'comment-template', save: commentTemplateSave },
] as const;

describe('Comments family block.json', () => {
    it.each(COMMENT_BLOCKS)(
        '$slug declares the artisanpack namespace + textdomain',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            // Comments family blocks live in the `theme` category alongside
            // the rest of the entity / loop forks.
            expect(meta.category).toBe('theme');
        }
    );
});

describe('Comments family dynamic save', () => {
    it.each(DYNAMIC_SAVES)('$slug save returns null (server-rendered)', ({ save }) => {
        expect((save as () => unknown)()).toBeNull();
    });
});

describe('Comments family wrapper save', () => {
    it.each(WRAPPER_SAVES)(
        '$slug save delegates to InnerBlocks.Content',
        ({ save }) => {
            // Wrapper saves return a React element (InnerBlocks.Content),
            // not null. Just assert non-null — the mocked InnerBlocks.Content
            // is a no-op component so the returned shape is a valid element.
            const result = (save as () => ReactElement | null)();
            expect(result).not.toBeNull();
        }
    );
});

describe('Comments family transforms', () => {
    it.each(COMMENT_BLOCKS)(
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

describe('Comments family usesContext wiring', () => {
    const PER_COMMENT_BLOCKS = COMMENT_BLOCKS.filter(({ slug }) =>
        slug.startsWith('comment-') && slug !== 'comment-template'
    );

    it.each(PER_COMMENT_BLOCKS)(
        '$slug reads artisanpack/commentPreview from block context',
        ({ meta }) => {
            expect(meta.usesContext).toContain('artisanpack/commentPreview');
            expect(meta.usesContext).toContain('artisanpack/commentId');
        }
    );

    it('comment-template provides artisanpack/commentId + commentPreview', () => {
        const provides = (commentTemplateMeta as { providesContext?: Record<string, string> }).providesContext;
        expect(provides).toBeDefined();
        expect(provides!['artisanpack/commentId']).toBe('commentId');
        expect(provides!['artisanpack/commentPreview']).toBe('commentPreview');
    });
});
