/**
 * Featured Image — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedImageUrl` / `_resolvedImageAlt` attributes.
 *  2. `artisanpack/postPreview` query-loop context (#483).
 *  3. Live page entity featured-image from the core-data shim's
 *     `_preview.featuredImage` (#481).
 *  4. Clearly-labelled placeholder.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
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
    fromPostEntity: ( entity ) => {
        const image = entity._preview?.featuredImage;

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
