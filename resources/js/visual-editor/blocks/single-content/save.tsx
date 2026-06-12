/**
 * Single Content — save component.
 *
 * Dynamic block: each renderer reads the persisted inner-block tree
 * (re-resolved against the chosen post by `QueryInliner`) and emits the
 * final markup, so returning `null` keeps Gutenberg from serializing
 * wrapper markup into post_content. (#501)
 */

export default function SingleContentSave(): null {
    return null;
}
