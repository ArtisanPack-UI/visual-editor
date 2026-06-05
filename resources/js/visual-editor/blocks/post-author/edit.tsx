/**
 * Post Author — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedAuthorName` attribute.
 *  2. `artisanpack/postPreview` query-loop context (#483).
 *  3. Live page entity author name from the core-data shim's
 *     `_preview.author.name` (#481).
 *  4. Clearly-labelled placeholder.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Post Author',
    resolvedKey: '_resolvedAuthorName',
    kind: 'text',
    fromQueryPreview: ( post ) => {
        const name = post.author?.name;
        return typeof name === 'string' && name !== '' ? { text: name } : null;
    },
    fromPostEntity: ( entity ) => {
        const name = entity._preview?.author?.name;
        return typeof name === 'string' && name !== '' ? { text: name } : null;
    },
} );
