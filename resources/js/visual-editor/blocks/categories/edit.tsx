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
 *
 * #960 adds the taxonomy picker (fed by the editor's taxonomy registry,
 * keeping a stored slug the registry doesn't list), the count style, and
 * the "All" item toggle + label.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ServerSideRender } from '../../editor/server-side-render';
import { getTaxonomies } from '../../editor/taxonomy-registry';
import { TEXT_DOMAIN } from '../../vendor/i18n';

type CountStyle = 'inline' | 'badge';

interface CategoriesAttributes {
    readonly taxonomy?: string;
    readonly showPostCounts?: boolean;
    readonly showHierarchy?: boolean;
    readonly showOnlyTopLevel?: boolean;
    readonly showEmpty?: boolean;
    readonly countStyle?: CountStyle;
    readonly showAll?: boolean;
    readonly allLabel?: string;
    readonly [ key: string ]: unknown;
}

interface CategoriesEditProps {
    readonly attributes: CategoriesAttributes;
    readonly setAttributes: ( attrs: Partial<CategoriesAttributes> ) => void;
}

/**
 * Builds the taxonomy `SelectControl` options from the runtime registry,
 * appending the stored slug when the registry doesn't list it so the
 * control never drops the author's value.
 */
function taxonomyOptions(
    current: string,
): { label: string; value: string }[] {
    const options = getTaxonomies().map( ( taxonomy ) => ( {
        label: taxonomy.plural,
        value: taxonomy.slug,
    } ) );

    if ( ! options.some( ( option ) => option.value === current ) ) {
        options.push( { label: current, value: current } );
    }

    return options;
}

export default function CategoriesEdit( {
    attributes,
    setAttributes,
}: CategoriesEditProps ): ReactElement {
    const taxonomy =
        typeof attributes.taxonomy === 'string' && attributes.taxonomy !== ''
            ? attributes.taxonomy
            : 'category';
    const showPostCounts = Boolean( attributes.showPostCounts );
    const showHierarchy = Boolean( attributes.showHierarchy );
    const showOnlyTopLevel = Boolean( attributes.showOnlyTopLevel );
    const showEmpty = Boolean( attributes.showEmpty );
    const countStyle: CountStyle =
        attributes.countStyle === 'badge' ? 'badge' : 'inline';
    const showAll = Boolean( attributes.showAll );
    const allLabel =
        typeof attributes.allLabel === 'string' ? attributes.allLabel : '';

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Taxonomy', TEXT_DOMAIN ) }
                        value={ taxonomy }
                        options={ taxonomyOptions( taxonomy ) }
                        onChange={ ( value: string ) =>
                            setAttributes( { taxonomy: value } )
                        }
                    />
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show post counts', TEXT_DOMAIN ) }
                        checked={ showPostCounts }
                        onChange={ ( value ) =>
                            setAttributes( { showPostCounts: value } )
                        }
                    />
                    { showPostCounts && (
                        <SelectControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Count style', TEXT_DOMAIN ) }
                            value={ countStyle }
                            options={ [
                                {
                                    label: __( 'Inline', TEXT_DOMAIN ),
                                    value: 'inline',
                                },
                                {
                                    label: __( 'Badge', TEXT_DOMAIN ),
                                    value: 'badge',
                                },
                            ] }
                            onChange={ ( value: string ) =>
                                setAttributes( {
                                    countStyle:
                                        value === 'badge' ? 'badge' : 'inline',
                                } )
                            }
                        />
                    ) }
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
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Show "All" item', TEXT_DOMAIN ) }
                        help={ __(
                            'Adds a first item that links to the unfiltered list.',
                            TEXT_DOMAIN,
                        ) }
                        checked={ showAll }
                        onChange={ ( value ) =>
                            setAttributes( { showAll: value } )
                        }
                    />
                    { showAll && (
                        <TextControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( '"All" label', TEXT_DOMAIN ) }
                            placeholder={ __( 'All', TEXT_DOMAIN ) }
                            value={ allLabel }
                            onChange={ ( value: string ) =>
                                setAttributes( { allLabel: value } )
                            }
                        />
                    ) }
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
