/**
 * Inline-icon RichText format registration (#717).
 *
 * Registers the `artisanpack/inline-icon` format so an icon can be
 * inserted at the caret inside any editable rich-text field — headings,
 * paragraphs, buttons, list items, captions. `contentEditable: false`
 * makes each icon a single, atomic object that shows its SVG preview in
 * the canvas yet round-trips as a `<span class="ap-inline-icon">` the
 * server hydrator (registered-set icons) or the sanitized inline SVG
 * (custom icons) resolves at render time.
 */

import { registerFormatType, store as richTextStore } from '@wordpress/rich-text';
import { select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { InlineIconEdit } from './edit';
import { FORMAT_NAME, INLINE_ICON_ATTRIBUTES, INLINE_ICON_CLASS } from './settings';

import './inline-icon.css';

/**
 * Register the inline-icon format. Idempotent — safe to call more than
 * once (the editor bootstrap already guards, but a stray double-call
 * won't throw a "already registered" error).
 */
export function registerInlineIconFormat(): void {
    // `getFormatType` (singular) isn't a public `@wordpress/rich-text`
    // export, so query the format store directly. `registerFormatType`
    // only `console.error`s (it doesn't throw) on a duplicate name, so
    // this guard keeps a stray double-call quiet.
    const selectors = select( richTextStore as never ) as unknown as {
        getFormatType( name: string ): unknown;
    };
    if ( selectors.getFormatType( FORMAT_NAME ) ) {
        return;
    }

    registerFormatType( FORMAT_NAME, {
        title: __( 'Inline icon', TEXT_DOMAIN ),
        tagName: 'span',
        className: INLINE_ICON_CLASS,
        // Atomic object: the icon is one non-editable unit the author can
        // select and delete whole, and its SVG body round-trips through
        // save/reload (see `@wordpress/rich-text` create/to-tree handling
        // of `contentEditable: false` formats).
        contentEditable: false,
        attributes: { ...INLINE_ICON_ATTRIBUTES },
        edit: InlineIconEdit,
        // `contentEditable` isn't in the published WPFormat type surface,
        // so widen the settings object for the registration call.
    } as unknown as Parameters< typeof registerFormatType >[ 1 ] );
}
