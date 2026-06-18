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
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    PhotoGridControls,
    getPhotoGridWrapperProps,
    type PhotoGridAttribute,
} from '../_shared/photo-grid';

type GridLayoutMode = 'fixed' | 'masonry';

interface GridAttributes {
    readonly numColumns: number;
    readonly layoutMode?: GridLayoutMode;
    readonly photoGrid?: PhotoGridAttribute | null;
}

function normalizeLayoutMode( value: unknown ): GridLayoutMode {
    return 'masonry' === value ? 'masonry' : 'fixed';
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
    const layoutMode = normalizeLayoutMode(attributes.layoutMode);
    const isMasonry = 'masonry' === layoutMode;

    const photoGridWrapper = getPhotoGridWrapperProps(attributes);
    const className = [
        'ap-grid',
        `ap-grid-has-${numColumns}-base-columns`,
        isMasonry ? 'ap-grid-layout-masonry' : 'ap-grid-layout-fixed',
        photoGridWrapper.className,
    ]
        .filter(Boolean)
        .join(' ');

    // Editor canvas preview path:
    //   - Browsers that ship native `grid-template-rows: masonry`
    //     render the canvas with true packed layout via `@supports`.
    //   - Other browsers see a regular columned grid in the canvas —
    //     same as the fixed-grid mode. We deliberately do NOT run the
    //     JS fallback inside Gutenberg's editor canvas: freshly-
    //     inserted grid-items are empty, so the fallback measures
    //     their height as 0 and collapses the wrapper. The public
    //     frontend's masonry-fallback bootstrap packs items at render
    //     time for both groups, so the published page is always packed.
    const blockProps = useBlockProps({ className, style: photoGridWrapper.style });
    const outerProps: Record<string, unknown> = { ...blockProps };
    if (isMasonry) {
        outerProps['data-ap-cols'] = numColumns;
    }

    const innerBlocksProps = useInnerBlocksProps(outerProps, {
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
                    <ToggleControl
                        label={__('Masonry layout', TEXT_DOMAIN)}
                        help={
                            isMasonry
                                ? __(
                                      'Items pack into columns by height. Row spans on grid items are ignored.',
                                      TEXT_DOMAIN
                                  )
                                : __(
                                      'Toggle on to pack items into columns with variable heights.',
                                      TEXT_DOMAIN
                                  )
                        }
                        checked={isMasonry}
                        onChange={(next) =>
                            setAttributes({ layoutMode: next ? 'masonry' : 'fixed' })
                        }
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
