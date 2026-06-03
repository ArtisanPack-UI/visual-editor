/**
 * CommentAuthorName — edit component.
 *
 * Server-rendered display block. Previews through
 * `createCommentPlaceholderEdit`: stamped `_resolvedAuthorName` first,
 * then `artisanpack/commentPreview` context, then placeholder.
 * Comments family fork (#519).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: __( 'Comment Author Name', TEXT_DOMAIN ),
    resolvedKey: '_resolvedAuthorName',
    kind: 'text',
    fromCommentPreview: ( comment ) => {
        const name = comment.authorName;
        return typeof name === 'string' && name !== '' ? { text: name } : null;
    },
    dummyValue: { text: 'Jane Doe' },
} );
