/**
 * Categories — save component.
 *
 * Dynamic block: the saved markup is just the block delimiter
 * (`<!-- wp:artisanpack/categories {…} /-->`) and the HTML is generated
 * server-side by `CategoriesBlock::render()`. Returning `null` from save is
 * the Gutenberg convention for dynamic blocks. Phase I6 loop / feed
 * cluster (#414).
 */

export default function CategoriesSave(): null {
    return null;
}
