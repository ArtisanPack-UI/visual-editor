/**
 * One-shot registrar for block effects (#881): the `artisanpackEffects`
 * attribute, the Effects inspector panel and the canvas preview.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

import { registerEffectsAttribute } from './register-attribute'
import { registerEffectsFilters } from './with-effects'

/**
 * Install every filter the effects feature needs. Idempotent.
 *
 * @since 1.15.0
 */
export function registerEffects(): void {
	registerEffectsAttribute()
	registerEffectsFilters()
}
