/**
 * One-shot registrar for the CSS positioning feature (#640).
 *
 * Bootstraps the supports-extension filter (auto-add `style.position`
 * to opted-in blocks' responsive routing), the BlockEdit HOC
 * (inspector Position panel + ancestor warning) and the style-
 * emission filters (canvas preview + save markup).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { registerPositionControl } from './with-position-control'
import { registerPositionStylesFilters } from './with-position-styles'
import { registerPositionSupportsExtension } from './extend-supports'

/**
 * Install every filter the position feature needs. Idempotent — each
 * registrar guards itself with a sentinel so calling this from both
 * the post-editor and site-editor entries is safe.
 *
 * @since 1.4.0
 */
export function registerPositioning(): void {
	registerPositionSupportsExtension()
	registerPositionControl()
	registerPositionStylesFilters()
}
