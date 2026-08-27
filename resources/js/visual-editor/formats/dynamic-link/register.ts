/**
 * Dynamic-Content-aware link format registration (#662).
 *
 * The built-in `core/link` format (registered as a side-effect of the
 * `@wordpress/format-library` import) renders the stock
 * `__experimentalLinkControl` in its inline popover, which has no seam
 * for adding a "pick a Dynamic Content field" step. Per the issue's
 * approach (a), this unregisters the built-in link format and re-registers
 * `core/link` with the same tag / attribute mapping — so existing `<a>`
 * content round-trips and the serialized output is unchanged — but with
 * an {@link DynamicLinkEdit} that swaps in {@link ArtisanPackLinkControl}.
 *
 * Registering under the same `core/link` name (rather than a new format
 * type) keeps every `allowedFormats: ['core/link']` list, the mod+k
 * shortcut, and the SSR resolver's `<a href="{{token}}">` rewrite working
 * untouched.
 *
 * @since 1.7.0
 */

import { registerFormatType, store as richTextStore, unregisterFormatType } from '@wordpress/rich-text';
import { select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { LINK_ATTRIBUTES, LINK_FORMAT_NAME } from './constants';
import { DynamicLinkEdit } from './edit';

export { LINK_FORMAT_NAME } from './constants';

let installed = false;

/**
 * Swap the built-in `core/link` edit for the Dynamic-Content-aware one.
 * Idempotent — safe to call from more than one editor bootstrap.
 *
 * @since 1.7.0
 */
export function registerDynamicLinkFormat(): void {
    if ( installed ) {
        return;
    }
    installed = true;

    // `getFormatType` (singular) isn't a public `@wordpress/rich-text`
    // export, so query the format store directly.
    const selectors = select( richTextStore as never ) as unknown as {
        getFormatType( name: string ): unknown;
    };

    if ( selectors.getFormatType( LINK_FORMAT_NAME ) ) {
        unregisterFormatType( LINK_FORMAT_NAME );
    }

    registerFormatType( LINK_FORMAT_NAME, {
        title: __( 'Link', TEXT_DOMAIN ),
        tagName: 'a',
        className: null,
        attributes: { ...LINK_ATTRIBUTES },
        edit: DynamicLinkEdit,
    } as unknown as Parameters< typeof registerFormatType >[ 1 ] );
}
