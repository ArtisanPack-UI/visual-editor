/**
 * Grid Item — editor-side component (#498).
 *
 * Child of `artisanpack/grid`. Carries `gridColumnSpan` and `gridRowSpan`
 * attributes plus an `innerLayout` enum that maps onto a flexbox
 * arrangement of its grandchildren.
 *
 * The span attributes are enrolled in `artisanpackResponsive.attributes`
 * so the HOC surfaces a per-breakpoint range control automatically and
 * merges the active breakpoint's override into the base attribute for
 * the editor canvas preview. The column-span max is clamped to the
 * parent grid's resolved `numColumns` via block context.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    FlexContainerControls,
    FlexItemControls,
    serializeFlex,
    type ArtisanpackFlexAttribute,
} from '../_shared/flex-controls';
import { BreakpointRegistry } from '../../responsive/registry';

type InnerLayout = 'normal' | 'equal' | 'center' | 'bottom' | 'last-bottom';

const VALID_INNER_LAYOUTS: ReadonlyArray<InnerLayout> = [
    'normal',
    'equal',
    'center',
    'bottom',
    'last-bottom',
];

interface GridItemAttributes {
    readonly innerLayout: InnerLayout;
    readonly gridColumnSpan: number;
    readonly gridRowSpan: number;
    readonly artisanpackFlex?: ArtisanpackFlexAttribute | null;
}

interface GridItemContext {
    readonly numColumns?: number;
    readonly 'artisanpack/gridLayoutMode'?: string;
}

interface GridItemEditProps {
    readonly attributes: GridItemAttributes;
    readonly setAttributes: (next: Partial<GridItemAttributes>) => void;
    readonly context: GridItemContext;
    readonly clientId: string;
}

function clampSpan(value: number | undefined, max: number, fallback: number): number {
    const next = typeof value === 'number' && Number.isFinite(value) ? Math.trunc(value) : fallback;
    if (next < 1) {
        return 1;
    }
    if (next > max) {
        return max;
    }
    return next;
}

export default function GridItemEdit({
    attributes,
    setAttributes,
    context,
    clientId,
}: GridItemEditProps): ReactElement {
    const numColumns = clampSpan(context.numColumns, 12, 12);
    const gridColumnSpan = clampSpan(attributes.gridColumnSpan, numColumns, 1);
    const gridRowSpan = clampSpan(attributes.gridRowSpan, 12, 1);
    const innerLayout = (VALID_INNER_LAYOUTS as ReadonlyArray<string>).includes(
        attributes.innerLayout
    )
        ? attributes.innerLayout
        : 'normal';
    const isMasonryParent = 'masonry' === context['artisanpack/gridLayoutMode'];

    const flexRegistry = new BreakpointRegistry();
    const flexResult = serializeFlex(
        attributes.artisanpackFlex ?? null,
        flexRegistry,
    );

    const className = [
        'ap-grid-item',
        `ap-grid-item-layout-${innerLayout}`,
        `ap-grid-item-span-${gridColumnSpan}-base-columns`,
        `ap-grid-item-span-${gridRowSpan}-base-row`,
        ...flexResult.classes,
    ].join(' ');

    const blockProps = useBlockProps({ className });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {});

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Grid Item Settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Inner Layout', TEXT_DOMAIN)}
                        value={innerLayout}
                        options={[
                            { value: 'normal', label: __('Normal Spacing', TEXT_DOMAIN) },
                            { value: 'equal', label: __('Equal Spacing', TEXT_DOMAIN) },
                            { value: 'center', label: __('Center Align', TEXT_DOMAIN) },
                            { value: 'bottom', label: __('All Bottom', TEXT_DOMAIN) },
                            { value: 'last-bottom', label: __('Last Item at Bottom', TEXT_DOMAIN) },
                        ]}
                        onChange={(value) => {
                            if ((VALID_INNER_LAYOUTS as ReadonlyArray<string>).includes(value)) {
                                setAttributes({ innerLayout: value as InnerLayout });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Column Span', TEXT_DOMAIN)}
                        help={__('How many grid columns this item spans.', TEXT_DOMAIN)}
                        value={gridColumnSpan}
                        onChange={(value) =>
                            setAttributes({ gridColumnSpan: clampSpan(value, numColumns, 1) })
                        }
                        min={1}
                        max={numColumns}
                        allowReset
                        resetFallbackValue={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Row Span', TEXT_DOMAIN)}
                        help={
                            isMasonryParent
                                ? __(
                                      "Row span doesn't apply in masonry layouts — rows pack automatically.",
                                      TEXT_DOMAIN
                                  )
                                : __('How many grid rows this item spans.', TEXT_DOMAIN)
                        }
                        value={gridRowSpan}
                        onChange={(value) =>
                            setAttributes({ gridRowSpan: clampSpan(value, 12, 1) })
                        }
                        min={1}
                        max={12}
                        allowReset
                        resetFallbackValue={1}
                        disabled={isMasonryParent}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <FlexContainerControls
                    flex={attributes.artisanpackFlex ?? null}
                    onChange={(next) =>
                        setAttributes({ artisanpackFlex: next })
                    }
                    registry={flexRegistry}
                />
                <FlexItemControls
                    flex={attributes.artisanpackFlex ?? null}
                    clientId={clientId}
                    onChange={(next) =>
                        setAttributes({ artisanpackFlex: next })
                    }
                    registry={flexRegistry}
                />
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
