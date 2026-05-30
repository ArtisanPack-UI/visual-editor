/**
 * Post Excerpt — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedExcerpt` attribute.
 *  2. `artisanpack/postPreview` query-loop context (#483).
 *  3. Live page entity excerpt fetched through the core-data shim
 *     when `postId`/`postType` block context is present (#481).
 *  4. Clearly-labelled placeholder.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import {
    createEntityPlaceholderEdit,
    readEntityString,
} from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Post Excerpt',
    resolvedKey: '_resolvedExcerpt',
    kind: 'text',
    fromQueryPreview: ( post ) =>
        typeof post.excerpt === 'string' && post.excerpt !== ''
            ? { text: post.excerpt }
            : null,
    fromPostEntity: ( entity ) => {
        const excerpt = readEntityString( entity.excerpt );
        return excerpt !== '' ? { text: excerpt } : null;
    },
} );
