import { render, screen, act } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import {
	resetActiveBreakpoint,
	setActiveBreakpoint,
} from '../active-breakpoint'

// `withResponsiveAttributes` uses `getBlockType` to discover the
// opt-in roots. Stub it so tests don't need to register a real block.
vi.mock( '@wordpress/blocks', () => {
	const blocks = new Map<string, { supports?: Record<string, unknown> }>()

	return {
		getBlockType: ( name: string ) => blocks.get( name ),
		// Mirrors the real `@wordpress/blocks.hasBlockSupport` contract:
		// returns the actual configured value (not just `feature in supports`),
		// so a falsy support entry is honored. Falls back to `defaultValue`
		// when the block isn't registered or doesn't declare supports.
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
import { withResponsiveAttributes } from '../with-responsive-attributes'

interface CapturedProps {
	attributes: Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
}

let captured: CapturedProps | null = null

function StubEdit( props: CapturedProps ): JSX.Element {
	captured = props
	return <div data-testid="stub">stub</div>
}

const Wrapped = withResponsiveAttributes( StubEdit as never )

beforeEach( () => {
	captured = null
	act( () => {
		resetActiveBreakpoint()
	} )
	__clearBlockTypes()
} )

afterEach( () => {
	act( () => {
		resetActiveBreakpoint()
	} )
	__clearBlockTypes()
} )

describe( 'withResponsiveAttributes — non-responsive blocks', () => {
	it( 'passes attributes through untouched when supports.artisanpackResponsive is missing', () => {
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

describe( 'withResponsiveAttributes — base breakpoint behavior', () => {
	beforeEach( () => {
		__setBlockType( 'artisanpack/columns', {
			artisanpackResponsive: { attributes: [ 'spacing', 'columnCount' ] },
		} )
	} )

	it( 'passes base attributes through untouched at base', () => {
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ { columnCount: 3 } }
				setAttributes={ vi.fn() }
			/> as never,
		)

		expect( captured?.attributes.columnCount ).toBe( 3 )
	} )

	it( 'forwards writes to setAttributes verbatim at base', () => {
		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ { columnCount: 3 } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { columnCount: 5 } )
		expect( setAttributes ).toHaveBeenCalledWith( { columnCount: 5 } )
	} )
} )

describe( 'withResponsiveAttributes — non-base breakpoint behavior', () => {
	beforeEach( () => {
		__setBlockType( 'artisanpack/columns', {
			artisanpackResponsive: { attributes: [ 'spacing', 'columnCount' ] },
		} )
	} )

	it( 'merges responsive overrides into the attributes the inner BlockEdit sees', () => {
		act( () => {
			setActiveBreakpoint( 'md' )
		} )

		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ {
					columnCount: 3,
					responsive: {
						columnCount: { md: 5 },
					},
				} }
				setAttributes={ vi.fn() }
			/> as never,
		)

		expect( captured?.attributes.columnCount ).toBe( 5 )
	} )

	it( 'cascades a smaller breakpoint up through null/missing slots', () => {
		act( () => {
			setActiveBreakpoint( 'lg' )
		} )

		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ {
					columnCount: 3,
					responsive: {
						columnCount: { sm: 1, md: 2 },
					},
				} }
				setAttributes={ vi.fn() }
			/> as never,
		)

		// lg has no override, md does → cascade returns 2.
		expect( captured?.attributes.columnCount ).toBe( 2 )
	} )

	it( 'routes a responsive write into attributes.responsive at the active breakpoint', () => {
		act( () => {
			setActiveBreakpoint( 'md' )
		} )

		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ { columnCount: 3 } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { columnCount: 5 } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				columnCount: { md: 5 },
			},
		} )
	} )

	it( 'merges into existing responsive overrides without dropping other breakpoints', () => {
		act( () => {
			setActiveBreakpoint( 'lg' )
		} )

		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ {
					columnCount: 3,
					responsive: { columnCount: { sm: 1 } },
				} }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { columnCount: 4 } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				columnCount: { sm: 1, lg: 4 },
			},
		} )
	} )

	it( 'falls through to the base for attributes outside the opt-in roots', () => {
		act( () => {
			setActiveBreakpoint( 'md' )
		} )

		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ { verticalAlignment: 'top' } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { verticalAlignment: 'center' } )

		// `verticalAlignment` is not declared in supports.artisanpackResponsive.attributes,
		// so it must write to the base attribute regardless of the active breakpoint.
		expect( setAttributes ).toHaveBeenCalledWith( { verticalAlignment: 'center' } )
	} )

	it( 'routes a deep spacing path into responsive without touching base style', () => {
		act( () => {
			setActiveBreakpoint( 'md' )
		} )

		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ {
					style: { spacing: { padding: '1rem' } },
				} }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( {
			style: { spacing: { padding: '2rem' } },
		} )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.spacing.padding': { md: '2rem' },
			},
		} )
	} )

	it( 'no-ops the write when nothing actually changed', () => {
		act( () => {
			setActiveBreakpoint( 'md' )
		} )

		const setAttributes = vi.fn()
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ { columnCount: 3 } }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { columnCount: 3 } )

		expect( setAttributes ).not.toHaveBeenCalled()
	} )
} )
