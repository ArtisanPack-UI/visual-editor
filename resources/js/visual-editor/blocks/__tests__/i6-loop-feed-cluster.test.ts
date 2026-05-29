/**
 * Phase I6 loop / feed cluster (#414) — block.json + save + transforms
 * contract for the three dynamic feed blocks (`archives`, `categories`,
 * `tag-cloud`).
 *
 * Covers, in one parametrized suite: namespace + textdomain + category on
 * block.json, the dynamic (`null`) save every server-rendered feed block
 * ships, and the bidirectional `core/* ↔ artisanpack/*` rollout transforms.
 *
 * The `query` / `post-template` forks have a non-null save and their own
 * renderer-parity coverage, so they are asserted separately.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
}));

import archivesMeta from '../archives/block.json';
import categoriesMeta from '../categories/block.json';
import tagCloudMeta from '../tag-cloud/block.json';

import archivesTransforms from '../archives/transforms';
import categoriesTransforms from '../categories/transforms';
import tagCloudTransforms from '../tag-cloud/transforms';

import archivesSave from '../archives/save';
import categoriesSave from '../categories/save';
import tagCloudSave from '../tag-cloud/save';

import queryMeta from '../query/block.json';
import postTemplateMeta from '../post-template/block.json';
import queryTransforms from '../query/transforms';
import postTemplateTransforms from '../post-template/transforms';

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

const FEED_BLOCKS = [
    { slug: 'archives', meta: archivesMeta, transforms: archivesTransforms, save: archivesSave },
    { slug: 'categories', meta: categoriesMeta, transforms: categoriesTransforms, save: categoriesSave },
    { slug: 'tag-cloud', meta: tagCloudMeta, transforms: tagCloudTransforms, save: tagCloudSave },
] as const;

describe('I6 loop/feed cluster block.json', () => {
    it.each(FEED_BLOCKS)(
        '$slug declares the artisanpack namespace + textdomain + widgets category',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            // Feed blocks keep the upstream `widgets` category (plan 13 §8).
            expect(meta.category).toBe('widgets');
        }
    );
});

describe('I6 loop/feed cluster dynamic save', () => {
    it.each(FEED_BLOCKS)('$slug save returns null (server-rendered)', ({ save }) => {
        expect((save as () => unknown)()).toBeNull();
    });
});

describe('I6 loop/feed cluster transforms', () => {
    it.each(FEED_BLOCKS)(
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

const QUERY_BLOCKS = [
    { slug: 'query', meta: queryMeta, transforms: queryTransforms },
    { slug: 'post-template', meta: postTemplateMeta, transforms: postTemplateTransforms },
] as const;

describe('I6 loop/feed cluster — query + post-template block.json', () => {
    it.each(QUERY_BLOCKS)(
        '$slug declares the artisanpack namespace + textdomain + theme category',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            // query + post-template keep the upstream `theme` category.
            expect(meta.category).toBe('theme');
        }
    );

    it('post-template is parent-locked to artisanpack/query', () => {
        expect((postTemplateMeta as { ancestor?: string[] }).ancestor).toContain(
            'artisanpack/query'
        );
    });
});

describe('I6 loop/feed cluster — query + post-template transforms', () => {
    it.each(QUERY_BLOCKS)(
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

    it('query folds the deprecated core/query-loop alias into artisanpack/query', () => {
        const t = queryTransforms as TransformsModule;
        const alias = t.from.find((e) => e.blocks?.includes('core/query-loop'));

        expect(alias).toBeDefined();
        expect(alias!.transform({ c: 3 })).toMatchObject({
            name: 'artisanpack/query',
            attributes: { c: 3 },
        });
    });
});
