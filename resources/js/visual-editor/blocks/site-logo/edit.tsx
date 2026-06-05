/**
 * Site Logo — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. The stamped `_resolvedLogoUrl` attribute (paired with
 *     `_resolvedSiteTitle` for alt text) wins when present.
 *  2. Otherwise the editor reads the live singleton site-meta entity
 *     (`root/__unstableBase` — see #481), using its `logoUrl` and
 *     `title` fields so the canvas previews the configured logo.
 *  3. Falls back to the placeholder label.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import {
    createEntityPlaceholderEdit,
    readEntityString,
} from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Site Logo',
    resolvedKey: '_resolvedLogoUrl',
    kind: 'image',
    altKey: '_resolvedSiteTitle',
    fromSiteEntity: ( site ) => {
        const url = typeof site.logoUrl === 'string' ? site.logoUrl : '';

        if ( url === '' ) {
            return null;
        }

        return {
            imageUrl: url,
            imageAlt: readEntityString( site.title ),
        };
    },
} );
