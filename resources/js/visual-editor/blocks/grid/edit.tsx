/**
 * Grid — editor-side component (#498).
 *
 * Parent block of the grid family. Hosts one or more
 * `artisanpack/grid-item` children via `useInnerBlocksProps` and
 * provides the resolved column count down to each item via block
 * context so item inspectors can clamp their span ranges to the
 * grid's actual column count.
 *
 * Per-breakpoint column count is encoded as a static class
 * (`ap-grid-has-N-{bp}-columns`) that the matching media-query rule in
 * `grid.css` picks up. The `artisanpackResponsive` HOC merges the
 * active breakpoint's override into `numColumns` so the editor canvas
 * shows the live preview as the author switches breakpoints.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    PhotoGridControls,
    getPhotoGridWrapperProps,
    type PhotoGridAttribute,
} from '../_shared/photo-grid';

interface GridAttributes {
    readonly numColumns: number;
    readonly photoGrid?: PhotoGridAttribute | null;
}

interface GridEditProps {
    readonly attributes: GridAttributes;
    readonly setAttributes: (next: Partial<GridAttributes>) => void;
}

const ALLOWED_BLOCKS: string[] = ['artisanpack/grid-item'];

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/grid-item', {}],
    ['artisanpack/grid-item', {}],
    ['artisanpack/grid-item', {}],
    ['artisanpack/grid-item', {}],
];

function clampColumns(value: number | undefined, fallback: number): number {
    const next = typeof value === 'number' && Number.isFinite(value) ? Math.trunc(value) : fallback;
    if (next < 1) {
        return 1;
    }
    if (next > 12) {
        return 12;
    }
    return next;
}

export default function GridEdit({ attributes, setAttributes }: GridEditProps): ReactElement {
    const numColumns = clampColumns(attributes.numColumns, 4);

    const photoGridWrapper = getPhotoGridWrapperProps(attributes);
    const className = [
        'ap-grid',
        `ap-grid-has-${numColumns}-base-columns`,
        photoGridWrapper.className,
    ]
        .filter(Boolean)
        .join(' ');
    const blockProps = useBlockProps({ className, style: photoGridWrapper.style });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        allowedBlocks: ALLOWED_BLOCKS,
        template: TEMPLATE,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Grid Settings', TEXT_DOMAIN)} initialOpen>
                    <RangeControl
                        label={__('Columns', TEXT_DOMAIN)}
                        value={numColumns}
                        onChange={(value) =>
                            setAttributes({ numColumns: clampColumns(value, 4) })
                        }
                        min={1}
                        max={12}
                        allowReset
                        resetFallbackValue={4}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PhotoGridControls
                    photoGrid={attributes.photoGrid ?? null}
                    onChange={(next) => setAttributes({ photoGrid: next })}
                />
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
