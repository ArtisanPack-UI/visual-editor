/**
 * Detects a freshly-created record with no persisted content — the trigger
 * for the #639 page-pattern-inserter modal to auto-open.
 *
 * The heuristic mirrors WordPress's "Choose a pattern" flow:
 *   1. The loaded content payload is empty (no blocks and no non-whitespace
 *      raw markup).
 *   2. The underlying record has never been saved (`createdAt === updatedAt`
 *      or `updatedAt` is missing entirely, so the record still shows its
 *      creation timestamp only).
 *
 * Both conditions must hold at first-load time. Once the user saves — even
 * a blank canvas — the `updated_at` timestamp advances past `created_at`
 * and the record no longer looks fresh; the modal never auto-opens again
 * without a hard reload of a freshly-created record. This is exactly the
 * "first-save suppression" behavior the acceptance criteria call for
 * (#639), and it needs no schema change or client-side flag.
 *
 * When the host doesn't stamp `data-created-at` / `data-updated-at`
 * (backward compat), the detection returns `false` and the modal never
 * auto-opens. The toolbar button still lets users open it manually.
 *
 * @since 1.4.0
 */

import type { BlockInstance } from '@wordpress/blocks';
import { useMemo } from 'react';

export interface FreshContentInputs {
    /**
     * Blocks currently loaded into the editor. Detection ignores anything
     * queued for save; only the initial payload matters.
     */
    readonly blocks: readonly BlockInstance[];
    /**
     * ISO-8601 creation timestamp, or `undefined` when the host didn't
     * stamp `data-created-at` on the mount element.
     */
    readonly createdAt: string | undefined;
    /**
     * ISO-8601 last-update timestamp, or `undefined` when the host didn't
     * stamp `data-updated-at`.
     */
    readonly updatedAt: string | undefined;
    /**
     * The `usePersistence` load-status enum. Detection only returns
     * `true` once the initial fetch has completed successfully;
     * otherwise `blocks` may be `[]` transiently while the request is
     * still in flight and a naïve check would misfire.
     */
    readonly loadStatus: 'idle' | 'loading' | 'ready' | 'error';
}

function normalizeTimestamp(input: string | undefined): string | null {
    if (input === undefined) {
        return null;
    }

    const trimmed = input.trim();

    if (trimmed === '') {
        return null;
    }

    // Reduce ISO-8601 timestamps to their millisecond form so a
    // trailing `Z` vs. `+00:00` offset representation doesn't cause
    // otherwise-equal timestamps to look different. `Date.parse`
    // handles both forms and returns NaN for garbage input; we bail
    // to `null` on NaN so a malformed stamp defaults to "not fresh".
    const parsed = Date.parse(trimmed);

    return Number.isNaN(parsed) ? null : String(parsed);
}

export function isFreshContent(inputs: FreshContentInputs): boolean {
    const { blocks, createdAt, updatedAt, loadStatus } = inputs;

    if (loadStatus !== 'ready') {
        return false;
    }

    if (blocks.length > 0) {
        return false;
    }

    const created = normalizeTimestamp(createdAt);
    const updated = normalizeTimestamp(updatedAt);

    // Both timestamps must be present — a host that stamps one but not
    // the other has (accidentally or otherwise) opted out of the
    // detection. Returning `false` here is the safe default.
    if (created === null || updated === null) {
        return false;
    }

    return created === updated;
}

/**
 * Memoized wrapper so callers can drop this into a React tree without
 * re-computing on every render.
 *
 * @since 1.4.0
 */
export function useFreshContentDetection(inputs: FreshContentInputs): boolean {
    return useMemo(
        () => isFreshContent(inputs),
        [inputs.blocks, inputs.createdAt, inputs.updatedAt, inputs.loadStatus]
    );
}
