/**
 * Vitest for the modal's client-side pattern filter (#639).
 *
 * The server's `?post_type=` filter is permissive by design so the
 * general pattern library (and the sidebar block-inserter) can keep
 * showing unscoped patterns to every context. The modal tightens
 * client-side to "explicit scope only, with a seed carve-out."
 */

import { describe, expect, it } from 'vitest';

import type { PatternRecord } from '../../../site-editor/patterns/api-client';
import { BUILT_IN_BLANK_SLUG, filterModalPatterns } from '../filter-modal-patterns';

function makePattern(overrides: Partial<PatternRecord> = {}): PatternRecord {
    return {
        id: 1,
        slug: 'x',
        title: { rendered: 'X', raw: 'X' },
        content: { raw: '', blocks: [] },
        synced: false,
        categories: [],
        status: 'publish',
        type: 'wp_block',
        post_types: null,
        ...overrides,
    };
}

describe('filterModalPatterns', () => {
    it('keeps a pattern whose post_types explicitly includes the requested post type', () => {
        const patterns = [
            makePattern({ id: 1, slug: 'landing-hero', post_types: [ 'page' ] }),
        ];

        expect(filterModalPatterns(patterns, 'page')).toEqual(patterns);
    });

    it('drops a pattern that scopes only to a different post type', () => {
        const patterns = [
            makePattern({ id: 1, slug: 'recipe-intro', post_types: [ 'post' ] }),
        ];

        expect(filterModalPatterns(patterns, 'page')).toEqual([]);
    });

    it('drops unscoped patterns unless they are the built-in Blank seed', () => {
        const patterns = [
            makePattern({ id: 1, slug: 'universal-cta', post_types: null }),
            makePattern({ id: 2, slug: BUILT_IN_BLANK_SLUG, post_types: null }),
        ];

        const result = filterModalPatterns(patterns, 'page');

        expect(result.map((p) => p.slug)).toEqual([ BUILT_IN_BLANK_SLUG ]);
    });

    it('drops patterns scoped to an empty array', () => {
        // A misregistered scope (`post_types: []`) matches nothing —
        // don't secretly re-include it in every modal.
        const patterns = [
            makePattern({ id: 1, slug: 'nowhere', post_types: [] }),
        ];

        expect(filterModalPatterns(patterns, 'page')).toEqual([]);
    });

    it('handles a missing post_types field (backward-compat with older backends)', () => {
        const patterns = [
            {
                ...makePattern({ id: 1, slug: 'legacy' }),
                post_types: undefined,
            } as unknown as PatternRecord,
        ];

        expect(filterModalPatterns(patterns, 'page')).toEqual([]);
    });

    it('keeps only the seed when postType is null (no post-type mapping)', () => {
        const patterns = [
            makePattern({ id: 1, slug: 'landing-hero', post_types: [ 'page' ] }),
            makePattern({ id: 2, slug: BUILT_IN_BLANK_SLUG, post_types: null }),
        ];

        const result = filterModalPatterns(patterns, null);

        expect(result.map((p) => p.slug)).toEqual([ BUILT_IN_BLANK_SLUG ]);
    });

    it('produces the exact set for a post editor across a realistic input', () => {
        const patterns = [
            makePattern({ id: 1, slug: 'landing-hero', post_types: [ 'page' ] }),
            makePattern({ id: 2, slug: 'recipe-intro', post_types: [ 'post' ] }),
            makePattern({ id: 3, slug: 'universal-cta', post_types: null }),
            makePattern({ id: 4, slug: BUILT_IN_BLANK_SLUG, post_types: null }),
        ];

        expect(
            filterModalPatterns(patterns, 'post').map((p) => p.slug).sort()
        ).toEqual([ BUILT_IN_BLANK_SLUG, 'recipe-intro' ].sort());
    });
} );
