/**
 * CommentsPaginationNext — edit component.
 *
 * Server-rendered display block. Previews as a labelled placeholder
 * in the canvas; the real next-page link is emitted server-side
 * once the comment pagination state is known (request-time, not
 * post-time, so there's no `_resolved*` attribute to stamp).
 * Comments family fork (#519) Pass 2.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Newer Comments', TEXT_DOMAIN ),
    resolvedKey: '_resolvedPaginationNextLabel',
    kind: 'text',
    dummyValue: { text: 'Newer Comments →' },
} );
