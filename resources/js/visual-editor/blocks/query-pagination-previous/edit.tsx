/**
 * QueryPaginationPrevious — edit component.
 *
 * Server-rendered display block. Previews as a labelled placeholder in
 * the canvas; the real previous-page link is emitted server-side once
 * the paginator state is known (request-time, after `QueryInliner`
 * stamps `_resolvedPreviousPageUrl`). Phase I-Block-Fork query family
 * (#521).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Previous Page', TEXT_DOMAIN ),
    resolvedKey: '_resolvedPreviousPageUrl',
    kind: 'text',
    dummyValue: { text: '← Previous Page' },
} );
