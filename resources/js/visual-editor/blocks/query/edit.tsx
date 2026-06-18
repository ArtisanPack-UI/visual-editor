/**
 * Query Loop — edit component.
 *
 * Provides inspector controls for all QueryRuntime-supported attributes:
 * post type, posts per page, offset, order/orderBy, and search filter.
 * Previews the first matching post via `useQueryPreview` and renders
 * `<InnerBlocks />` inside a `BlockContextProvider` so inner
 * `artisanpack/post-*` blocks resolve against the right post.
 *
 * Phase I6 loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';
import { useEffect } from 'react';
import {
    BlockContextProvider,
    InnerBlocks,
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Notice,
    PanelBody,
    RangeControl,
    SelectControl,
    TextControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
    QUERY_PREVIEW_CONTEXT_KEY,
    type QueryPreviewContextValue,
} from '../../editor/query-preview-context';
import { useQueryPreview } from '../../editor/use-query-preview';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import PostVariantsPanel from './post-variants-panel';

interface QueryEditProps {
    attributes: Record<string, unknown>;
    setAttributes: ( changes: Record<string, unknown> ) => void;
    clientId: string;
}

// Seed every newly-inserted query with a post-template + pagination +
// no-results trio so the saved tree has the structure QueryInliner
// expects (one post-template wrapping N post-template-item clones,
// plus the request-time pagination + empty-state siblings). Without
// this, users have to discover that the pagination / no-results
// children are ancestor-locked to artisanpack/query and dig into the
// in-block inserter to add them. The InnerBlocks `template` only
// applies on first mount, so it does not overwrite an existing
// user-arranged tree (#521).
const DEFAULT_TEMPLATE: ReadonlyArray<[ string ]> = [
    [ 'artisanpack/post-template' ],
    [ 'artisanpack/query-pagination' ],
    [ 'artisanpack/query-no-results' ],
];

function getQuery( attributes: Record<string, unknown> ): Record<string, unknown> {
    if (
        attributes.query !== null &&
        typeof attributes.query === 'object' &&
        ! Array.isArray( attributes.query )
    ) {
        return attributes.query as Record<string, unknown>;
    }
    return {};
}

export default function QueryEdit( {
    attributes,
    setAttributes,
    clientId,
}: QueryEditProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();
    const query = getQuery( attributes );

    const updateQuery = ( changes: Record<string, unknown> ): void => {
        setAttributes( { query: { ...query, ...changes } } );
    };

    const postType = typeof query.postType === 'string' && query.postType !== ''
        ? query.postType : 'post';
    const perPage = typeof query.perPage === 'number' ? query.perPage : 5;
    const offset = typeof query.offset === 'number' ? query.offset : 0;
    const order = query.order === 'asc' ? 'asc' : 'desc';
    const orderBy = typeof query.orderBy === 'string' ? query.orderBy : 'date';
    const search = typeof query.search === 'string' ? query.search : '';
    const inherit = Boolean( query.inherit );

    const persistedQueryId =
        typeof attributes.queryId === 'string' || typeof attributes.queryId === 'number'
            ? String( attributes.queryId )
            : null;

    useEffect( () => {
        if ( persistedQueryId === clientId ) {
            return;
        }
        setAttributes( { queryId: clientId } );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ persistedQueryId, clientId ] );

    const preview = useQueryPreview( query );

    // Pipe the resolved record set + paginator state down to
    // descendants via block context — `post-template` iterates against
    // `posts`, `query-pagination` consumes `total` / `currentPage` /
    // `perPage`, `query-title` consumes `queryTitle`. The canvas always
    // previews page 1 because pagination is not interactive in the
    // editor (issue #599 scope).
    const queryPreviewContext: QueryPreviewContextValue = {
        posts: preview.status === 'ready' ? preview.posts : [],
        total: preview.total,
        currentPage: 1,
        queryTitle: '',
        perPage,
        status: preview.status,
    };

    const blockContext = {
        postType,
        [ QUERY_PREVIEW_CONTEXT_KEY ]: queryPreviewContext,
    };

    return (
        <div { ...blockProps }>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Post type', TEXT_DOMAIN ) }
                        value={ postType }
                        options={ [
                            { label: __( 'Posts', TEXT_DOMAIN ), value: 'post' },
                            { label: __( 'Pages', TEXT_DOMAIN ), value: 'page' },
                        ] }
                        onChange={ ( value: string ) => updateQuery( { postType: value } ) }
                    />
                    <RangeControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                        label={ __( 'Posts per page', TEXT_DOMAIN ) }
                        value={ perPage }
                        onChange={ ( value?: number ) => updateQuery( { perPage: value ?? 5 } ) }
                        min={ 1 }
                        max={ 100 }
                    />
                    <RangeControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                        label={ __( 'Offset', TEXT_DOMAIN ) }
                        value={ offset }
                        onChange={ ( value?: number ) => updateQuery( { offset: value ?? 0 } ) }
                        min={ 0 }
                        max={ 100 }
                    />
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Order by', TEXT_DOMAIN ) }
                        value={ `${ orderBy }/${ order }` }
                        options={ [
                            { label: __( 'Newest to oldest', TEXT_DOMAIN ), value: 'date/desc' },
                            { label: __( 'Oldest to newest', TEXT_DOMAIN ), value: 'date/asc' },
                            { label: __( 'A → Z', TEXT_DOMAIN ), value: 'title/asc' },
                            { label: __( 'Z → A', TEXT_DOMAIN ), value: 'title/desc' },
                        ] }
                        onChange={ ( value: string ) => {
                            const [ newOrderBy, newOrder ] = value.split( '/' );
                            updateQuery( { orderBy: newOrderBy, order: newOrder } );
                        } }
                    />
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Inherit query from URL', TEXT_DOMAIN ) }
                        help={ __( 'When enabled, uses the current page\'s query context (e.g. archive, search results).', TEXT_DOMAIN ) }
                        checked={ inherit }
                        onChange={ ( value ) => updateQuery( { inherit: value } ) }
                    />
                </PanelBody>
                <PanelBody title={ __( 'Filters', TEXT_DOMAIN ) } initialOpen={ false }>
                    <TextControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Search', TEXT_DOMAIN ) }
                        value={ search }
                        onChange={ ( value: string ) => updateQuery( { search: value } ) }
                    />
                </PanelBody>
                <PanelBody title={ __( 'Preview', TEXT_DOMAIN ) } initialOpen={ false }>
                    <PreviewStatus preview={ preview } />
                </PanelBody>
                <PostVariantsPanel
                    queryClientId={ clientId }
                    previewTotal={ preview.status === 'ready' ? preview.total : 0 }
                />
            </InspectorControls>
            <BlockContextProvider value={ blockContext }>
                <InnerBlocks template={ [ ...DEFAULT_TEMPLATE ] } />
            </BlockContextProvider>
        </div>
    );
}

interface PreviewSummaryProps {
    preview: ReturnType<typeof useQueryPreview>;
}

function PreviewStatus( { preview }: PreviewSummaryProps ): ReactElement {
    if ( preview.status === 'loading' ) {
        return <p>{ __( 'Loading preview…', TEXT_DOMAIN ) }</p>;
    }

    if ( preview.status === 'error' ) {
        return (
            <Notice status="error" isDismissible={ false }>
                { preview.error ?? __( 'Preview failed.', TEXT_DOMAIN ) }
            </Notice>
        );
    }

    if ( preview.status === 'ready' ) {
        return (
            <p>
                { __( 'Posts matched:', TEXT_DOMAIN ) } <strong>{ preview.total }</strong>
            </p>
        );
    }

    return <p>{ __( 'Configure the query to see a preview.', TEXT_DOMAIN ) }</p>;
}

