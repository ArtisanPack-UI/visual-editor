/**
 * CommentContent — edit component.
 *
 * Server-rendered display block. Previews through
 * `createCommentPlaceholderEdit`: stamped `_resolvedContent` first,
 * then `artisanpack/commentPreview` context, then placeholder.
 * Comments family fork (#519).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: __( 'Comment Content', TEXT_DOMAIN ),
    resolvedKey: '_resolvedContent',
    kind: 'html',
    fromCommentPreview: ( comment ) => {
        const content = comment.content;
        return typeof content === 'string' && content !== '' ? { text: content } : null;
    },
    dummyValue: {
        text: '<p>This is a sample comment. Authors editing a template see this representative content so they can style the block before any real comments exist.</p>',
    },
} );
