/**
 * Column — edit component.
 *
 * Ported from `@wordpress/block-library/src/column/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-column` class to `useBlockProps` so the
 * editor canvas matches the front-end.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    InnerBlocks,
    BlockControls,
    BlockVerticalAlignmentToolbar,
    InspectorControls,
    useBlockProps,
    useSettings,
    useInnerBlocksProps,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import {
    __experimentalUseCustomUnits as useCustomUnits,
    __experimentalUnitControl as UnitControl,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { sprintf, __ } from '@wordpress/i18n';

interface ColumnAttributes {
    readonly verticalAlignment?: string;
    readonly width?: string;
    readonly templateLock?: string | boolean;
    readonly allowedBlocks?: string[];
}

interface ColumnEditProps {
    attributes: ColumnAttributes;
    setAttributes: (attrs: Partial<ColumnAttributes>) => void;
    clientId: string;
}

function ColumnInspectorControls({
    width,
    setAttributes,
}: {
    width?: string;
    setAttributes: (attrs: Partial<ColumnAttributes>) => void;
}): ReactElement {
    const [availableUnits] = (useSettings as any)('spacing.units');
    const units = (useCustomUnits as any)({
        availableUnits: availableUnits || ['%', 'px', 'em', 'rem', 'vw'],
    });
    return (
        <ToolsPanel
            label={__('Settings')}
            resetAll={() => setAttributes({ width: undefined })}
        >
            <ToolsPanelItem
                hasValue={() => width !== undefined}
                label={__('Width')}
                onDeselect={() => setAttributes({ width: undefined })}
                isShownByDefault
            >
                <UnitControl
                    label={__('Width')}
                    // @ts-expect-error - upstream prop
                    __unstableInputWidth="calc(50% - 8px)"
                    // @ts-expect-error - upstream prop
                    __next40pxDefaultSize
                    value={width || ''}
                    onChange={(nextWidth: string) => {
                        const adjusted =
                            0 > parseFloat(nextWidth) ? '0' : nextWidth;
                        setAttributes({ width: adjusted });
                    }}
                    units={units}
                />
            </ToolsPanelItem>
        </ToolsPanel>
    );
}

export default function ColumnEdit({
    attributes: { verticalAlignment, width, templateLock, allowedBlocks },
    setAttributes,
    clientId,
}: ColumnEditProps): ReactElement {
    const classes = clsx('wp-block-column', {
        [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
    });
    const { columnsIds, hasChildBlocks, rootClientId } = useSelect(
        (select: any) => {
            const { getBlockOrder, getBlockRootClientId } =
                select(blockEditorStore);
            const rootId = getBlockRootClientId(clientId);
            return {
                hasChildBlocks: getBlockOrder(clientId).length > 0,
                rootClientId: rootId,
                columnsIds: getBlockOrder(rootId),
            };
        },
        [clientId]
    );

    const { updateBlockAttributes } = useDispatch(blockEditorStore) as any;

    const updateAlignment = (value: string | null): void => {
        setAttributes({ verticalAlignment: value ?? undefined });
        updateBlockAttributes(rootClientId, { verticalAlignment: null });
    };

    const widthWithUnit = Number.isFinite(width as unknown as number)
        ? width + '%'
        : width;
    const blockProps = (useBlockProps as any)({
        className: classes,
        style: widthWithUnit ? { flexBasis: widthWithUnit } : undefined,
    });

    const columnsCount = columnsIds.length;
    const currentColumnPosition = columnsIds.indexOf(clientId) + 1;

    const label = sprintf(
        /* translators: 1: Block label, 2: Position, 3: Total */
        __('%1$s (%2$d of %3$d)'),
        blockProps['aria-label'],
        currentColumnPosition,
        columnsCount
    );

    const innerBlocksProps = (useInnerBlocksProps as any)(
        { ...blockProps, 'aria-label': label },
        {
            templateLock,
            allowedBlocks,
            renderAppender: hasChildBlocks
                ? undefined
                : (InnerBlocks as any).ButtonBlockAppender,
        }
    );

    return (
        <>
            <BlockControls>
                <BlockVerticalAlignmentToolbar
                    onChange={updateAlignment}
                    value={verticalAlignment}
                    controls={['top', 'center', 'bottom', 'stretch']}
                />
            </BlockControls>
            <InspectorControls>
                <ColumnInspectorControls
                    width={width}
                    setAttributes={setAttributes}
                />
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
