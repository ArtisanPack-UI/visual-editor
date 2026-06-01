import { render, act } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { resetStateStores, setActiveState } from '../active-state'

vi.mock( '@wordpress/blocks', () => {
	const blocks = new Map<string, { supports?: Record<string, unknown> }>()

	return {
		getBlockType: ( name: string ) => blocks.get( name ),
		hasBlockSupport: (
			blockType: { supports?: Record<string, unknown> } | undefined,
			feature: string,
			defaultValue: boolean,
		) => {
			if ( ! blockType?.supports ) {
				return defaultValue
			}
			if ( ! ( feature in blockType.supports ) ) {
				return defaultValue
			}
			return blockType.supports[ feature ]
		},
		__setBlockType: ( name: string, supports: Record<string, unknown> ) => {
			blocks.set( name, { supports } )
		},
		__clearBlockTypes: () => {
			blocks.clear()
		},
	}
} )

import { __setBlockType, __clearBlockTypes } from '@wordpress/blocks'
import { withStateAttributes } from '../with-state-attributes'

interface CapturedProps {
	attributes: Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
}

let captured: CapturedProps | null = null

function StubEdit( props: CapturedProps ): JSX.Element {
	captured = props
	return <div data-testid="stub">stub</div>
}

const Wrapped = withStateAttributes( StubEdit as never )

beforeEach( () => {
	captured = null
	act( () => {
		resetStateStores()
	} )
	__clearBlockTypes()
} )

afterEach( () => {
	act( () => {
		resetStateStores()
	} )
	__clearBlockTypes()
} )

describe( 'withStateAttributes — non-stateful blocks', () => {
	it( 'passes attributes through untouched when supports.artisanpackStates is missing', () => {
		__setBlockType( 'core/paragraph', {} )

		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="core/paragraph"
				attributes={ { content: 'hi' } }
				setAttributes={ setAttributes }
			/> as never,
		)

		expect( captured?.attributes ).toEqual( { content: 'hi' } )

		captured?.setAttributes( { content: 'updated' } )
		expect( setAttributes ).toHaveBeenCalledWith( { content: 'updated' } )
	} )
} )

describe( 'withStateAttributes — idle behavior', () => {
	beforeEach( () => {
		__setBlockType( 'artisanpack/button', {
			artisanpackStates: { attributes: [ 'backgroundColor', 'textColor', 'style.color.background' ] },
		} )
	} )

	it( 'writes fall straight through to base on idle', () => {
		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/button"
				attributes={ { backgroundColor: 'vivid-purple' } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { backgroundColor: 'pale-pink' } )
		expect( setAttributes ).toHaveBeenCalledWith( { backgroundColor: 'pale-pink' } )
	} )
} )

describe( 'withStateAttributes — non-idle routing', () => {
	beforeEach( () => {
		__setBlockType( 'artisanpack/button', {
			artisanpackStates: { attributes: [ 'backgroundColor', 'textColor', 'style.color.background' ] },
		} )

		act( () => {
			setActiveState( 'hover' )
		} )
	} )

	it( 'routes a top-level palette write to attributes.states.backgroundColor.hover and mirrors to base (#515)', () => {
		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/button"
				attributes={ { backgroundColor: 'vivid-purple' } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { backgroundColor: 'pale-pink' } )

		expect( setAttributes ).toHaveBeenCalledTimes( 1 )
		const actual = setAttributes.mock.calls[ 0 ][ 0 ]

		// #515: the base is mirrored so the data store (read by
		// panels via useSelect) stays in lockstep with the active
		// state. The pristine idle value is restored before save.
		expect( actual.backgroundColor ).toBe( 'pale-pink' )
		expect( actual.states ).toEqual( {
			backgroundColor: { hover: 'pale-pink' },
		} )
	} )

	it( 'overlays the active state on top of the idle base for reads', () => {
		render(
			<Wrapped
				name="artisanpack/button"
				attributes={ {
					backgroundColor: 'vivid-purple',
					states:          { backgroundColor: { hover: 'pale-pink' } },
				} }
				setAttributes={ vi.fn() }
			/> as never,
		)

		expect( captured?.attributes.backgroundColor ).toBe( 'pale-pink' )
	} )

	it( 'mirrors state-eligible writes to the base so the data store stays in lockstep (#515)', () => {
		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/button"
				attributes={ { backgroundColor: 'vivid-purple' } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { backgroundColor: 'pale-pink' } )

		const actual = setAttributes.mock.calls[ 0 ][ 0 ]
		expect( actual ).toHaveProperty( 'backgroundColor', 'pale-pink' )
		expect( actual.states ).toEqual( {
			backgroundColor: { hover: 'pale-pink' },
		} )
	} )

	it( 'falls through to base for paths outside the state roots', () => {
		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/button"
				attributes={ { backgroundColor: 'vivid-purple', url: 'https://a.test' } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { url: 'https://b.test' } )

		expect( setAttributes ).toHaveBeenCalledWith( { url: 'https://b.test' } )
	} )
} )
