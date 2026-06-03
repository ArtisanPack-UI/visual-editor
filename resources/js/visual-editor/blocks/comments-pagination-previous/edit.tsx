/**
 * CommentsPaginationPrevious — edit component.
 *
 * Server-rendered display block. Previews as a labelled placeholder
 * in the canvas; the real previous-page link is emitted server-side
 * once the comment pagination state is known (request-time).
 * Comments family fork (#519) Pass 2.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Older Comments', TEXT_DOMAIN ),
    resolvedKey: '_resolvedPaginationPreviousLabel',
    kind: 'text',
    dummyValue: { text: '← Older Comments' },
} );
