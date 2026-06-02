/**
 * CommentDate — edit component.
 *
 * Server-rendered display block. Previews through
 * `createCommentPlaceholderEdit`: stamped `_resolvedDateFormatted`
 * first, then `artisanpack/commentPreview` context, then placeholder.
 * Comments family fork (#519).
 */

import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: 'Comment Date',
    resolvedKey: '_resolvedDateFormatted',
    kind: 'text',
    fromCommentPreview: ( comment ) => {
        const formatted = comment.dateFormatted ?? comment.date;
        return typeof formatted === 'string' && formatted !== ''
            ? { text: formatted }
            : null;
    },
} );
