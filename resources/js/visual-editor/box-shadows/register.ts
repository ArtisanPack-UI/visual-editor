/**
 * One-shot registrar for the box-shadow feature (#607).
 *
 * Bootstraps the BlockEdit HOC (shadow tools panel + token-missing
 * warnings), the supports-extension filter (auto-add `style.shadow`
 * to opted-in blocks' state/responsive routing), and the style-
 * emission filters (canvas preview + save markup).
 *
 * Kept in its own file rather than the package barrel so host bundlers
 * walking `./index.ts` don't transitively pull in `@wordpress/blocks`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { registerBoxShadowControl } from './with-box-shadow-control'
import { registerBoxShadowSupportsExtension } from './extend-supports'
import { registerBoxShadowStylesFilters } from './with-box-shadow-styles'

/**
 * Install every filter the box-shadow feature needs. Idempotent —
 * each registrar guards itself with a sentinel so calling this from
 * both post-editor and site-editor entries is safe.
 *
 * @since 1.2.0
 */
export function registerBoxShadows(): void {
	registerBoxShadowSupportsExtension()
	registerBoxShadowControl()
	registerBoxShadowStylesFilters()
}
