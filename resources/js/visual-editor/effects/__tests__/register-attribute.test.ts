/**
 * The `artisanpackEffects` attribute is injected on every block unless it
 * opts out (#881).
 */

import { applyFilters } from '@wordpress/hooks'
import { describe, expect, it } from 'vitest'

import { registerEffectsAttribute } from '../register-attribute'

registerEffectsAttribute()

function register( settings: Record<string, unknown> ): Record<string, unknown> {
	return applyFilters( 'blocks.registerBlockType', settings, 'test/block' ) as Record<string, unknown>
}

describe( 'registerEffectsAttribute', () => {
	it( 'adds the attribute to blocks', () => {
		expect( ( register( { attributes: {} } ).attributes as Record<string, unknown> ).artisanpackEffects )
			.toEqual( { type: 'object' } )
	} )

	it( 'skips blocks that opt out', () => {
		expect( register( { supports: { artisanpackEffects: false }, attributes: {} } ).attributes )
			.toEqual( {} )
	} )
} )
