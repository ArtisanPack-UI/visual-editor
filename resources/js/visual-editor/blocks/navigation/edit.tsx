/**
 * Deprecated `artisanpack/navigation` edit component.
 *
 * The block was forked in Phase I5 (#413) and reverted to `core/navigation`
 * in #808. This stub exists only so persisted `<!-- wp:artisanpack/navigation
 * ... -->` markup deserializes cleanly; on mount it swaps itself for the
 * upstream `core/navigation` block, preserving `ref` + all fork attributes
 * and any inner blocks Gutenberg attached during parse.
 *
 * Inner-block safety: the near-universal shape is
 * `<!-- wp:artisanpack/navigation {"ref":N} /-->` (self-closing) — menu items
 * live on the `wp_navigation` entity via `ref`, never in the local block tree.
 * For that shape, `getBlocks(clientId)` returns `[]` throughout the stub's
 * lifetime and `[]` is the correct inner-blocks argument to `createBlock`;
 * the migrated `core/navigation` re-hydrates its own inner blocks from the
 * entity via `ref`. For the rare shape with inline children (
 * `<!-- wp:artisanpack/navigation --> ... <!-- /wp:artisanpack/navigation -->`
 * ), Gutenberg's parser populates the block tree synchronously before any
 * edit renders, so `getBlocks(clientId)` reflects the parsed children on the
 * very first render — no race. We still read inner blocks fresh via
 * `select()` inside the effect (rather than closing over the `useSelect`
 * value) as belt-and-suspenders against any post-mount reification path.
 */

import { useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect, select } from '@wordpress/data';
import { store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';

interface EditProps {
    clientId: string;
    attributes: Record<string, unknown>;
}

export default function DeprecatedNavigationEdit({
    clientId,
    attributes,
}: EditProps): JSX.Element {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const { replaceBlock } = useDispatch(blockEditorStore) as any;

    // Subscribed read — keeps the effect's dependency array reactive to
    // late-arriving inner-block reification (if any) so we don't fire the
    // migration until the block tree has settled.
    const innerBlockCount = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (sel: any) => (sel(blockEditorStore).getBlocks(clientId) as unknown[]).length,
        [clientId],
    );

    const migratedRef = useRef(false);

    useEffect(() => {
        if (migratedRef.current) return;
        migratedRef.current = true;

        // Read the current inner-block tree at effect-fire time via a
        // direct `select()` call rather than closing over `useSelect`'s
        // value — guarantees the freshest snapshot even if the
        // dependency-array trigger and effect fire were separated by an
        // intervening render that changed the tree.
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const currentInner = (select as any)(blockEditorStore).getBlocks(clientId) as unknown[];

        replaceBlock(
            clientId,
            createBlock(
                'core/navigation',
                { ...attributes },
                (currentInner ?? []) as never[],
            ),
        );
    }, [clientId, attributes, innerBlockCount, replaceBlock]);

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps as any)();

    return <div {...blockProps} />;
}
