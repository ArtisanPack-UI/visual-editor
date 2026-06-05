/**
 * Archives — edit component.
 *
 * `core/archives` is server-rendered. Upstream's `edit.js` renders through
 * Gutenberg's `@wordpress/server-side-render`, which POSTs to
 * `wp/v2/block-renderer/core/archives`; this package's preview endpoint
 * lives at `/visual-editor/api/blocks/preview` instead and archives are
 * resolved on the server (see `src/Blocks/Core/ArchivesBlock.php`). The
 * fork therefore previews through the package's `<ServerSideRender>` seam
 * — the same approach the V1 taxonomy/archive override uses — and ports
 * the inspector controls to `PanelBody`. Phase I6 loop / feed
 * cluster (#414).
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ServerSideRender } from '../../editor/server-side-render';
import { TEXT_DOMAIN } from '../../vendor/i18n';

interface ArchivesAttributes {
    readonly displayAsDropdown?: boolean;
    readonly showPostCounts?: boolean;
    readonly type?: string;
    readonly [ key: string ]: unknown;
}

interface ArchivesEditProps {
    readonly attributes: ArchivesAttributes;
    readonly setAttributes: ( attrs: Partial<ArchivesAttributes> ) => void;
}

export default function ArchivesEdit( {
    attributes,
    setAttributes,
}: ArchivesEditProps ): ReactElement {
    const displayAsDropdown = Boolean( attributes.displayAsDropdown );
    const showPostCounts = Boolean( attributes.showPostCounts );
    const type = attributes.type === 'yearly' ? 'yearly' : 'monthly';

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Display as dropdown', TEXT_DOMAIN ) }
                        checked={ displayAsDropdown }
                        onChange={ ( value ) =>
                            setAttributes( { displayAsDropdown: value } )
                        }
                    />
                    { displayAsDropdown && (
                        <ToggleControl
                            // @ts-expect-error - upstream prop
                            __nextHasNoMarginBottom
                            label={ __( 'Show label', TEXT_DOMAIN ) }
                            checked={ Boolean( attributes.showLabel ?? true ) }
                            onChange={ ( value ) =>
                                setAttributes( { showLabel: value } )
                            }
                        />
                    ) }
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show post counts', TEXT_DOMAIN ) }
                        checked={ showPostCounts }
                        onChange={ ( value ) =>
                            setAttributes( { showPostCounts: value } )
                        }
                    />
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Group by', TEXT_DOMAIN ) }
                        value={ type }
                        options={ [
                            { label: __( 'Monthly', TEXT_DOMAIN ), value: 'monthly' },
                            { label: __( 'Yearly', TEXT_DOMAIN ), value: 'yearly' },
                        ] }
                        onChange={ ( value: string ) =>
                            setAttributes( { type: value } )
                        }
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <ServerSideRender
                    block="artisanpack/archives"
                    attributes={ attributes }
                />
            </div>
        </>
    );
}
