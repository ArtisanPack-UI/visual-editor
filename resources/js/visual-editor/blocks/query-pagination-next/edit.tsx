/**
 * QueryPaginationNext — edit component.
 *
 * Server-rendered display block. Previews as a labelled placeholder in
 * the canvas; the real next-page link is emitted server-side once the
 * paginator state is known (request-time, after `QueryInliner` stamps
 * `_resolvedNextPageUrl`). Phase I-Block-Fork query family (#521).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Next Page', TEXT_DOMAIN ),
    resolvedKey: '_resolvedNextPageUrl',
    kind: 'text',
    dummyValue: { text: __( 'Next Page →', TEXT_DOMAIN ) },
} );
