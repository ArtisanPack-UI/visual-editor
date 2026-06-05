/**
 * Site Title — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. The stamped `_resolvedSiteTitle` attribute (front-end / saved
 *     tree path) wins when present.
 *  2. Otherwise the editor reads the live singleton site-meta entity
 *     (`root/__unstableBase` — see #481) through the core-data shim
 *     so the canvas previews the configured site title.
 *  3. Falls back to the placeholder label when neither resolves.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import {
    createEntityPlaceholderEdit,
    readEntityString,
} from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: 'Site Title',
    resolvedKey: '_resolvedSiteTitle',
    kind: 'text',
    fromSiteEntity: ( site ) => {
        const title = readEntityString( site.title );
        return title !== '' ? { text: title } : null;
    },
} );
