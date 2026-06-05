/**
 * Categories (Terms List) — edit component.
 *
 * `core/categories` is server-rendered. Upstream's `edit.js` reads
 * `getTaxonomy('category').labels.name` and queries `@wordpress/core-data`
 * for terms, but the post editor's core-data shim does not expose the
 * taxonomy entity and this package resolves terms on the server (see
 * `src/Blocks/Core/CategoriesBlock.php`). The fork therefore previews
 * through the package's `<ServerSideRender>` seam — the same approach the
 * upstream taxonomy/archive overrides use — and ports the inspector
 * controls to `PanelBody`. Phase I6 loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ServerSideRender } from '../../editor/server-side-render';
import { TEXT_DOMAIN } from '../../vendor/i18n';

interface CategoriesAttributes {
    readonly showPostCounts?: boolean;
    readonly showHierarchy?: boolean;
    readonly showOnlyTopLevel?: boolean;
    readonly showEmpty?: boolean;
    readonly [ key: string ]: unknown;
}

interface CategoriesEditProps {
    readonly attributes: CategoriesAttributes;
    readonly setAttributes: ( attrs: Partial<CategoriesAttributes> ) => void;
}

export default function CategoriesEdit( {
    attributes,
    setAttributes,
}: CategoriesEditProps ): ReactElement {
    const showPostCounts = Boolean( attributes.showPostCounts );
    const showHierarchy = Boolean( attributes.showHierarchy );
    const showOnlyTopLevel = Boolean( attributes.showOnlyTopLevel );
    const showEmpty = Boolean( attributes.showEmpty );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show post counts', TEXT_DOMAIN ) }
                        checked={ showPostCounts }
                        onChange={ ( value ) =>
                            setAttributes( { showPostCounts: value } )
                        }
                    />
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show hierarchy', TEXT_DOMAIN ) }
                        checked={ showHierarchy }
                        onChange={ ( value ) =>
                            setAttributes( { showHierarchy: value } )
                        }
                    />
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show only top level', TEXT_DOMAIN ) }
                        checked={ showOnlyTopLevel }
                        onChange={ ( value ) =>
                            setAttributes( { showOnlyTopLevel: value } )
                        }
                    />
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show empty terms', TEXT_DOMAIN ) }
                        checked={ showEmpty }
                        onChange={ ( value ) =>
                            setAttributes( { showEmpty: value } )
                        }
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <ServerSideRender
                    block="artisanpack/categories"
                    attributes={ attributes }
                />
            </div>
        </>
    );
}
