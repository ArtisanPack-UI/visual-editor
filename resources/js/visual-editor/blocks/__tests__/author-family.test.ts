/**
 * Author family fork (#518) — block.json + save + transforms contract.
 *
 * Covers the three forked author-family blocks (post-author-name,
 * post-author-biography, avatar) in one parametrized suite: namespace +
 * textdomain on block.json, the dynamic (`null`) save, and the
 * bidirectional `core/* ↔ artisanpack/*` transforms every fork ships.
 *
 * Mirrors the I5 entity-cluster contract (#413) — see `i5-entity-cluster.test.ts`.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
}));

import postAuthorNameMeta from '../post-author-name/block.json';
import postAuthorBiographyMeta from '../post-author-biography/block.json';
import avatarMeta from '../avatar/block.json';

import postAuthorNameTransforms from '../post-author-name/transforms';
import postAuthorBiographyTransforms from '../post-author-biography/transforms';
import avatarTransforms from '../avatar/transforms';

import postAuthorNameSave from '../post-author-name/save';
import postAuthorBiographySave from '../post-author-biography/save';
import avatarSave from '../avatar/save';

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

const AUTHOR_FAMILY_BLOCKS = [
    {
        slug: 'post-author-name',
        meta: postAuthorNameMeta,
        transforms: postAuthorNameTransforms,
        save: postAuthorNameSave,
    },
    {
        slug: 'post-author-biography',
        meta: postAuthorBiographyMeta,
        transforms: postAuthorBiographyTransforms,
        save: postAuthorBiographySave,
    },
    {
        slug: 'avatar',
        meta: avatarMeta,
        transforms: avatarTransforms,
        save: avatarSave,
    },
] as const;

describe('author-family block.json (#518)', () => {
    it.each(AUTHOR_FAMILY_BLOCKS)(
        '$slug declares the artisanpack namespace + textdomain',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            // Author family stays in the upstream `theme` category.
            expect(meta.category).toBe('theme');
        }
    );

    it.each(AUTHOR_FAMILY_BLOCKS)(
        '$slug declares artisanpack/postPreview in usesContext for query-loop preview',
        ({ meta }) => {
            expect((meta as { usesContext?: string[] }).usesContext).toContain(
                'artisanpack/postPreview'
            );
        }
    );

    it('avatar keeps commentId in usesContext for forward-compat with the comments family (#519)', () => {
        expect((avatarMeta as { usesContext?: string[] }).usesContext).toContain('commentId');
    });
});

describe('author-family dynamic save (#518)', () => {
    it.each(AUTHOR_FAMILY_BLOCKS)('$slug save returns null (server-rendered)', ({ save }) => {
        expect((save as () => unknown)()).toBeNull();
    });
});

describe('author-family transforms (#518)', () => {
    it.each(AUTHOR_FAMILY_BLOCKS)(
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
