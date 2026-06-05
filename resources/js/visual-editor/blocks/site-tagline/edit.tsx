/**
 * Site Tagline — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. The stamped `_resolvedSiteTagline` attribute wins when present.
 *  2. Otherwise the editor reads the live singleton site-meta entity
 *     (`root/__unstableBase` — see #481) so the canvas previews the
 *     configured tagline.
 *  3. Falls back to the placeholder label.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import {
    createEntityPlaceholderEdit,
    readEntityString,
} from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Site Tagline',
    resolvedKey: '_resolvedSiteTagline',
    kind: 'text',
    fromSiteEntity: ( site ) => {
        const description = readEntityString( site.description );
        return description !== '' ? { text: description } : null;
    },
} );
