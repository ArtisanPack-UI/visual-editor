/**
 * Comments Number — save component.
 *
 * Dynamic block: the count is resolved server-side by `PostResolver`
 * and combined with the saved labels by the Blade / React / Vue
 * renderers at render time. Returning `null` from save is the
 * Gutenberg convention for dynamic blocks — only the block delimiter
 * and attributes are persisted.
 */

export default function CommentsNumberSave(): null {
    return null;
}
