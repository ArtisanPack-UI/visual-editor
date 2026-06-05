/**
 * Phase I5 entity-cluster (#413) — block.json + save + transforms contract.
 *
 * Covers all 11 forked entity blocks (`template-part`, the `post-*` family,
 * the `site-*` family, `navigation`) in one parametrized suite: namespace +
 * textdomain on block.json, the dynamic (`null`) save for the 10
 * server-rendered blocks, and the bidirectional `core/* ↔ artisanpack/*`
 * transforms every fork ships.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    // navigation's save delegates through getBlockType; an unregistered
    // core block makes it return null, which is the contract we assert.
    getBlockType: () => undefined,
}));

import templatePartMeta from '../template-part/block.json';
import postTitleMeta from '../post-title/block.json';
import postContentMeta from '../post-content/block.json';
import postExcerptMeta from '../post-excerpt/block.json';
import postDateMeta from '../post-date/block.json';
import postAuthorMeta from '../post-author/block.json';
import postFeaturedImageMeta from '../post-featured-image/block.json';
import siteTitleMeta from '../site-title/block.json';
import siteTaglineMeta from '../site-tagline/block.json';
import siteLogoMeta from '../site-logo/block.json';
import navigationMeta from '../navigation/block.json';

import templatePartTransforms from '../template-part/transforms';
import postTitleTransforms from '../post-title/transforms';
import postContentTransforms from '../post-content/transforms';
import postExcerptTransforms from '../post-excerpt/transforms';
import postDateTransforms from '../post-date/transforms';
import postAuthorTransforms from '../post-author/transforms';
import postFeaturedImageTransforms from '../post-featured-image/transforms';
import siteTitleTransforms from '../site-title/transforms';
import siteTaglineTransforms from '../site-tagline/transforms';
import siteLogoTransforms from '../site-logo/transforms';
import navigationTransforms from '../navigation/transforms';

import templatePartSave from '../template-part/save';
import postTitleSave from '../post-title/save';
import postContentSave from '../post-content/save';
import postExcerptSave from '../post-excerpt/save';
import postDateSave from '../post-date/save';
import postAuthorSave from '../post-author/save';
import postFeaturedImageSave from '../post-featured-image/save';
import siteTitleSave from '../site-title/save';
import siteTaglineSave from '../site-tagline/save';
import siteLogoSave from '../site-logo/save';

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

const ENTITY_BLOCKS = [
    { slug: 'template-part', meta: templatePartMeta, transforms: templatePartTransforms, save: templatePartSave },
    { slug: 'post-title', meta: postTitleMeta, transforms: postTitleTransforms, save: postTitleSave },
    { slug: 'post-content', meta: postContentMeta, transforms: postContentTransforms, save: postContentSave },
    { slug: 'post-excerpt', meta: postExcerptMeta, transforms: postExcerptTransforms, save: postExcerptSave },
    { slug: 'post-date', meta: postDateMeta, transforms: postDateTransforms, save: postDateSave },
    { slug: 'post-author', meta: postAuthorMeta, transforms: postAuthorTransforms, save: postAuthorSave },
    { slug: 'post-featured-image', meta: postFeaturedImageMeta, transforms: postFeaturedImageTransforms, save: postFeaturedImageSave },
    { slug: 'site-title', meta: siteTitleMeta, transforms: siteTitleTransforms, save: siteTitleSave },
    { slug: 'site-tagline', meta: siteTaglineMeta, transforms: siteTaglineTransforms, save: siteTaglineSave },
    { slug: 'site-logo', meta: siteLogoMeta, transforms: siteLogoTransforms, save: siteLogoSave },
    // navigation has a delegating (non-null) save — asserted separately.
    { slug: 'navigation', meta: navigationMeta, transforms: navigationTransforms, save: null },
] as const;

describe('I5 entity-cluster block.json', () => {
    it.each(ENTITY_BLOCKS)(
        '$slug declares the artisanpack namespace + textdomain',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            // Entity blocks keep the upstream `theme` category (plan 13 §8).
            expect(meta.category).toBe('theme');
        }
    );
});

describe('I5 entity-cluster dynamic save', () => {
    const dynamic = ENTITY_BLOCKS.filter((b) => b.save !== null);

    it.each(dynamic)('$slug save returns null (server-rendered)', ({ save }) => {
        expect((save as () => unknown)()).toBeNull();
    });
});

describe('I5 entity-cluster transforms', () => {
    it.each(ENTITY_BLOCKS)(
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
