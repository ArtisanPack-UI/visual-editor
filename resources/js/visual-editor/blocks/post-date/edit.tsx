/**
 * Post Date — edit component.
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
    label: 'Post Date',
    resolvedKey: '_resolvedDateFormatted',
    kind: 'text',
    fromQueryPreview: ( post ) => {
        if ( typeof post.dateFormatted === 'string' && post.dateFormatted !== '' ) {
            return { text: post.dateFormatted };
        }

        // Fallback when the server-formatted date isn't on the payload:
        // strip the time portion off the ISO `publishedAt` so the editor
        // still shows something post-shaped instead of the placeholder.
        if ( typeof post.publishedAt === 'string' && post.publishedAt !== '' ) {
            const datePart = post.publishedAt.slice( 0, 10 );
            return datePart !== '' ? { text: datePart } : null;
        }

        return null;
    },
} );
