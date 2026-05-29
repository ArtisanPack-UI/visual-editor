/**
 * Tag Cloud — edit component.
 *
 * `core/tag-cloud` is server-rendered. Upstream's `edit.js` calls
 * `getTaxonomies({ per_page: -1 })` to populate a taxonomy picker, but the
 * post editor's core-data shim does not implement that selector and this
 * package resolves tags on the server (see
 * `src/Blocks/Core/TagCloudBlock.php`). The fork therefore previews
 * through the package's `<ServerSideRender>` seam — the same approach the
 * V1 taxonomy/archive override uses — and ports the inspector controls to
 * `PanelBody`. Phase I6 loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    SelectControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ServerSideRender } from '../../editor/server-side-render';
import { TEXT_DOMAIN } from '../../vendor/i18n';

// Typed as `{ label: string; value: string }[]` so TS does not narrow the
// option values to literal unions — that narrowing collides with the
// broader `string` type the block attributes carry.
const SMALLEST_SIZE_OPTIONS: { label: string; value: string }[] = [
    { label: '8pt', value: '8pt' },
    { label: '10pt', value: '10pt' },
    { label: '12pt', value: '12pt' },
];

const LARGEST_SIZE_OPTIONS: { label: string; value: string }[] = [
    { label: '16pt', value: '16pt' },
    { label: '22pt', value: '22pt' },
    { label: '28pt', value: '28pt' },
];

interface TagCloudAttributes {
    readonly numberOfTags?: number;
    readonly showTagCounts?: boolean;
    readonly smallestFontSize?: string;
    readonly largestFontSize?: string;
    readonly [ key: string ]: unknown;
}

interface TagCloudEditProps {
    readonly attributes: TagCloudAttributes;
    readonly setAttributes: ( attrs: Partial<TagCloudAttributes> ) => void;
}

export default function TagCloudEdit( {
    attributes,
    setAttributes,
}: TagCloudEditProps ): ReactElement {
    const numberOfTags =
        typeof attributes.numberOfTags === 'number' ? attributes.numberOfTags : 45;
    const showTagCounts = Boolean( attributes.showTagCounts );
    const smallestFontSize =
        typeof attributes.smallestFontSize === 'string'
            ? attributes.smallestFontSize
            : '8pt';
    const largestFontSize =
        typeof attributes.largestFontSize === 'string'
            ? attributes.largestFontSize
            : '22pt';

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <RangeControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                        label={ __( 'Number of tags', TEXT_DOMAIN ) }
                        min={ 1 }
                        max={ 100 }
                        value={ numberOfTags }
                        onChange={ ( value?: number ) =>
                            setAttributes( { numberOfTags: value ?? 45 } )
                        }
                    />
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show tag counts', TEXT_DOMAIN ) }
                        checked={ showTagCounts }
                        onChange={ ( value ) =>
                            setAttributes( { showTagCounts: value } )
                        }
                    />
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Smallest size', TEXT_DOMAIN ) }
                        value={ smallestFontSize }
                        options={ SMALLEST_SIZE_OPTIONS }
                        onChange={ ( value: string ) =>
                            setAttributes( { smallestFontSize: value } )
                        }
                    />
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Largest size', TEXT_DOMAIN ) }
                        value={ largestFontSize }
                        options={ LARGEST_SIZE_OPTIONS }
                        onChange={ ( value: string ) =>
                            setAttributes( { largestFontSize: value } )
                        }
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <ServerSideRender
                    block="artisanpack/tag-cloud"
                    attributes={ attributes }
                />
            </div>
        </>
    );
}
