/**
 * QueryPaginationPrevious — edit component.
 *
 * Server-rendered display block. The real previous-page link is
 * emitted server-side once the paginator state is known (request-time,
 * after `QueryInliner` stamps `_resolvedPreviousPageUrl`). The canvas
 * always previews page 1, so the previous-page affordance is rendered
 * as a muted placeholder unless the saved tree carries
 * `_resolvedPreviousPageUrl` from a prior server render. Phase
 * I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface QueryPaginationPreviousEditProps {
    attributes: Record<string, unknown>;
}

export default function QueryPaginationPreviousEdit( {
    attributes,
}: QueryPaginationPreviousEditProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    const resolvedUrl = typeof attributes._resolvedPreviousPageUrl === 'string'
        ? attributes._resolvedPreviousPageUrl
        : '';
    const baseLabel = typeof attributes.label === 'string' && attributes.label !== ''
        ? attributes.label
        : __( 'Previous Page', TEXT_DOMAIN );

    if ( resolvedUrl !== '' ) {
        return (
            <span { ...blockProps }>
                &larr; { baseLabel }
            </span>
        );
    }

    // No previous page exists from canvas page 1 — render muted so
    // users can tell at a glance that the affordance is inactive in
    // the preview without obscuring it for styling work.
    return (
        <span { ...blockProps } style={ { opacity: 0.55, fontStyle: 'italic' } }>
            { baseLabel }
        </span>
    );
}
