/**
 * Deprecated `artisanpack/navigation` save component.
 *
 * The block was forked in Phase I5 (#413) and reverted to `core/navigation`
 * in #808. The stub's `edit` swaps the block for `core/navigation` on mount,
 * so save is never invoked during authoring; a stub is still required for
 * `registerBlockType` to accept the block, and returning `null` mirrors the
 * dynamic (server-rendered) contract the fork previously used.
 */

export default function DeprecatedNavigationSave(): null {
    return null;
}
