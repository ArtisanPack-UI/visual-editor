/**
 * QueryPaginationNumbers — edit component.
 *
 * Server-rendered display block. Previews as a labelled placeholder
 * — the actual numbered links are computed at request time by
 * `QueryInliner` from the resolved paginator and emitted server-side
 * by the Blade / React / Vue renderers. Phase I-Block-Fork query
 * family (#521).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Page Numbers', TEXT_DOMAIN ),
    resolvedKey: '_resolvedPaginationNumbersLabel',
    kind: 'text',
    dummyValue: { text: '1 2 3' },
} );
