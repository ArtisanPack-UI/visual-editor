/**
 * Search — save component.
 *
 * `core/search` is a server-interactive block: upstream ships no `save`
 * and the markup is produced at render time. The fork keeps that contract
 * — the saved markup is just the block delimiter
 * (`<!-- wp:artisanpack/search {…} /-->`) and the front-end Blade/React/Vue
 * renderers build the `<form>` from the attributes. Returning `null` from
 * save is the Gutenberg convention for blocks rendered outside `save`.
 */

export default function SearchSave(): null {
    return null;
}
