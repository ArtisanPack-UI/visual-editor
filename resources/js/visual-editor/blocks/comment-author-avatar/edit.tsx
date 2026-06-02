/**
 * CommentAuthorAvatar — edit component.
 *
 * Server-rendered display block. Previews through the lightweight
 * `createCommentPlaceholderEdit` helper, which reads the stamped
 * `_resolvedAvatarUrl` attribute, then falls back to the
 * `artisanpack/commentPreview` context piped down by `comment-template`.
 * Comments family fork (#519).
 */

import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: 'Comment Author Avatar',
    resolvedKey: '_resolvedAvatarUrl',
    altKey: '_resolvedAvatarAlt',
    kind: 'image',
    fromCommentPreview: ( comment ) => {
        const url = comment.authorAvatarUrl;
        return typeof url === 'string' && url !== ''
            ? { imageUrl: url, imageAlt: comment.authorName ?? '' }
            : null;
    },
} );
