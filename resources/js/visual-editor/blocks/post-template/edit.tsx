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
import { list, grid, layout } from '@wordpress/icons';

import { readQueryPreviewContext } from '../../editor/query-preview-context';
import { QueryPreviewIterations } from '../../editor/query-preview-iterations';
import { TEXT_DOMAIN } from '../../vendor/i18n';

const DEFAULT_TEMPLATE: ReadonlyArray<[string]> = [ [ 'artisanpack/post-title' ] ];

type PostTemplateLayout = 'list' | 'grid' | 'masonry';

function normalizeLayout( value: unknown ): PostTemplateLayout {
    if ( 'grid' === value || 'masonry' === value ) {
        return value;
    }
    return 'list';
}

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
    const layoutValue = normalizeLayout( attributes.layout );
    const columns = typeof attributes.columns === 'number' ? attributes.columns : 3;
    const isGrid = 'grid' === layoutValue;
    const isMasonry = 'masonry' === layoutValue;
    const usesColumns = isGrid || isMasonry;

    // Match the Blade renderer's class set so the editor canvas and the
    // public frontend use the same CSS-grid layout.
    //
    // For masonry we layer `is-layout-grid` underneath `is-layout-masonry`
    // so Gutenberg's bundled layout baseline (which only knows about the
    // standard `is-layout-grid` class) gives us `display: grid` inside
    // the editor canvas iframe, and the post-template's existing grid
    // rules give us `grid-template-columns`. The `is-layout-masonry`
    // class then layers `grid-template-rows: masonry` on top via
    // `@supports` for browsers that ship native CSS Grid masonry — non-
    // supporting browsers see a regular columned grid in the canvas,
    // which the public frontend's JS fallback packs at render time.
    const className = [
        'wp-block-post-template',
        ( isGrid || isMasonry ) ? 'is-layout-grid' : '',
        isMasonry ? 'is-layout-masonry' : '',
        ! usesColumns ? 'is-layout-flow' : '',
        usesColumns ? `columns-${ columns }` : '',
    ].filter( Boolean ).join( ' ' );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( { className } );

    // Editor canvas preview path:
    //   - Browsers that ship native `grid-template-rows: masonry`
    //     render the canvas with true packed layout via @supports.
    //   - Other browsers see a regular columned grid (visually the
    //     same as `layout: grid`). The public frontend's masonry-
    //     fallback bootstrap packs items for both groups, so the
    //     published page is always packed even if the canvas isn't.
    // We deliberately don't run the JS fallback here: doing so inside
    // Gutenberg's canvas iframe leaves items absolutely-positioned
    // before BlockContextProvider streams their content in, so they
    // measure 0px and collapse the wrapper.
    const outerProps: Record< string, unknown > = { ...blockProps };
    if ( isMasonry ) {
        outerProps[ 'data-ap-cols' ] = columns;
    }

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
                        isPressed={ 'list' === layoutValue }
                        onClick={ () => setAttributes( { layout: 'list' } ) }
                    />
                    <ToolbarButton
                        icon={ grid }
                        label={ __( 'Grid view', TEXT_DOMAIN ) }
                        isPressed={ isGrid }
                        onClick={ () => setAttributes( { layout: 'grid' } ) }
                    />
                    <ToolbarButton
                        icon={ layout }
                        label={ __( 'Masonry view', TEXT_DOMAIN ) }
                        isPressed={ isMasonry }
                        onClick={ () => setAttributes( { layout: 'masonry' } ) }
                    />
                </ToolbarGroup>
            </BlockControls>

            { usesColumns && (
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
                            help={ isMasonry
                                ? __(
                                    'Masonry packs items into columns by shortest-column-first; row spans are ignored in this layout.',
                                    TEXT_DOMAIN
                                )
                                : undefined }
                        />
                    </PanelBody>
                </InspectorControls>
            ) }

            <QueryPreviewIterations
                clientId={ clientId }
                preview={ previewValue }
                postType={ postType }
                defaultTemplate={ DEFAULT_TEMPLATE }
                outerProps={ outerProps }
            />
        </>
    );
}
