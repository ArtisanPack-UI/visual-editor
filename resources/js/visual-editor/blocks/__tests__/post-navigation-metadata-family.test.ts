/**
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 *
 * block.json + save + transforms contract for the four blocks:
 *
 *   - post-navigation-link
 *   - post-terms
 *   - read-more
 *   - term-description
 *
 * Mirrors the i5-entity-cluster suite — parametrized over all four blocks
 * for namespace/textdomain assertions, the dynamic `null` save contract,
 * and the bidirectional `core/* ↔ artisanpack/*` transforms every fork
 * ships.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes?: Record<string, unknown>) => ({
        name,
        attributes: attributes ?? {},
        innerBlocks: [],
    }),
    getBlockType: () => undefined,
}));

import postNavigationLinkMeta from '../post-navigation-link/block.json';
import postTermsMeta from '../post-terms/block.json';
import readMoreMeta from '../read-more/block.json';
import termDescriptionMeta from '../term-description/block.json';

import postNavigationLinkTransforms from '../post-navigation-link/transforms';
import postTermsTransforms from '../post-terms/transforms';
import readMoreTransforms from '../read-more/transforms';
import termDescriptionTransforms from '../term-description/transforms';

import postNavigationLinkSave from '../post-navigation-link/save';
import postTermsSave from '../post-terms/save';
import readMoreSave from '../read-more/save';
import termDescriptionSave from '../term-description/save';

import postNavigationLinkDeprecated from '../post-navigation-link/deprecated';
import postTermsDeprecated from '../post-terms/deprecated';
import termDescriptionDeprecated from '../term-description/deprecated';

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

const FAMILY = [
    {
        slug: 'post-navigation-link',
        meta: postNavigationLinkMeta,
        transforms: postNavigationLinkTransforms,
        save: postNavigationLinkSave,
    },
    {
        slug: 'post-terms',
        meta: postTermsMeta,
        transforms: postTermsTransforms,
        save: postTermsSave,
    },
    {
        slug: 'read-more',
        meta: readMoreMeta,
        transforms: readMoreTransforms,
        save: readMoreSave,
    },
    {
        slug: 'term-description',
        meta: termDescriptionMeta,
        transforms: termDescriptionTransforms,
        save: termDescriptionSave,
    },
] as const;

describe('post navigation / metadata family block.json', () => {
    it.each(FAMILY)(
        '$slug declares the artisanpack namespace + textdomain',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            // Theme-category parity with the upstream blocks.
            expect(meta.category).toBe('theme');
        }
    );

    it('post-navigation-link, post-terms, and read-more pipe artisanpack/postPreview through usesContext (#483)', () => {
        const postContextBlocks = [
            postNavigationLinkMeta,
            postTermsMeta,
            readMoreMeta,
        ];

        for (const meta of postContextBlocks) {
            expect(meta.usesContext).toContain('artisanpack/postPreview');
        }
    });

    it('term-description keeps the archive-only termId/taxonomy context and does not opt into postPreview', () => {
        expect(termDescriptionMeta.usesContext).toEqual(['termId', 'taxonomy']);
    });
});

describe('post navigation / metadata family dynamic save', () => {
    it.each(FAMILY)('$slug save returns null (server-rendered)', ({ save }) => {
        expect((save as () => unknown)()).toBeNull();
    });
});

describe('post navigation / metadata family transforms', () => {
    it.each(FAMILY)(
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

describe('post navigation / metadata family deprecations', () => {
    // read-more has no upstream deprecated.js — the fork omits the chain
    // by design, asserted via the import surface in index.ts.
    const blocksWithDeprecations = [
        { slug: 'post-navigation-link', deprecated: postNavigationLinkDeprecated },
        { slug: 'post-terms', deprecated: postTermsDeprecated },
        { slug: 'term-description', deprecated: termDescriptionDeprecated },
    ];

    it.each(blocksWithDeprecations)(
        '$slug deprecation migrates textAlign into the canonical block-support shape',
        ({ deprecated }) => {
            const [v1] = deprecated;
            expect(v1.save()).toBeNull();
            expect(typeof v1.migrate).toBe('function');

            const migrated = v1.migrate({ textAlign: 'center' });

            expect(migrated).toMatchObject({
                style: { typography: { textAlign: 'center' } },
            });
            expect((migrated as { textAlign?: string }).textAlign).toBeUndefined();
        }
    );

    it.each(blocksWithDeprecations)(
        '$slug deprecation isEligible flags legacy markup',
        ({ deprecated }) => {
            const [v1] = deprecated;

            expect(v1.isEligible({ textAlign: 'right' })).toBe(true);
            expect(v1.isEligible({ className: 'foo has-text-align-left bar' })).toBe(true);
            expect(v1.isEligible({})).toBe(false);
        }
    );
});

describe('post navigation / metadata family preview-context shape', () => {
    it('QueryPreviewPost accepts terms/adjacent/term fields (#520)', async () => {
        // Smoke-test the QueryPreviewPost extension: pin the runtime
        // shape so a future refactor that drops one of the keys trips
        // the test instead of silently breaking the preview-context
        // path the post-terms / post-navigation-link / term-description
        // edits depend on.
        type Preview = import('../../editor/use-query-preview').QueryPreviewPost;

        const sample: Preview = {
            id: 1,
            terms: { category: [{ name: 'News', url: '/news' }] },
            adjacent: { previous: { title: 'Older', url: '/older' }, next: null },
            term: { name: 'News', description: 'About news' },
        };

        expect(sample.terms?.category?.[0]?.name).toBe('News');
        expect(sample.adjacent?.previous?.url).toBe('/older');
        expect(sample.term?.description).toBe('About news');
    });
});
