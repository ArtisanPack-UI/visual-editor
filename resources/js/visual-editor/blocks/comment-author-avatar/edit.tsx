/**
 * CommentAuthorAvatar — edit component.
 *
 * Server-rendered display block. Previews through the lightweight
 * `createCommentPlaceholderEdit` helper, which reads the stamped
 * `_resolvedAvatarUrl` attribute, then falls back to the
 * `artisanpack/commentPreview` context piped down by `comment-template`.
 * Comments family fork (#519).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: __( 'Comment Author Avatar', TEXT_DOMAIN ),
    resolvedKey: '_resolvedAvatarUrl',
    altKey: '_resolvedAvatarAlt',
    kind: 'image',
    fromCommentPreview: ( comment ) => {
        const url = comment.authorAvatarUrl;
        return typeof url === 'string' && url !== ''
            ? { imageUrl: url, imageAlt: comment.authorName ?? '' }
            : null;
    },
    // Project the block's `width` / `height` attributes onto the rendered
    // `<img>` so the editor preview reflects in-canvas resizing instead of
    // ignoring the block's sizing controls.
    getImageProps: ( attributes ) => {
        const props: Record<string, unknown> = {};
        if ( typeof attributes.width === 'number' && attributes.width > 0 ) {
            props.width = attributes.width;
        }
        if ( typeof attributes.height === 'number' && attributes.height > 0 ) {
            props.height = attributes.height;
        }
        return props;
    },
} );
