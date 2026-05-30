/**
 * Featured Image — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes, and
 * the editor's core-data shim does not expose the entity to the post
 * editor. The fork therefore previews through the lightweight
 * `createEntityPlaceholderEdit` helper (renders the resolved value when
 * present; falls back to the resolved post in the query-loop block
 * context — #483 — and finally to a clearly-labelled placeholder) rather
 * than delegating to upstream's entity-querying edit. Phase I5 entity
 * cluster (#413).
 */

import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Featured Image',
    resolvedKey: '_resolvedImageUrl',
    kind: 'image',
    altKey: '_resolvedImageAlt',
    fromQueryPreview: ( post ) => {
        const image = post.featuredImage;

        if ( image === null || image === undefined ) {
            return null;
        }

        if ( typeof image.url !== 'string' || image.url === '' ) {
            return null;
        }

        return {
            imageUrl: image.url,
            imageAlt: typeof image.alt === 'string' ? image.alt : '',
        };
    },
} );
