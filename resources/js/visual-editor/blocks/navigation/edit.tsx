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
 *
 * Lock safety: upstream `replaceBlocks` silently no-ops when
 * `canInsertBlockType('core/navigation', rootClientId)` is false (parent
 * `templateLock` of `all` / `insert`, a `disabled` / `contentOnly` editing
 * mode, preview mode). The automatic migration therefore only fires when the
 * replacement is allowed, and is only marked done once the stub has actually
 * left the block tree. When it cannot migrate, the stub renders a visible
 * `Warning` explaining the legacy format — with an explicit "Update block"
 * action when the author can edit the block — instead of an empty div.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useRegistry, useSelect } from '@wordpress/data';
import {
    store as blockEditorStore,
    useBlockProps,
    Warning,
} from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface EditProps {
    clientId: string;
    attributes: Record<string, unknown>;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyRegistry = any;

/**
 * Build the replacement `core/navigation` block from the stub's current
 * attributes and the freshest inner-block snapshot in `registry`.
 */
function buildReplacement(
    registry: AnyRegistry,
    clientId: string,
    attributes: Record<string, unknown>,
): unknown {
    const currentInner = registry
        .select(blockEditorStore)
        .getBlocks(clientId) as unknown[];

    return createBlock(
        'core/navigation',
        { ...attributes },
        (currentInner ?? []) as never[],
    );
}

export default function DeprecatedNavigationEdit({
    clientId,
    attributes,
}: EditProps): JSX.Element {
    // Bind to the active `RegistryProvider` (site editor's iframe uses a
    // scoped `@wordpress/data` registry). Reading via the default global
    // registry would miss the scoped store entirely and mint the
    // replacement as if it had no inner blocks — permanently dropping any
    // inline children on migration.
    const registry = useRegistry() as AnyRegistry;

    // Subscribed reads — keep the effect reactive to late-arriving
    // inner-block reification and to the parent's block-list settings /
    // lock state settling (a nested block's parent registers its settings
    // after the child's first render, so `canInsertBlockType` may flip
    // from false to true).
    const { innerBlockCount, canMigrate, canForceUpdate } = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (sel: any) => {
            const editor = sel(blockEditorStore);
            const rootClientId = editor.getBlockRootClientId(clientId);

            return {
                innerBlockCount: (editor.getBlocks(clientId) as unknown[]).length,
                canMigrate: Boolean(
                    editor.canInsertBlockType('core/navigation', rootClientId),
                ),
                // The explicit action swaps the stub inside its parent's
                // inner-block list, which is only safe for a nested block
                // the author can fully edit.
                canForceUpdate:
                    Boolean(rootClientId) &&
                    'default' === editor.getBlockEditingMode(clientId) &&
                    !editor.getSettings()?.isPreviewMode,
            };
        },
        [clientId],
    );

    const migratedRef = useRef(false);
    const [migrationFailed, setMigrationFailed] = useState(false);

    useEffect(() => {
        if (migratedRef.current || !canMigrate) {
            return;
        }

        registry
            .dispatch(blockEditorStore)
            .replaceBlock(clientId, buildReplacement(registry, clientId, attributes));

        // `replaceBlocks` is a synchronous thunk: if the stub is still in
        // the tree, the replacement was refused. Leave the migration
        // un-marked so a later lock / settings change can retry it.
        if (registry.select(blockEditorStore).getBlock(clientId)) {
            setMigrationFailed(true);
            return;
        }

        migratedRef.current = true;
    }, [clientId, attributes, innerBlockCount, canMigrate, registry]);

    const forceUpdate = useCallback(() => {
        const editor = registry.select(blockEditorStore);
        const rootClientId = editor.getBlockRootClientId(clientId);

        if (!rootClientId) {
            return;
        }

        const replacement = buildReplacement(registry, clientId, attributes);
        const siblings = editor.getBlocks(rootClientId) as Array<{ clientId: string }>;

        // `replaceInnerBlocks` swaps the stub for its successor in place
        // without going through `canInsertBlockType`, so an inherited
        // `templateLock` cannot strand the legacy block forever. The
        // successor occupies the same slot, so the parent's structure is
        // unchanged.
        registry.dispatch(blockEditorStore).replaceInnerBlocks(
            rootClientId,
            siblings.map((block) => (block.clientId === clientId ? replacement : block)),
        );

        if (!registry.select(blockEditorStore).getBlock(clientId)) {
            migratedRef.current = true;
        }
    }, [clientId, attributes, registry]);

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps as any)();

    if (!canMigrate || migrationFailed) {
        const actions = canForceUpdate
            ? [
                  <Button key="update" variant="primary" onClick={forceUpdate}>
                      {__('Update block', TEXT_DOMAIN)}
                  </Button>,
              ]
            : [];

        return (
            <div {...blockProps}>
                <Warning actions={actions}>
                    {canForceUpdate
                        ? __(
                              'This navigation block uses a legacy format and could not be updated automatically. Update it to the current Navigation block to edit it.',
                              TEXT_DOMAIN,
                          )
                        : __(
                              'This navigation block uses a legacy format and could not be updated automatically because its location is locked. Unlock the surrounding block to update it.',
                              TEXT_DOMAIN,
                          )}
                </Warning>
            </div>
        );
    }

    return <div {...blockProps} />;
}
