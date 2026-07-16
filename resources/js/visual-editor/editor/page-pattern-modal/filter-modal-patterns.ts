/**
 * Client-side tightening on top of the server's `?post_type=` filter for
 * the page-pattern-inserter modal (#639).
 *
 * The general pattern index (`PatternController::index`) filters
 * permissively — unscoped patterns (post_types === null) match every
 * request so the sidebar block-inserter panel keeps its "library of
 * everything" behavior. The modal wants stricter semantics: only
 * patterns that explicitly declare they're for this post type belong
 * in the whole-page picker. Anything else risks polluting the modal
 * with what the user thinks of as block-inserter snippets.
 *
 * The one exception is the built-in `page/blank` seed — it's
 * intentionally unscoped so a fresh install always has an "empty
 * canvas" affordance in the modal regardless of the post type. Special-
 * casing here keeps the seed available without giving up the stricter
 * default for every other unscoped pattern.
 *
 * @since 1.4.0
 */

import type { PatternRecord } from '../../site-editor/patterns/api-client';

export const BUILT_IN_BLANK_SLUG = 'page/blank';

/**
 * @param records    Patterns as returned by `listPatterns`. May include
 *                   unscoped entries — the server's `?post_type=` is
 *                   permissive by design.
 * @param postType   The current editor's post-type slug (`page`,
 *                   `post`, or a host-registered custom type). When
 *                   `null`, the modal is running against a resource
 *                   with no post-type mapping and the caller should
 *                   already have skipped the fetch — but we handle
 *                   `null` here defensively by returning the seed only.
 */
export function filterModalPatterns(
    records: readonly PatternRecord[],
    postType: string | null
): readonly PatternRecord[] {
    return records.filter((record) => {
        if (record.slug === BUILT_IN_BLANK_SLUG) {
            return true;
        }

        if (postType === null) {
            return false;
        }

        // Unscoped patterns (post_types null / undefined / empty) don't
        // survive — the general pattern library is what the sidebar
        // inserter shows, not this modal.
        if (
            record.post_types === null ||
            record.post_types === undefined ||
            record.post_types.length === 0
        ) {
            return false;
        }

        return record.post_types.includes(postType);
    });
}
