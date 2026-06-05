/**
 * Post Date — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedDateFormatted` attribute.
 *  2. `artisanpack/postPreview` query-loop context (#483) — prefers
 *     the server-formatted `dateFormatted`, falls back to the ISO
 *     date portion of `publishedAt`.
 *  3. Live page entity date from the core-data shim, using
 *     `_preview.dateFormatted` (mirrors the front-end format) and
 *     falling back to the ISO `date` portion (#481).
 *  4. Clearly-labelled placeholder.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
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
    fromPostEntity: ( entity ) => {
        const preview = entity._preview;
        const formatted = preview?.dateFormatted;

        if ( typeof formatted === 'string' && formatted !== '' ) {
            return { text: formatted };
        }

        // Mirror the query-preview ISO fallback so a missing
        // `_preview.dateFormatted` still surfaces the date instead of
        // collapsing back to the placeholder.
        const iso = entity.date;

        if ( typeof iso === 'string' && iso !== '' ) {
            const datePart = iso.slice( 0, 10 );
            return datePart !== '' ? { text: datePart } : null;
        }

        return null;
    },
} );
