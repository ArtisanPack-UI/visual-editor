/**
 * Vitest for the fresh-content detection heuristic (#639).
 *
 * The hook itself is a `useMemo` wrapper around `isFreshContent`; we
 * exercise the pure function directly to keep the cases readable and
 * avoid a react-hook harness.
 */

import { describe, expect, it } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

import { isFreshContent } from '../use-fresh-content-detection';

const BLOCK: BlockInstance = {
    clientId: 'x',
    name: 'core/paragraph',
    isValid: true,
    attributes: {},
    innerBlocks: [],
} as unknown as BlockInstance;

describe('isFreshContent', () => {
    it('returns true when content is empty, load is ready, and created === updated', () => {
        expect(
            isFreshContent({
                blocks: [],
                createdAt: '2026-01-01T00:00:00Z',
                updatedAt: '2026-01-01T00:00:00Z',
                loadStatus: 'ready',
            })
        ).toBe(true);
    });

    it('treats equivalent ISO representations as the same instant', () => {
        // A backend that emits `+00:00` for `created_at` and `Z` for
        // `updated_at` (or vice versa) shouldn't defeat the heuristic —
        // the timestamps refer to the same moment.
        expect(
            isFreshContent({
                blocks: [],
                createdAt: '2026-01-01T00:00:00+00:00',
                updatedAt: '2026-01-01T00:00:00Z',
                loadStatus: 'ready',
            })
        ).toBe(true);
    });

    it('returns false while the initial load is still in flight', () => {
        // During `loading`, blocks are transiently empty even for saved
        // pages — the modal would misfire without the guard.
        expect(
            isFreshContent({
                blocks: [],
                createdAt: '2026-01-01T00:00:00Z',
                updatedAt: '2026-01-01T00:00:00Z',
                loadStatus: 'loading',
            })
        ).toBe(false);
    });

    it('returns false when the record has been saved (created !== updated)', () => {
        expect(
            isFreshContent({
                blocks: [],
                createdAt: '2026-01-01T00:00:00Z',
                updatedAt: '2026-01-02T00:00:00Z',
                loadStatus: 'ready',
            })
        ).toBe(false);
    });

    it('returns false when the canvas already has content', () => {
        expect(
            isFreshContent({
                blocks: [BLOCK],
                createdAt: '2026-01-01T00:00:00Z',
                updatedAt: '2026-01-01T00:00:00Z',
                loadStatus: 'ready',
            })
        ).toBe(false);
    });

    it('returns false when either timestamp is missing (backward-compat)', () => {
        // Hosts that don't stamp `data-created-at` / `data-updated-at`
        // opt out of the detection entirely. Safe default: no auto-open.
        expect(
            isFreshContent({
                blocks: [],
                createdAt: undefined,
                updatedAt: '2026-01-01T00:00:00Z',
                loadStatus: 'ready',
            })
        ).toBe(false);

        expect(
            isFreshContent({
                blocks: [],
                createdAt: '2026-01-01T00:00:00Z',
                updatedAt: undefined,
                loadStatus: 'ready',
            })
        ).toBe(false);

        expect(
            isFreshContent({
                blocks: [],
                createdAt: undefined,
                updatedAt: undefined,
                loadStatus: 'ready',
            })
        ).toBe(false);
    });

    it('returns false when a stamped timestamp is malformed', () => {
        // A bogus stamp shouldn't be silently coerced to `NaN === NaN
        // === false` — but Date.parse yields NaN and we normalize to
        // null, which is compared against null and yields true. Guard
        // explicitly here so the tests document the behavior.
        expect(
            isFreshContent({
                blocks: [],
                createdAt: 'not-a-date',
                updatedAt: 'also-not-a-date',
                loadStatus: 'ready',
            })
        ).toBe(false);
    });
});
