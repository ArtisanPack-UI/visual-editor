import { render, act } from '@testing-library/react'
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
import { RichTextData } from '@wordpress/rich-text'
import { buildOverlay, withResponsiveAttributes } from '../with-responsive-attributes'
import { pathMatchesAnyRoot } from '../attribute-paths'
import { getResponsiveRegistry } from '../registry'

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

describe( 'withResponsiveAttributes — rich text at a device breakpoint (#820)', () => {
	it( 'forwards typed RichTextData content to setAttributes at Mobile', () => {
		__setBlockType( 'artisanpack/paragraph', {
			artisanpackResponsive: { attributes: [ 'style.position' ] },
		} )

		const setAttributes = vi.fn()
		const previous      = RichTextData.fromHTMLString( 'C' )
		const typed         = RichTextData.fromHTMLString( 'Column 1' )

		act( () => {
			setActiveBreakpoint( 'mobile' )
		} )

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ { content: previous } }
				setAttributes={ setAttributes }
			/> as never,
		)

		act( () => {
			captured?.setAttributes( { content: typed } )
		} )

		expect( setAttributes ).toHaveBeenCalledWith( { content: typed } )
	} )
} )

describe( 'withResponsiveAttributes — preset / custom pairs (#887)', () => {
	const TEXT_ROOTS = [ 'fontSize', 'textColor', 'style.color.text', 'backgroundColor', 'style.color.background' ]

	beforeEach( () => {
		__setBlockType( 'artisanpack/paragraph', {
			artisanpackResponsive: { attributes: TEXT_ROOTS },
		} )

		act( () => {
			setActiveBreakpoint( 'mobile' )
		} )
	} )

	function renderParagraph( attributes: Record<string, unknown>, setAttributes = vi.fn() ): typeof setAttributes {
		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ { content: 'Copy', ...attributes } }
				setAttributes={ setAttributes }
			/> as never,
		)

		return setAttributes
	}

	it( 'routes the custom font size through the `fontSize` root', () => {
		expect( pathMatchesAnyRoot( 'style.typography.fontSize', TEXT_ROOTS ) ).toBe( true )

		const setAttributes = renderParagraph( {} )

		captured?.setAttributes( { style: { typography: { fontSize: '18px' } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.typography.fontSize': { mobile: '18px' },
				fontSize: { mobile: false },
			},
		} )
	} )

	it( 'tombstones the inherited custom size when Mobile picks a preset', () => {
		const setAttributes = renderParagraph( {
			responsive: { 'style.typography.fontSize': { tablet: '20px' } },
		} )

		captured?.setAttributes( { fontSize: 'small', style: { typography: { fontSize: undefined } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.typography.fontSize': { tablet: '20px', mobile: false },
				fontSize: { mobile: 'small' },
			},
		} )
	} )

	it( 'tombstones the preset when Mobile switches to a custom value', () => {
		const setAttributes = renderParagraph( {
			responsive: { fontSize: { mobile: 'small' }, 'style.typography.fontSize': { mobile: false } },
		} )

		captured?.setAttributes( { fontSize: undefined, style: { typography: { fontSize: '18px' } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				fontSize: { mobile: false },
				'style.typography.fontSize': { mobile: '18px' },
			},
		} )
	} )

	it( 'drops the twin tombstone when the value at that breakpoint is cleared', () => {
		const setAttributes = renderParagraph( {
			responsive: {
				fontSize: { mobile: 'small' },
				'style.typography.fontSize': { tablet: '20px', mobile: false },
			},
		} )

		captured?.setAttributes( { fontSize: undefined } )

		const responsive = setAttributes.mock.calls[ 0 ][ 0 ].responsive

		expect( responsive.fontSize.mobile ).toBeUndefined()
		expect( responsive[ 'style.typography.fontSize' ] ).toEqual( { tablet: '20px' } )
	} )

	it( 'tombstones the colour twins the same way', () => {
		const setAttributes = renderParagraph( {
			responsive: { 'style.color.background': { tablet: '#0f172a' } },
		} )

		captured?.setAttributes( { backgroundColor: 'accent', textColor: 'contrast' } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.color.background': { tablet: '#0f172a', mobile: false },
				backgroundColor: { mobile: 'accent' },
				textColor: { mobile: 'contrast' },
				'style.color.text': { mobile: false },
			},
		} )
	} )

	it( 'shows only the Mobile preset, not the Tablet custom size, at Mobile', () => {
		renderParagraph( {
			responsive: {
				fontSize: { mobile: 'small' },
				'style.typography.fontSize': { tablet: '20px', mobile: false },
			},
		} )

		const style = captured?.attributes.style as { typography?: { fontSize?: unknown } } | undefined

		expect( captured?.attributes.fontSize ).toBe( 'small' )
		expect( style?.typography?.fontSize ).toBeUndefined()
	} )

	it( 'hides a base custom size behind a Mobile tombstone', () => {
		renderParagraph( {
			style: { typography: { fontSize: '30px' } },
			responsive: {
				fontSize: { mobile: 'small' },
				'style.typography.fontSize': { mobile: false },
			},
		} )

		const style = captured?.attributes.style as { typography?: { fontSize?: unknown } } | undefined

		expect( style?.typography?.fontSize ).toBeUndefined()
	} )

	it( 'keeps cascading past a narrower tombstone at Tablet', () => {
		act( () => {
			setActiveBreakpoint( 'tablet' )
		} )

		renderParagraph( {
			responsive: {
				fontSize: { mobile: 'small' },
				'style.typography.fontSize': { tablet: '20px', mobile: false },
			},
		} )

		const style = captured?.attributes.style as { typography?: { fontSize?: unknown } } | undefined

		expect( style?.typography?.fontSize ).toBe( '20px' )
		expect( captured?.attributes.fontSize ).toBeUndefined()
	} )

	it( 'treats `false` on an unpaired path as an ordinary value', () => {
		expect( buildOverlay( { isStackedOnMobile: { mobile: false } }, 'mobile', getResponsiveRegistry() ) )
			.toEqual( { isStackedOnMobile: false } )
	} )
} )

function deepFreeze<T>( value: T ): T {
	if ( null !== value && 'object' === typeof value ) {
		for ( const child of Object.values( value as Record<string, unknown> ) ) {
			deepFreeze( child )
		}

		Object.freeze( value )
	}

	return value
}

describe( 'withResponsiveAttributes — base writes keep their siblings', () => {
	beforeEach( () => {
		__setBlockType( 'artisanpack/paragraph', {
			artisanpackResponsive: { attributes: [ 'spacing', 'fontSize', 'style.typography.fontSize' ] },
		} )

		act( () => {
			setActiveBreakpoint( 'md' )
		} )
	} )

	it( 'keeps the other base style values when a non-responsive leaf changes at a breakpoint', () => {
		const setAttributes = vi.fn()
		const attributes    = deepFreeze( {
			content: 'Copy',
			style: { typography: { lineHeight: '1.5' }, border: { width: '1px' } },
		} )

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ attributes }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( {
			style: { typography: { lineHeight: '1.5' }, border: { width: '1px', color: '#f00' } },
		} )

		expect( setAttributes ).toHaveBeenCalledWith( {
			style: { typography: { lineHeight: '1.5' }, border: { width: '1px', color: '#f00' } },
		} )
	} )

	it( 'keeps siblings when the panel sends only the changed subtree', () => {
		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ deepFreeze( {
					style: { typography: { lineHeight: '1.5' }, border: { width: '1px' } },
				} ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { style: { border: { color: '#f00' } } } )

		const style = setAttributes.mock.calls[ 0 ][ 0 ].style

		expect( style.typography.lineHeight ).toBe( '1.5' )
		expect( style.border.width ).toBe( '1px' )
		expect( style.border.color ).toBe( '#f00' )
	} )

	it( 'routes a responsive leaf and keeps the base siblings of a mixed edit', () => {
		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ deepFreeze( {
					style: {
						spacing: { padding: '1rem' },
						typography: { lineHeight: '1.5' },
						border: { width: '1px' },
					},
					responsive: { 'style.spacing.padding': { md: '2rem' } },
				} ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		// The panel works from the read view, so it echoes the md padding.
		captured?.setAttributes( {
			style: {
				spacing: { padding: '3rem' },
				typography: { lineHeight: '1.5' },
				border: { width: '1px', style: 'dashed' },
			},
		} )

		expect( setAttributes ).toHaveBeenCalledWith( {
			style: {
				spacing: { padding: '1rem' },
				typography: { lineHeight: '1.5' },
				border: { width: '1px', style: 'dashed' },
			},
			responsive: { 'style.spacing.padding': { md: '3rem' } },
		} )
	} )

	it( 'never copies a breakpoint override into the base', () => {
		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ deepFreeze( {
					style: { spacing: { padding: '1rem' }, border: { width: '1px' } },
					responsive: { 'style.spacing.padding': { md: '2rem' } },
				} ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		expect( ( captured?.attributes.style as { spacing: { padding: string } } ).spacing.padding ).toBe( '2rem' )

		captured?.setAttributes( {
			style: { spacing: { padding: '2rem' }, border: { width: '1px', color: '#000' } },
		} )

		expect( setAttributes ).toHaveBeenCalledWith( {
			style: { spacing: { padding: '1rem' }, border: { width: '1px', color: '#000' } },
		} )
	} )

	it( 'writes a nested deep path without dropping sibling branches', () => {
		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ deepFreeze( {
					style: {
						border: {
							top: { width: '1px', color: '#111' },
							bottom: { width: '2px' },
						},
						shadow: 'var:preset|shadow|natural',
					},
				} ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { style: { border: { top: { style: 'dotted' } } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			style: {
				border: {
					top: { width: '1px', color: '#111', style: 'dotted' },
					bottom: { width: '2px' },
				},
				shadow: 'var:preset|shadow|natural',
			},
		} )
	} )

	it( 'clears a base leaf without touching its siblings', () => {
		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ deepFreeze( {
					style: { typography: { lineHeight: '1.5' }, border: { width: '1px', color: '#f00' } },
				} ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { style: { border: { color: undefined } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			style: { typography: { lineHeight: '1.5' }, border: { width: '1px' } },
		} )
	} )

	it( 'clears the top-level key when its last base leaf is removed', () => {
		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="artisanpack/paragraph"
				attributes={ deepFreeze( { style: { border: { color: '#f00' } } } ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { style: { border: { color: undefined } } } )

		const updates = setAttributes.mock.calls[ 0 ][ 0 ]

		// `toEqual` ignores undefined keys, so assert the key is sent.
		expect( Object.keys( updates ) ).toEqual( [ 'style' ] )
		expect( updates.style ).toBeUndefined()
	} )
} )
