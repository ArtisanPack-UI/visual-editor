/**
 * The React and Vue flex serializers read the host-configurable
 * breakpoints from `setBreakpoints()` (M-17), as Blade's `FlexSupport`
 * reads `BreakpointRegistry`. Default output is pinned by the shared
 * flex fixtures and the markup-parity goldens.
 */

import { afterEach, describe, expect, it } from 'vitest'

import * as reactFlex from '../src/support/flex-serializer'
import * as reactVisibility from '../src/visibility'
import * as vueFlex from '../../visual-editor-renderer-vue/src/support/flex-serializer'
import * as vueVisibility from '../../visual-editor-renderer-vue/src/visibility'

const RULES = [
	{ className: 'ap-gap-x-[1rem]', property: 'column-gap', value: '1rem', breakpoint: 'base' },
	{ className: 'md:ap-gap-x-[2rem]', property: 'column-gap', value: '2rem', breakpoint: 'md' },
	{ className: 'mobile:ap-gap-x-[3rem]', property: 'column-gap', value: '3rem', breakpoint: 'mobile' },
]

describe.each( [
	[ 'react', reactFlex, reactVisibility ],
	[ 'vue', vueFlex, vueVisibility ],
] )( '%s flex serializer breakpoints', ( _name, flex, visibility ) => {
	afterEach( () => {
		visibility.setBreakpoints( {} )
	} )

	it( 'keeps the default media queries', () => {
		expect( flex.buildArbitraryStyles( RULES ) ).toBe(
			'.ap-gap-x-\\[1rem\\] { column-gap: 1rem; } '
				+ '@media (min-width: 768px) { .md\\:ap-gap-x-\\[2rem\\] { column-gap: 2rem; } } '
				+ '@media (max-width: 767px) { .mobile\\:ap-gap-x-\\[3rem\\] { column-gap: 3rem; } }',
		)
	} )

	it( 'uses host breakpoint overrides for arbitrary-value media queries', () => {
		visibility.setBreakpoints( { md: 900, mobile: { maxWidthPx: 599, previewWidthPx: 375 } } )

		expect( flex.buildArbitraryStyles( RULES ) ).toBe(
			'.ap-gap-x-\\[1rem\\] { column-gap: 1rem; } '
				+ '@media (min-width: 900px) { .md\\:ap-gap-x-\\[2rem\\] { column-gap: 2rem; } } '
				+ '@media (max-width: 599px) { .mobile\\:ap-gap-x-\\[3rem\\] { column-gap: 3rem; } }',
		)
	} )

	it( 'emits classes for a host-added breakpoint key', () => {
		const input = { container: { enabled: { base: true }, direction: { base: 'column', xs: 'row' } } }

		expect( flex.serializeFlex( input ).classes ).not.toContain( 'xs:ap-flex-row' )

		visibility.setBreakpoints( { xs: 480 } )

		expect( flex.serializeFlex( input ).classes ).toContain( 'xs:ap-flex-row' )
	} )
} )
