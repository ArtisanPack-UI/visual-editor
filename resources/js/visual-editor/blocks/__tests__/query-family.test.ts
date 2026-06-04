/**
 * Query family fork (#521) — block.json + save + transforms contract.
 *
 * Six blocks: query-no-results, query-pagination wrapper, the three
 * pagination leaves (previous / numbers / next), and query-title.
 *
 * Covers, in one parametrized suite: namespace + textdomain + theme
 * category on block.json, the right save shape per block (InnerBlocks
 * wrappers vs `null` dynamic leaves), and the bidirectional `core/* ↔
 * artisanpack/*` rollout transforms. Mirrors the i6-loop-feed-cluster
 * + post-comments-family suites.
 */

import { describe, it, expect, vi } from 'vitest';

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
    useBlockProps: Object.assign(
        () => ({}),
        { save: () => ({}) }
    ),
}));

import queryNoResultsMeta from '../query-no-results/block.json';
import queryPaginationMeta from '../query-pagination/block.json';
import queryPaginationNextMeta from '../query-pagination-next/block.json';
import queryPaginationNumbersMeta from '../query-pagination-numbers/block.json';
import queryPaginationPreviousMeta from '../query-pagination-previous/block.json';
import queryTitleMeta from '../query-title/block.json';

import queryNoResultsTransforms from '../query-no-results/transforms';
import queryPaginationTransforms from '../query-pagination/transforms';
import queryPaginationNextTransforms from '../query-pagination-next/transforms';
import queryPaginationNumbersTransforms from '../query-pagination-numbers/transforms';
import queryPaginationPreviousTransforms from '../query-pagination-previous/transforms';
import queryTitleTransforms from '../query-title/transforms';

import queryNoResultsSave from '../query-no-results/save';
import queryPaginationSave from '../query-pagination/save';
import queryPaginationNextSave from '../query-pagination-next/save';
import queryPaginationNumbersSave from '../query-pagination-numbers/save';
import queryPaginationPreviousSave from '../query-pagination-previous/save';
import queryTitleSave from '../query-title/save';

interface BlockTransform {
    type: string;
    blocks: string[];
    transform: (
        attrs: Record<string, unknown>,
        innerBlocks?: unknown[]
    ) => {
        name: string;
        attributes: Record<string, unknown>;
        innerBlocks: unknown[];
    };
}
interface TransformsModule {
    from: BlockTransform[];
    to: BlockTransform[];
}

// Wrappers (query-no-results, query-pagination) return InnerBlocks.Content
// — a React element. Leaves (the three pagination leaves + query-title)
// are dynamic blocks whose save returns null.
const QUERY_FAMILY = [
    {
        slug: 'query-no-results',
        meta: queryNoResultsMeta,
        transforms: queryNoResultsTransforms,
        save: queryNoResultsSave,
        savesNull: false,
    },
    {
        slug: 'query-pagination',
        meta: queryPaginationMeta,
        transforms: queryPaginationTransforms,
        save: queryPaginationSave,
        savesNull: false,
    },
    {
        slug: 'query-pagination-next',
        meta: queryPaginationNextMeta,
        transforms: queryPaginationNextTransforms,
        save: queryPaginationNextSave,
        savesNull: true,
    },
    {
        slug: 'query-pagination-numbers',
        meta: queryPaginationNumbersMeta,
        transforms: queryPaginationNumbersTransforms,
        save: queryPaginationNumbersSave,
        savesNull: true,
    },
    {
        slug: 'query-pagination-previous',
        meta: queryPaginationPreviousMeta,
        transforms: queryPaginationPreviousTransforms,
        save: queryPaginationPreviousSave,
        savesNull: true,
    },
    {
        slug: 'query-title',
        meta: queryTitleMeta,
        transforms: queryTitleTransforms,
        save: queryTitleSave,
        savesNull: true,
    },
] as const;

describe('query family block.json', () => {
    it.each(QUERY_FAMILY)(
        '$slug declares the artisanpack namespace + textdomain + theme category',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            expect(meta.category).toBe('theme');
        }
    );

    it('query-no-results is ancestor-locked to artisanpack/query', () => {
        expect((queryNoResultsMeta as { ancestor?: string[] }).ancestor).toContain(
            'artisanpack/query'
        );
    });

    it('query-pagination is ancestor-locked to artisanpack/query', () => {
        expect((queryPaginationMeta as { ancestor?: string[] }).ancestor).toContain(
            'artisanpack/query'
        );
    });

    it.each([
        { slug: 'query-pagination-next', meta: queryPaginationNextMeta },
        { slug: 'query-pagination-numbers', meta: queryPaginationNumbersMeta },
        { slug: 'query-pagination-previous', meta: queryPaginationPreviousMeta },
    ])('$slug is parent-locked to artisanpack/query-pagination', ({ meta }) => {
        expect((meta as { parent?: string[] }).parent).toContain('artisanpack/query-pagination');
    });

    it('query-pagination allows only the previous / numbers / next children', () => {
        const allowed = (queryPaginationMeta as { allowedBlocks?: string[] }).allowedBlocks ?? [];
        // Exact equality (not `toContain`) — the wrapper must reject every
        // other child so the editor inserter cannot smuggle unrelated blocks
        // into the pagination row.
        expect(allowed).toEqual([
            'artisanpack/query-pagination-previous',
            'artisanpack/query-pagination-numbers',
            'artisanpack/query-pagination-next',
        ]);
    });
});

describe('query family save', () => {
    it.each(QUERY_FAMILY)('$slug save shape', ({ save, savesNull }) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const result = (save as () => any)();

        if (savesNull) {
            expect(result).toBeNull();
        } else {
            // Wrapper save returns a React element (InnerBlocks.Content
            // or a tagName wrapper around it). Not null is enough — the
            // full structural assertion lives in the renderer-parity
            // suite.
            expect(result).not.toBeNull();
        }
    });
});

describe('query family transforms', () => {
    it.each(QUERY_FAMILY)(
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

    it.each([
        { slug: 'query-no-results', transforms: queryNoResultsTransforms },
        { slug: 'query-pagination', transforms: queryPaginationTransforms },
    ])('$slug wrapper transforms thread innerBlocks through in both directions', ({ slug, transforms }) => {
        // Wrapper transforms must preserve the nested tree on BOTH directions —
        // otherwise the user's empty-state / pagination layout disappears on
        // namespace round-trip in one direction even when the other still works.
        const t = transforms as TransformsModule;
        const innerBlocks = [{ name: 'artisanpack/paragraph', attributes: {}, innerBlocks: [] }];

        const from = t.from.find((e) => e.blocks?.includes(`core/${slug}`));
        const to = t.to.find((e) => e.blocks?.includes(`core/${slug}`));

        expect(from!.transform({}, innerBlocks).innerBlocks).toEqual(innerBlocks);
        expect(to!.transform({}, innerBlocks).innerBlocks).toEqual(innerBlocks);
    });
});
