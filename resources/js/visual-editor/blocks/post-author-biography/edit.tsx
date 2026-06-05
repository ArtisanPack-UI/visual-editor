/**
 * Post Author Biography — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from the stamped `_resolvedAuthorBio`
 * attribute. The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedAuthorBio` attribute.
 *  2. `artisanpack/postPreview` query-loop context (#483).
 *  3. Live page entity author bio from the core-data shim's
 *     `_preview.author.bio` (#481).
 *  4. Clearly-labelled placeholder.
 *
 * Renders as `html` kind so the placeholder preview strips tags before
 * displaying; the production renderers emit the full HTML on the front
 * end. Author family fork (#518).
 */

import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Author Biography',
    resolvedKey: '_resolvedAuthorBio',
    kind: 'html',
    fromQueryPreview: ( post ) => {
        const bio = post.author?.bio;
        return typeof bio === 'string' && bio !== '' ? { text: bio } : null;
    },
    fromPostEntity: ( entity ) => {
        const bio = entity._preview?.author?.bio;
        return typeof bio === 'string' && bio !== '' ? { text: bio } : null;
    },
} );
