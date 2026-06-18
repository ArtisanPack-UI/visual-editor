/**
 * QueryPaginationNext — edit component.
 *
 * Server-rendered display block. The real next-page link is emitted
 * server-side once the paginator state is known (request-time, after
 * `QueryInliner` stamps `_resolvedNextPageUrl`). In the canvas the
 * preview prefers, in order:
 *
 *   1. The stamped `_resolvedNextPageUrl` (faithful pre-rendered tree).
 *   2. A "Next Page" affordance computed from the
 *      `artisanpack/queryPreview` block context (#599) — the canvas
 *      always previews page 1 so a next page exists whenever the
 *      resolved total exceeds `perPage`.
 *   3. The labelled placeholder.
 *
 * Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { readQueryPreviewContext } from '../../editor/query-preview-context';
import { TEXT_DOMAIN } from '../../vendor/i18n';

interface QueryPaginationNextEditProps {
    attributes: Record<string, unknown>;
    context?: Record<string, unknown>;
}

export default function QueryPaginationNextEdit( {
    attributes,
    context,
}: QueryPaginationNextEditProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    const resolvedUrl = typeof attributes._resolvedNextPageUrl === 'string'
        ? attributes._resolvedNextPageUrl
        : '';
    const baseLabel = typeof attributes.label === 'string' && attributes.label !== ''
        ? attributes.label
        : __( 'Next Page', TEXT_DOMAIN );

    const preview = readQueryPreviewContext( context );
    // Canvas always previews page 1. A next page exists whenever the
    // resolved total exceeds `perPage` and `perPage` is configured.
    const hasNextPage = preview !== null && preview.perPage > 0 && preview.total > preview.perPage;

    if ( resolvedUrl !== '' || hasNextPage ) {
        return (
            <span { ...blockProps }>
                { baseLabel } &rarr;
            </span>
        );
    }

    return (
        <span { ...blockProps } style={ { opacity: 0.55, fontStyle: 'italic' } }>
            { baseLabel }
        </span>
    );
}
