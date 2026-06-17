/**
 * QueryPaginationNumbers — edit component.
 *
 * Server-rendered display block. The actual numbered links are
 * computed at request time by `QueryInliner` from the resolved
 * paginator and emitted server-side by the Blade / React / Vue
 * renderers. In the canvas the preview computes a plausible run of
 * page numbers from `total` + `perPage` carried on the
 * `artisanpack/queryPreview` block context (#599) so authors can see
 * the spacing / styling of multi-page navigation without forcing a
 * server round-trip. Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

import {
    readQueryPreviewContext,
    type QueryPreviewContextValue,
} from '../../editor/query-preview-context';

const MAX_PREVIEW_NUMBERS = 5;
const FALLBACK_TEXT = '1 2 3';

interface QueryPaginationNumbersEditProps {
    attributes: Record<string, unknown>;
    context?: Record<string, unknown>;
}

function computePagePreview( preview: QueryPreviewContextValue | null ): number[] {
    if ( preview === null || preview.perPage <= 0 || preview.total <= 0 ) {
        return [];
    }
    const pageCount = Math.max( 1, Math.ceil( preview.total / preview.perPage ) );
    const cap = Math.min( pageCount, MAX_PREVIEW_NUMBERS );
    const pages: number[] = [];
    for ( let index = 1; index <= cap; index++ ) {
        pages.push( index );
    }
    return pages;
}

export default function QueryPaginationNumbersEdit( {
    attributes,
    context,
}: QueryPaginationNumbersEditProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    // Stamped pages from a prior server render take precedence — the
    // canvas should be byte-faithful when the saved tree already has
    // them. Otherwise fall back to the live computed preview.
    const stampedLabel = typeof attributes._resolvedPaginationNumbersLabel === 'string'
        ? attributes._resolvedPaginationNumbersLabel
        : '';

    if ( stampedLabel !== '' ) {
        return <span { ...blockProps }>{ stampedLabel }</span>;
    }

    const preview = readQueryPreviewContext( context );
    const pages = computePagePreview( preview );

    if ( pages.length === 0 ) {
        return <span { ...blockProps }>{ FALLBACK_TEXT }</span>;
    }

    // Canvas always previews page 1; mark it current to mirror the
    // front-end output. Other pages render as plain numbers — the
    // paginator is not interactive in the editor by design.
    return (
        <span { ...blockProps }>
            { pages.map( ( page, index ) => (
                <span key={ page }>
                    { index > 0 ? ' ' : '' }
                    { page === 1
                        ? ( <span aria-current="page" style={ { fontWeight: 600 } }>{ page }</span> )
                        : ( <span>{ page }</span> ) }
                </span>
            ) ) }
        </span>
    );
}
