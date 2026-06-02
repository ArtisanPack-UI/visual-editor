/**
 * CommentReplyLink — edit component.
 *
 * Server-rendered display block. Previews as a labelled placeholder
 * in the canvas; the real reply link is emitted server-side once
 * comment threading state is known. Comments family fork (#519).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: __( 'Reply', TEXT_DOMAIN ),
    resolvedKey: '_resolvedReplyLinkLabel',
    kind: 'text',
    dummyValue: { text: 'Reply' },
} );
