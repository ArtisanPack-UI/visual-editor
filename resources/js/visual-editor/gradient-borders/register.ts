/**
 * One-shot registrar for the gradient border feature (#490).
 *
 * Bootstraps both the BlockEdit HOC (inspector picker + token-missing
 * warning) and the supports-extension filter (auto-add `border.gradient`
 * to opted-in blocks' state/responsive routing lists).
 *
 * Kept in its own file rather than the package barrel so host bundlers
 * that walk `./index.ts` transitively don't pull in `@wordpress/blocks`
 * (which would trip JSON-import-attribute checks). The post-editor and
 * site-editor bootstraps import this file directly.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { registerGradientBorderControl } from './with-gradient-border-control'
import { registerGradientBorderSupportsExtension } from './extend-supports'
import { registerGradientBorderStylesFilters } from './with-gradient-border-styles'

/**
 * Install every filter the gradient border feature needs. Idempotent —
 * each registrar guards itself with a sentinel so calling this from
 * both post-editor and site-editor entries is safe.
 *
 * @since 1.1.0
 */
export function registerGradientBorders(): void {
	registerGradientBorderSupportsExtension()
	registerGradientBorderControl()
	registerGradientBorderStylesFilters()
}
