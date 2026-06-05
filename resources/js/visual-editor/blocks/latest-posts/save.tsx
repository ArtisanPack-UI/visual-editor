/**
 * Latest Posts — save component.
 *
 * Dynamic block: the saved markup is just the block delimiter
 * (`<!-- wp:artisanpack/latest-posts {…} /-->`) and the HTML is generated
 * server-side by `LatestPostsBlock::render()`. Returning `null` from save
 * is the Gutenberg convention for dynamic blocks.
 */

export default function LatestPostsSave(): null {
    return null;
}
