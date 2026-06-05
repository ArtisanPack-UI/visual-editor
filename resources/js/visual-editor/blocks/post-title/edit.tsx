/**
 * Post Title — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. The stamped `_resolvedTitle` attribute (front-end / saved-tree
 *     path) wins when present.
 *  2. Falls back to the resolved post in the `artisanpack/postPreview`
 *     block context — query-loop case (#483).
 *  3. Falls back to the live page entity (`postType` / `postId` block
 *     context, fetched through the core-data shim) so a block placed
 *     at the page level previews the page's actual title (#481).
 *  4. Falls back to a clearly-labelled placeholder.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import {
    createEntityPlaceholderEdit,
    readEntityString,
} from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Post Title',
    resolvedKey: '_resolvedTitle',
    kind: 'text',
    fromQueryPreview: ( post ) =>
        typeof post.title === 'string' && post.title !== ''
            ? { text: post.title }
            : null,
    fromPostEntity: ( entity ) => {
        const title = readEntityString( entity.title );
        return title !== '' ? { text: title } : null;
    },
} );
