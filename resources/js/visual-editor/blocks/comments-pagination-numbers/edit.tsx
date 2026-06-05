/**
 * CommentsPaginationNumbers — edit component.
 *
 * Server-rendered display block. Previews as a labelled placeholder
 * — pagination state is computed at request time, not from any
 * post-level resolved data. Comments family fork (#519) Pass 2.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Comments Page Numbers', TEXT_DOMAIN ),
    resolvedKey: '_resolvedPaginationNumbersLabel',
    kind: 'text',
    dummyValue: { text: '1 2 3' },
} );
