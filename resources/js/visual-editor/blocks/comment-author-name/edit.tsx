/**
 * CommentAuthorName — edit component.
 *
 * Server-rendered display block. Previews through
 * `createCommentPlaceholderEdit`: stamped `_resolvedAuthorName` first,
 * then `artisanpack/commentPreview` context, then placeholder.
 * Comments family fork (#519).
 */

import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: 'Comment Author Name',
    resolvedKey: '_resolvedAuthorName',
    kind: 'text',
    fromCommentPreview: ( comment ) => {
        const name = comment.authorName;
        return typeof name === 'string' && name !== '' ? { text: name } : null;
    },
} );
