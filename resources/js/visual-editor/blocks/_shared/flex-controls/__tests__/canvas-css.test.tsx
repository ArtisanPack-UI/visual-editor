/**
 * Custom Flex Panel values in the editor canvas (#1039).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { render } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { BreakpointRegistry } from '../../../../responsive/registry'
import { getCanvasScopedStyleElement } from '../../../../support/canvas-scoped-styles'
import {
	buildFlexCanvasCss,
	FLEX_ARBITRARY_STYLE_CHANNEL,
	useFlexCanvasStyles,
} from '../canvas-css'
import { serializeFlex } from '../serializer'
import type { ArtisanpackFlexAttribute } from '../types'

const registry = new BreakpointRegistry()

function css( flex: unknown ): string {
	return buildFlexCanvasCss( serializeFlex( flex as ArtisanpackFlexAttribute, registry ), registry )
}

function Publisher( { clientId, flex }: { clientId: string; flex: unknown } ): JSX.Element {
	useFlexCanvasStyles(
		clientId,
		serializeFlex( flex as ArtisanpackFlexAttribute, registry ),
		registry,
	)

	return <div id={ `block-${ clientId }` } />
}

describe( 'buildFlexCanvasCss', () => {
	it( 'prints a custom basis under the class the serializer emits', () => {
		expect( css( { item: { basis: { base: '14px' } } } ) ).toBe(
			'.ap-basis-\\[14px\\] { flex-basis: 14px; }',
		)
	} )

	it( 'wraps responsive values in the same media query as the front end', () => {
		const output = css( { item: { basis: { base: '14px', md: '40px' } } } )

		expect( output ).toContain( '.ap-basis-\\[14px\\] { flex-basis: 14px; }' )
		expect( output ).toContain(
			`@media ${ registry.mediaQuery( 'md', true ) } { .md\\:ap-basis-\\[40px\\] { flex-basis: 40px; } }`,
		)
	} )

	it( 'covers container values', () => {
		expect( css( { container: { gap: { row: { base: '2px' }, column: { base: '4px' } } } } ) ).toBe(
			'.ap-gap-y-\\[2px\\] { row-gap: 2px; } .ap-gap-x-\\[4px\\] { column-gap: 4px; }',
		)
	} )

	it( 'keeps a breakpoint keyword winning over a base custom value (#949)', () => {
		const output = css( { item: { basis: { base: '280px', mobile: 'auto' } } } )

		expect( output ).toContain( '.mobile\\:ap-basis-auto { flex-basis: auto; }' )
		expect( output.indexOf( 'mobile\\:ap-basis-auto' ) ).toBeGreaterThan(
			output.indexOf( 'ap-basis-\\[280px\\]' ),
		)
	} )

	it( 'prints nothing for token-only values', () => {
		expect( css( { item: { basis: { base: 'auto' }, grow: { base: 1 } } } ) ).toBe( '' )
	} )

	it.each( [
		[ 'declaration break', '14px;position:fixed' ],
		[ 'tag break', '1px}</style><script>' ],
		[ 'comment', '1px/*' ],
		[ 'blank', '   ' ],
	] )( 'drops a hostile value: %s', ( _label, value ) => {
		expect( css( { item: { basis: { base: value } } } ) ).toBe( '' )
	} )
} )

describe( 'useFlexCanvasStyles', () => {
	it( 'publishes rules while mounted and removes them on unmount', () => {
		const { unmount } = render(
			<Publisher clientId="flex-a" flex={ { item: { basis: { base: '14px' } } } } />,
		)

		expect( getCanvasScopedStyleElement( FLEX_ARBITRARY_STYLE_CHANNEL )?.textContent ).toContain(
			'flex-basis: 14px',
		)

		unmount()

		expect( getCanvasScopedStyleElement( FLEX_ARBITRARY_STYLE_CHANNEL ) ).toBeNull()
	} )

	it( 'keeps a shared rule until the last block using it unmounts', () => {
		const flex   = { item: { basis: { base: '14px' } } }
		const first  = render( <Publisher clientId="flex-b" flex={ flex } /> )
		const second = render( <Publisher clientId="flex-c" flex={ flex } /> )

		first.unmount()

		expect( getCanvasScopedStyleElement( FLEX_ARBITRARY_STYLE_CHANNEL )?.textContent ).toContain(
			'flex-basis: 14px',
		)

		second.unmount()
	} )

	it( 'adds no style host for a block without custom values', () => {
		render( <Publisher clientId="flex-d" flex={ { item: { grow: { base: 1 } } } } /> )

		expect( getCanvasScopedStyleElement( FLEX_ARBITRARY_STYLE_CHANNEL ) ).toBeNull()
	} )
} )
