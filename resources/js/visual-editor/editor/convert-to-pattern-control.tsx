/**
 * "Convert to pattern" block-settings menu control.
 *
 * Mounts in the BlockSettingsMenuControls slot so the action appears in
 * the three-dot menu of every selected block (single or multi-select)
 * across both editor surfaces. Selecting it opens
 * `CreatePatternDialog` pre-filled with the selected blocks; the
 * dialog asks for a sync choice (synced / unsynced) and persists the
 * pattern via C5.
 *
 * On success:
 *   - For a synced pattern, the source selection is replaced with a
 *     `core/block` reference so the original location now points at
 *     the synced pattern (matches Gutenberg / WP behaviour).
 *   - For an unsynced pattern, the source selection is left untouched
 *     — the new pattern is just a saved copy of the same blocks.
 */

import {
    BlockSettingsMenuControls,
} from '@wordpress/block-editor';
import { createBlock, type BlockInstance } from '@wordpress/blocks';
import { MenuItem } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useCallback, useState } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';
import { CreatePatternDialog } from '../site-editor/patterns/create-pattern-dialog';

export interface ConvertToPatternControlProps {
    /** Base URL for the visual-editor API (e.g. `/visual-editor/api`). */
    apiBase: string;
}

interface BlockEditorSelectors {
    getBlocksByClientId?: (
        clientIds: readonly string[]
    ) => readonly BlockInstance[];
    getBlock?: (clientId: string) => BlockInstance | null;
}

interface BlockEditorActions {
    replaceBlocks?: (
        clientIds: readonly string[],
        blocks: readonly BlockInstance[]
    ) => void;
}

interface CoreDataActions {
    receiveEntityRecords?: (
        kind: string,
        name: string,
        records: readonly Record<string, unknown>[]
    ) => void;
}

function suggestNameFromBlocks(blocks: readonly BlockInstance[]): string {
    if (blocks.length === 1) {
        const sole = blocks[0];

        if (sole !== undefined) {
            const name = typeof sole.name === 'string' ? sole.name : '';
            const stripped = name.replace(/^core\//, '');

            return stripped
                .replace(/-/g, ' ')
                .replace(/\b\w/g, (char: string) => char.toUpperCase());
        }
    }

    return '';
}

export function ConvertToPatternControl(
    props: ConvertToPatternControlProps
): JSX.Element {
    const { apiBase } = props;

    const [open, setOpen] = useState(false);
    const [snapshot, setSnapshot] = useState<{
        blocks: readonly BlockInstance[];
        clientIds: readonly string[];
    } | null>(null);

    const apiConfig = { apiBase };

    const blockEditorActions = useDispatch('core/block-editor') as
        | BlockEditorActions
        | null
        | undefined;
    const replaceBlocks = blockEditorActions?.replaceBlocks;

    const coreActions = useDispatch('core') as
        | CoreDataActions
        | null
        | undefined;
    const receiveEntityRecords = coreActions?.receiveEntityRecords;

    const getBlocksByClientId = useSelect(
        (select): BlockEditorSelectors['getBlocksByClientId'] => {
            const store = select('core/block-editor') as
                | BlockEditorSelectors
                | undefined;

            return store?.getBlocksByClientId;
        },
        []
    );

    const getBlock = useSelect(
        (select): BlockEditorSelectors['getBlock'] => {
            const store = select('core/block-editor') as
                | BlockEditorSelectors
                | undefined;

            return store?.getBlock;
        },
        []
    );

    const handleOpen = useCallback(
        (
            onClose: () => void,
            selectedBlockClientIds: readonly string[]
        ): void => {
            if (selectedBlockClientIds.length === 0) {
                onClose();
                return;
            }

            const blocks = getBlocksByClientId?.(selectedBlockClientIds) ?? [];

            // Some `BlockEdit` paths can return `null` for clientIds
            // that are mid-replace (e.g. inside a sequenced delete).
            // Strip those so we don't try to serialize a null shaped
            // tree.
            const cleaned = blocks.filter(
                (block): block is BlockInstance => block !== null
            );

            if (cleaned.length === 0) {
                onClose();
                return;
            }

            setSnapshot({ blocks: cleaned, clientIds: selectedBlockClientIds });
            setOpen(true);
            onClose();
        },
        [getBlocksByClientId]
    );

    return (
        <>
            <BlockSettingsMenuControls>
                {(slot: {
                    onClose: () => void;
                    selectedBlockClientIds: readonly string[];
                }) => (
                    <MenuItem
                        data-testid="ap-convert-to-pattern-menu-item"
                        onClick={() =>
                            handleOpen(
                                slot.onClose,
                                slot.selectedBlockClientIds
                            )
                        }
                    >
                        {__('Convert to pattern', TEXT_DOMAIN)}
                    </MenuItem>
                )}
            </BlockSettingsMenuControls>
            {open && snapshot !== null ? (
                <CreatePatternDialog
                    apiConfig={apiConfig}
                    initialSync={null}
                    sourceBlocks={snapshot.blocks}
                    initialName={suggestNameFromBlocks(snapshot.blocks)}
                    onClose={() => {
                        setOpen(false);
                        setSnapshot(null);
                    }}
                    onCreated={(record, info) => {
                        if (info.sync === 'synced') {
                            // Push the freshly-created pattern into the
                            // core-data shim's cache before the
                            // `core/block` reference renders. Skipping
                            // this step makes the new reference render
                            // a "Block has been deleted or is
                            // unavailable" placeholder until the user
                            // reloads the editor.
                            if (
                                typeof receiveEntityRecords === 'function'
                            ) {
                                receiveEntityRecords(
                                    'postType',
                                    'wp_block',
                                    [
                                        record as unknown as Record<
                                            string,
                                            unknown
                                        >,
                                    ]
                                );
                            }

                            if (typeof replaceBlocks === 'function') {
                                // The snapshot was taken when the
                                // dialog opened. By the time the user
                                // confirms, those blocks may have been
                                // moved, deleted, or otherwise
                                // replaced (e.g. via a parallel
                                // selection, undo/redo). Filter to the
                                // ids that still exist so
                                // `replaceBlocks` doesn't throw on a
                                // stale clientId.
                                const liveIds =
                                    typeof getBlock === 'function'
                                        ? snapshot.clientIds.filter(
                                              (clientId) =>
                                                  getBlock(clientId) !== null
                                          )
                                        : snapshot.clientIds;

                                if (liveIds.length > 0) {
                                    const ref = createBlock('core/block', {
                                        ref: record.id,
                                    });

                                    replaceBlocks(liveIds, [ref]);
                                }
                            }
                        }

                        setOpen(false);
                        setSnapshot(null);
                    }}
                />
            ) : null}
        </>
    );
}
