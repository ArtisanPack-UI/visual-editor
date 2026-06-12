/**
 * Related Posts — save component.
 *
 * Dynamic block: each renderer reads the persisted inner-block tree
 * (cloned once per related post by `QueryInliner`) and emits the final
 * markup, so returning `null` keeps Gutenberg from serializing wrapper
 * markup into post_content. (#501)
 */

export default function RelatedPostsSave(): null {
    return null;
}
