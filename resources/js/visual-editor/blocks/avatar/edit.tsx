/**
 * Avatar — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from the stamped `_resolvedAuthorAvatar`
 * (URL) and `_resolvedAuthorName` (alt-text) attributes. The fork
 * previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedAuthorAvatar` attribute.
 *  2. `artisanpack/postPreview` query-loop context (#483).
 *  3. Live page entity author avatar from the core-data shim's
 *     `_preview.author.avatarUrl` (#481).
 *  4. Clearly-labelled placeholder.
 *
 * Renders as `image` kind so the preview emits an `<img>`. The avatar
 * block supports `commentId` context for forward-compat with the
 * comments family (#519); per-comment resolution lands when that
 * sub-issue is implemented. Author family fork (#518).
 */

import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Avatar',
    resolvedKey: '_resolvedAuthorAvatar',
    altKey: '_resolvedAuthorName',
    kind: 'image',
    fromQueryPreview: ( post ) => {
        const url = post.author?.avatarUrl;
        const name = post.author?.name;
        return typeof url === 'string' && url !== ''
            ? { imageUrl: url, imageAlt: typeof name === 'string' ? name : '' }
            : null;
    },
    fromPostEntity: ( entity ) => {
        const url = entity._preview?.author?.avatarUrl;
        const name = entity._preview?.author?.name;
        return typeof url === 'string' && url !== ''
            ? { imageUrl: url, imageAlt: typeof name === 'string' ? name : '' }
            : null;
    },
} );
