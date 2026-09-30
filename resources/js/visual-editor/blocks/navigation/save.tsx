/**
 * Deprecated `artisanpack/navigation` save component.
 *
 * The block was forked in Phase I5 (#413) and reverted to `core/navigation`
 * in #808. The stub's `edit` swaps the block for `core/navigation` on mount,
 * but a stub that cannot migrate (locked parent, disabled editing mode, or a
 * post saved without the block ever rendering) is still serialized through
 * this save. It must therefore reproduce the 1.11 fork's output exactly — the
 * fork delegated to `core/navigation`'s save, which persists inline inner
 * blocks (`<!-- wp:navigation-link … -->` children) and emits nothing for the
 * `ref` (entity-backed) form.
 *
 * The upstream logic is mirrored here rather than delegated to the registered
 * `core/navigation` save so the stub validates and re-serializes identically
 * even if `core/navigation` has not registered yet.
 *
 * @since 1.12.0
 */

import { InnerBlocks } from '@wordpress/block-editor';

interface SaveProps {
    attributes: { ref?: number };
}

export default function DeprecatedNavigationSave({
    attributes,
}: SaveProps): JSX.Element | null {
    if (attributes.ref) {
        return null;
    }

    return <InnerBlocks.Content />;
}
