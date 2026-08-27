/**
 * Pure rich-text helpers for the Dynamic-Content-aware link format (#662).
 *
 * Kept free of `@wordpress/*` imports so they unit-test without mocking
 * the editor runtime.
 *
 * @since 1.7.0
 */

import { LINK_FORMAT_NAME } from './constants';

export interface RichTextFormatObject {
    type: string;
    attributes?: Record< string, string >;
}

export interface RichTextValueLike {
    text: string;
    start?: number;
    end?: number;
    formats: Array< RichTextFormatObject[] | undefined >;
    replacements?: unknown[];
}

export interface LinkFormatInput {
    url?: string;
    opensInNewTab?: boolean;
}

/**
 * Build the `core/link` format object, applying the "open in new tab"
 * rel/target pair the same way `@wordpress/format-library` does.
 *
 * @since 1.7.0
 *
 * @param  LinkFormatInput  next  The link values chosen in the popover.
 *
 * @return The `core/link` format object.
 */
export function buildLinkFormat( next: LinkFormatInput ): RichTextFormatObject {
    const attributes: Record< string, string > = { url: next.url ?? '' };

    if ( next.opensInNewTab ) {
        attributes.target = '_blank';
        attributes.rel    = 'noreferrer noopener';
    }

    return { type: LINK_FORMAT_NAME, attributes };
}

/**
 * Find the contiguous `[start, end)` range of the `core/link` run that
 * covers the caret, so an edit/remove targets the whole link even when
 * the selection is collapsed inside it.
 *
 * @since 1.7.0
 *
 * @param  RichTextValueLike  value  The current rich-text value.
 *
 * @return The `[start, end)` tuple, or `null` when the caret isn't on a link.
 */
export function activeLinkRange( value: RichTextValueLike ): [ number, number ] | null {
    const formats = value.formats;
    const caret   = value.start ?? 0;

    const hasLink = ( index: number ): boolean =>
        index >= 0 &&
        index < formats.length &&
        Array.isArray( formats[ index ] ) &&
        ( formats[ index ] as RichTextFormatObject[] ).some( ( f ) => LINK_FORMAT_NAME === f.type );

    // The caret sits *between* characters. Probe the character to the
    // right first, then the one to the left (caret at the link's end).
    let index = caret;
    if ( ! hasLink( index ) ) {
        index = caret - 1;
    }
    if ( ! hasLink( index ) ) {
        return null;
    }

    let start = index;
    while ( start > 0 && hasLink( start - 1 ) ) {
        start--;
    }

    let end = index + 1;
    while ( hasLink( end ) ) {
        end++;
    }

    return [ start, end ];
}
