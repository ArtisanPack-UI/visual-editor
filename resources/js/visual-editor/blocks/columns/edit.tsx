/**
 * Columns — edit component.
 *
 * Ported from `@wordpress/block-library/src/columns/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-columns` class to `useBlockProps` so the
 * editor canvas matches the front-end. Swaps `core/column` inner block
 * defaults/allowedBlocks for the `artisanpack/column` fork.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import {
    Notice,
    RangeControl,
    ToggleControl,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
    __experimentalVStack as VStack,
} from '@wordpress/components';
import {
    InspectorControls,
    useInnerBlocksProps,
    BlockControls,
    BlockVerticalAlignmentToolbar,
    __experimentalBlockVariationPicker,
    useBlockProps,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import { useDispatch, useSelect, useRegistry } from '@wordpress/data';
import {
    createBlock,
    createBlocksFromInnerBlocksTemplate,
    store as blocksStore,
} from '@wordpress/blocks';

import {
    hasExplicitPercentColumnWidths,
    getMappedColumnWidths,
    getRedistributedColumnWidths,
    toWidthPrecision,
} from './utils';

const COLUMN_BLOCK = 'artisanpack/column';
const DEFAULT_BLOCK = { name: COLUMN_BLOCK };

interface ColumnsAttributes {
    readonly verticalAlignment?: string;
    readonly isStackedOnMobile?: boolean;
    readonly templateLock?: string | boolean;
}

interface ColumnsEditProps {
    attributes: ColumnsAttributes;
    setAttributes: (attrs: Partial<ColumnsAttributes>) => void;
    clientId: string;
    name: string;
}

function ColumnInspectorControls({
    clientId,
    setAttributes,
    isStackedOnMobile,
}: {
    clientId: string;
    setAttributes: (attrs: Partial<ColumnsAttributes>) => void;
    isStackedOnMobile?: boolean;
}): ReactElement {
    const { count, canInsertColumnBlock, minCount } = useSelect(
        (select: any) => {
            const { canInsertBlockType, canRemoveBlock, getBlockOrder } =
                select(blockEditorStore);
            const blockOrder = getBlockOrder(clientId);
            const preventRemovalBlockIndexes = blockOrder.reduce(
                (acc: number[], blockId: string, index: number) => {
                    if (!canRemoveBlock(blockId)) {
                        acc.push(index);
                    }
                    return acc;
                },
                []
            );
            return {
                count: blockOrder.length,
                canInsertColumnBlock: canInsertBlockType(
                    COLUMN_BLOCK,
                    clientId
                ),
                minCount: Math.max(...preventRemovalBlockIndexes) + 1,
            };
        },
        [clientId]
    );
    const { getBlocks } = useSelect(blockEditorStore) as any;
    const { replaceInnerBlocks } = useDispatch(blockEditorStore) as any;

    function updateColumns(previousColumns: number, newColumns: number): void {
        let innerBlocks = getBlocks(clientId);
        const hasExplicitWidths = hasExplicitPercentColumnWidths(innerBlocks);
        const isAddingColumn = newColumns > previousColumns;

        if (isAddingColumn && hasExplicitWidths) {
            const newColumnWidth = toWidthPrecision(100 / newColumns) ?? 0;
            const newlyAddedColumns = newColumns - previousColumns;
            const widths = getRedistributedColumnWidths(
                innerBlocks,
                100 - newColumnWidth * newlyAddedColumns
            );
            innerBlocks = [
                ...getMappedColumnWidths(innerBlocks, widths),
                ...Array.from({ length: newlyAddedColumns }).map(() =>
                    createBlock(COLUMN_BLOCK, {
                        width: `${newColumnWidth}%`,
                    })
                ),
            ];
        } else if (isAddingColumn) {
            innerBlocks = [
                ...innerBlocks,
                ...Array.from({
                    length: newColumns - previousColumns,
                }).map(() => createBlock(COLUMN_BLOCK)),
            ];
        } else if (newColumns < previousColumns) {
            innerBlocks = innerBlocks.slice(
                0,
                -(previousColumns - newColumns)
            );
            if (hasExplicitWidths) {
                const widths = getRedistributedColumnWidths(innerBlocks, 100);
                innerBlocks = getMappedColumnWidths(innerBlocks, widths);
            }
        }

        replaceInnerBlocks(clientId, innerBlocks);
    }

    return (
        <ToolsPanel
            label={__('Settings')}
            resetAll={() => setAttributes({ isStackedOnMobile: true })}
        >
            {canInsertColumnBlock && (
                <VStack spacing={4} style={{ gridColumn: '1 / -1' }}>
                    <RangeControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        label={__('Columns')}
                        value={count}
                        onChange={(value: number) =>
                            updateColumns(count, Math.max(minCount, value))
                        }
                        min={Math.max(1, minCount)}
                        max={Math.max(6, count)}
                    />
                    {count > 6 && (
                        <Notice status="warning" isDismissible={false}>
                            {__(
                                'This column count exceeds the recommended amount and may cause visual breakage.'
                            )}
                        </Notice>
                    )}
                </VStack>
            )}
            <ToolsPanelItem
                label={__('Stack on mobile')}
                isShownByDefault
                hasValue={() => isStackedOnMobile !== true}
                onDeselect={() => setAttributes({ isStackedOnMobile: true })}
            >
                <ToggleControl
                    // @ts-expect-error - upstream prop
                    __nextHasNoMarginBottom
                    label={__('Stack on mobile')}
                    checked={!!isStackedOnMobile}
                    onChange={() =>
                        setAttributes({
                            isStackedOnMobile: !isStackedOnMobile,
                        })
                    }
                />
            </ToolsPanelItem>
        </ToolsPanel>
    );
}

function ColumnsEditContainer({
    attributes,
    setAttributes,
    clientId,
}: ColumnsEditProps): ReactElement {
    const { isStackedOnMobile, verticalAlignment, templateLock } = attributes;
    const registry = useRegistry();
    const { getBlockOrder } = useSelect(blockEditorStore) as any;
    const { updateBlockAttributes } = useDispatch(blockEditorStore) as any;

    const classes = clsx('wp-block-columns', {
        [`are-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        ['is-not-stacked-on-mobile']: !isStackedOnMobile,
    });

    const blockProps = (useBlockProps as any)({ className: classes });
    const innerBlocksProps = (useInnerBlocksProps as any)(blockProps, {
        defaultBlock: DEFAULT_BLOCK,
        directInsert: true,
        orientation: 'horizontal',
        renderAppender: false,
        templateLock,
    });

    function updateAlignment(newVerticalAlignment: string | null): void {
        const innerBlockClientIds = getBlockOrder(clientId);
        registry.batch(() => {
            setAttributes({
                verticalAlignment: newVerticalAlignment ?? undefined,
            });
            updateBlockAttributes(innerBlockClientIds, {
                verticalAlignment: newVerticalAlignment,
            });
        });
    }

    return (
        <>
            <BlockControls>
                <BlockVerticalAlignmentToolbar
                    onChange={updateAlignment}
                    value={verticalAlignment}
                />
            </BlockControls>
            <InspectorControls>
                <ColumnInspectorControls
                    clientId={clientId}
                    setAttributes={setAttributes}
                    isStackedOnMobile={isStackedOnMobile}
                />
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}

function Placeholder({
    clientId,
    name,
    setAttributes,
}: ColumnsEditProps): ReactElement {
    const { blockType, defaultVariation, variations } = useSelect(
        (select: any) => {
            const {
                getBlockVariations,
                getBlockType,
                getDefaultBlockVariation,
            } = select(blocksStore);
            return {
                blockType: getBlockType(name),
                defaultVariation: getDefaultBlockVariation(name, 'block'),
                variations: getBlockVariations(name, 'block'),
            };
        },
        [name]
    );
    const { replaceInnerBlocks } = useDispatch(blockEditorStore) as any;
    const blockProps = (useBlockProps as any)({ className: 'wp-block-columns' });

    return (
        <div {...blockProps}>
            <__experimentalBlockVariationPicker
                icon={blockType?.icon?.src}
                label={blockType?.title}
                variations={variations}
                instructions={__('Divide into columns. Select a layout:')}
                onSelect={(nextVariation: any = defaultVariation) => {
                    if (nextVariation?.attributes) {
                        setAttributes(nextVariation.attributes);
                    }
                    if (nextVariation?.innerBlocks) {
                        replaceInnerBlocks(
                            clientId,
                            createBlocksFromInnerBlocksTemplate(
                                nextVariation.innerBlocks
                            ),
                            true
                        );
                    }
                }}
                allowSkip
            />
        </div>
    );
}

export default function ColumnsEdit(props: ColumnsEditProps): ReactElement {
    const { clientId } = props;
    const hasInnerBlocks = useSelect(
        (select: any) =>
            select(blockEditorStore).getBlocks(clientId).length > 0,
        [clientId]
    );
    const Component = hasInnerBlocks ? ColumnsEditContainer : Placeholder;
    return <Component {...props} />;
}
