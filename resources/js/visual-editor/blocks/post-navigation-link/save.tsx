/**
 * PostNavigationLink — save component.
 *
 * Dynamic block: the saved markup is just the block delimiter and the HTML
 * is produced server-side by the Blade / React / Vue renderers from the
 * stamped `_resolvedAdjacent*` attributes. Returning `null` from save is
 * the Gutenberg convention for dynamic blocks. Phase I-Block-Fork —
 * post navigation / metadata family (#520).
 */

export default function PostNavigationLinkSave(): null {
    return null;
}
