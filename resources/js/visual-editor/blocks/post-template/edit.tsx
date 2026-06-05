/**
 * Post Template — edit component.
 *
 * Renders `<InnerBlocks />` with a default `artisanpack/post-title`
 * template so users can build the per-iteration layout. Provides a
 * toolbar toggle to switch between list and grid display, plus a columns
 * control when grid is active. The wrapping `artisanpack/query`
 * `BlockContextProvider` resolves the inner `artisanpack/post-*` blocks
 * against the right post. Phase I6 loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';
import {
    BlockControls,
    InnerBlocks,
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    ToolbarButton,
    ToolbarGroup,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { list, grid } from '@wordpress/icons';

import { TEXT_DOMAIN } from '../../vendor/i18n';

const DEFAULT_TEMPLATE: ReadonlyArray<[string]> = [ [ 'artisanpack/post-title' ] ];

interface PostTemplateEditProps {
    attributes: Record<string, unknown>;
    setAttributes: ( changes: Record<string, unknown> ) => void;
}

export default function PostTemplateEdit( {
    attributes,
    setAttributes,
}: PostTemplateEditProps ): ReactElement {
    const layout = typeof attributes.layout === 'string' ? attributes.layout : 'list';
    const columns = typeof attributes.columns === 'number' ? attributes.columns : 3;
    const isGrid = layout === 'grid';

    const className = [
        'wp-block-post-template',
        isGrid ? 'is-layout-grid' : 'is-layout-flow',
        isGrid ? `columns-${ columns }` : '',
    ].filter( Boolean ).join( ' ' );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( { className } );

    return (
        <>
            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon={ list }
                        label={ __( 'List view', TEXT_DOMAIN ) }
                        isPressed={ ! isGrid }
                        onClick={ () => setAttributes( { layout: 'list' } ) }
                    />
                    <ToolbarButton
                        icon={ grid }
                        label={ __( 'Grid view', TEXT_DOMAIN ) }
                        isPressed={ isGrid }
                        onClick={ () => setAttributes( { layout: 'grid' } ) }
                    />
                </ToolbarGroup>
            </BlockControls>

            { isGrid && (
                <InspectorControls>
                    <PanelBody title={ __( 'Layout', TEXT_DOMAIN ) }>
                        <RangeControl
                            // @ts-expect-error - upstream prop
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={ __( 'Columns', TEXT_DOMAIN ) }
                            value={ columns }
                            onChange={ ( value?: number ) =>
                                setAttributes( { columns: value ?? 3 } )
                            }
                            min={ 2 }
                            max={ 6 }
                        />
                    </PanelBody>
                </InspectorControls>
            ) }

            <div { ...blockProps }>
                <InnerBlocks template={ [ ...DEFAULT_TEMPLATE ] } />
            </div>
        </>
    );
}
