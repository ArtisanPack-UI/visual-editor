/**
 * CommentContent — edit component.
 *
 * Server-rendered display block. Previews through
 * `createCommentPlaceholderEdit`: stamped `_resolvedContent` first,
 * then `artisanpack/commentPreview` context, then placeholder.
 * Comments family fork (#519).
 */

import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: 'Comment Content',
    resolvedKey: '_resolvedContent',
    kind: 'html',
    fromCommentPreview: ( comment ) => {
        const content = comment.content;
        return typeof content === 'string' && content !== '' ? { text: content } : null;
    },
} );
