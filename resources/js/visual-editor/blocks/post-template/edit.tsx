/**
 * Post Template — edit component.
 *
 * Renders one editable iteration of the per-post template (driven by
 * `<InnerBlocks />`) plus N read-only ghosts for the rest of the
 * resolved record set, via the shared `<QueryPreviewIterations>`
 * renderer. Provides a toolbar toggle to switch between list and grid
 * display, plus a columns control when grid is active. The wrapping
 * `artisanpack/query` block pipes the resolved record set down through
 * `artisanpack/queryPreview` block context (#599). Phase I6 loop /
 * feed cluster (#414).
 */

import type { ReactElement } from 'react';
import {
    BlockControls,
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

import { readQueryPreviewContext } from '../../editor/query-preview-context';
import { QueryPreviewIterations } from '../../editor/query-preview-iterations';
import { TEXT_DOMAIN } from '../../vendor/i18n';

const DEFAULT_TEMPLATE: ReadonlyArray<[string]> = [ [ 'artisanpack/post-title' ] ];

interface PostTemplateEditProps {
    attributes: Record<string, unknown>;
    setAttributes: ( changes: Record<string, unknown> ) => void;
    clientId: string;
    context?: Record<string, unknown>;
}

export default function PostTemplateEdit( {
    attributes,
    setAttributes,
    clientId,
    context,
}: PostTemplateEditProps ): ReactElement {
    const layout = typeof attributes.layout === 'string' ? attributes.layout : 'list';
    const columns = typeof attributes.columns === 'number' ? attributes.columns : 3;
    const isGrid = layout === 'grid';

    // Match the Blade renderer's class set so the editor canvas and the
    // public frontend use the same CSS-grid layout. `is-layout-grid` +
    // `columns-{n}` together activate the rules shipped via the
    // post-variant stylesheet, which also powers per-post column / row
    // spans (#592).
    const className = [
        'wp-block-post-template',
        isGrid ? 'is-layout-grid' : 'is-layout-flow',
        isGrid ? `columns-${ columns }` : '',
    ].filter( Boolean ).join( ' ' );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( { className } );

    const previewValue = readQueryPreviewContext( context );
    const postType = typeof context?.postType === 'string' && context.postType !== ''
        ? context.postType
        : 'post';

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

            <QueryPreviewIterations
                clientId={ clientId }
                preview={ previewValue }
                postType={ postType }
                defaultTemplate={ DEFAULT_TEMPLATE }
                outerProps={ blockProps as Record<string, unknown> }
            />
        </>
    );
}
