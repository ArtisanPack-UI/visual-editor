/**
 * Previous Post — save component.
 *
 * Dynamic block: each renderer reads the persisted inner-block tree
 * (re-resolved against the adjacent post by `PostResolver`) and emits
 * the final markup, so returning `null` keeps Gutenberg from
 * serializing wrapper markup into post_content. (#499)
 */

export default function PreviousPostSave(): null {
    return null;
}
