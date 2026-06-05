/**
 * Archives — save component.
 *
 * Dynamic block: the saved markup is just the block delimiter
 * (`<!-- wp:artisanpack/archives {…} /-->`) and the HTML is generated
 * server-side by `ArchivesBlock::render()`. Returning `null` from save is
 * the Gutenberg convention for dynamic blocks. Phase I6 loop / feed
 * cluster (#414).
 */

export default function ArchivesSave(): null {
    return null;
}
