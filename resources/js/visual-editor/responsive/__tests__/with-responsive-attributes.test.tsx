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

describe( 'withResponsiveAttributes — spacing overrides use the renderer paths (#918)', () => {
	beforeEach( () => {
		__setBlockType( 'artisanpack/columns', {
			artisanpackResponsive: { attributes: [ 'spacing', 'align', 'columnCount' ] },
		} )

		act( () => {
			setActiveBreakpoint( 'tablet' )
		} )
	} )

	function renderColumns( attributes: Record<string, unknown>, setAttributes = vi.fn() ): typeof setAttributes {
		render(
			<Wrapped
				name="artisanpack/columns"
				attributes={ deepFreeze( attributes ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		return setAttributes
	}

	it( 'splits a first spacing edit on a block with no style into the property paths', () => {
		const setAttributes = renderColumns( {} )

		captured?.setAttributes( { style: { spacing: { padding: '2rem', blockGap: { left: '3em' } } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.spacing.padding': { tablet: '2rem' },
				'style.spacing.blockGap': { tablet: { left: '3em' } },
			},
		} )
	} )

	it( 'splits a first spacing edit on a block whose style has no spacing', () => {
		const setAttributes = renderColumns( { style: { color: { text: '#111' } } } )

		captured?.setAttributes( { style: { color: { text: '#111' }, spacing: { margin: { top: '1rem' } } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.spacing.margin': { tablet: { top: '1rem' } },
			},
		} )
	} )

	it( 'keeps a gap edit on spacing without blockGap at the gap path', () => {
		const setAttributes = renderColumns( { style: { spacing: { padding: '1rem' } } } )

		captured?.setAttributes( { style: { spacing: { padding: '1rem', blockGap: '2em' } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: { 'style.spacing.blockGap': { tablet: '2em' } },
		} )
	} )

	it( 'collapses one axis of a per-axis base gap into the gap path', () => {
		const setAttributes = renderColumns( { style: { spacing: { blockGap: { top: '1em', left: '2em' } } } } )

		captured?.setAttributes( { style: { spacing: { blockGap: { top: '1em', left: '3em' } } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: { 'style.spacing.blockGap': { tablet: { left: '3em' } } },
		} )
	} )

	it( 'merges an axis into the breakpoint\'s existing gap override', () => {
		const setAttributes = renderColumns( {
			style: { spacing: { blockGap: { top: '1em', left: '2em' } } },
			responsive: { 'style.spacing.blockGap': { tablet: { left: '3em' }, mobile: '0' } },
		} )

		captured?.setAttributes( { style: { spacing: { blockGap: { top: '4em', left: '3em' } } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: { 'style.spacing.blockGap': { tablet: { left: '3em', top: '4em' }, mobile: '0' } },
		} )
	} )

	it( 'collapses per-side padding and margin edits into their paths', () => {
		const setAttributes = renderColumns( {
			style: { spacing: { padding: { top: '1rem', bottom: '1rem' }, margin: { top: '0', bottom: '0' } } },
		} )

		captured?.setAttributes( {
			style: { spacing: { padding: { top: '2rem', bottom: '3rem' }, margin: { top: '0', bottom: '4rem' } } },
		} )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.spacing.padding': { tablet: { top: '2rem', bottom: '3rem' } },
				'style.spacing.margin': { tablet: { bottom: '4rem' } },
			},
		} )
	} )

	it( 'drops a cleared side and the breakpoint once no side is left', () => {
		const setAttributes = renderColumns( {
			style: { spacing: { padding: { top: '1rem' } } },
			responsive: { 'style.spacing.padding': { tablet: { top: '2rem' }, mobile: '0' } },
		} )

		captured?.setAttributes( { style: { spacing: { padding: { top: undefined } } } } )

		const responsive = setAttributes.mock.calls[ 0 ][ 0 ].responsive

		expect( responsive[ 'style.spacing.padding' ].tablet ).toBeUndefined()
		expect( responsive[ 'style.spacing.padding' ].mobile ).toBe( '0' )
	} )

	it( 'folds legacy deep and shallow keys into the property path on the next edit', () => {
		const setAttributes = renderColumns( {
			style: { spacing: { blockGap: { top: '1em', left: '2em' } } },
			responsive: {
				'style.spacing.blockGap.left': { tablet: '3em', mobile: '1em' },
				'style.spacing': { mobile: { padding: '1rem' } },
				columnCount: { mobile: 1 },
			},
		} )

		captured?.setAttributes( { style: { spacing: { blockGap: { top: '5em', left: '3em' } } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				columnCount: { mobile: 1 },
				'style.spacing.blockGap': { tablet: { left: '3em', top: '5em' }, mobile: { left: '1em' } },
				'style.spacing.padding': { mobile: '1rem' },
			},
		} )
	} )

	it( 'collapses a per-corner border radius under a `border` root', () => {
		__setBlockType( 'acme/card', {
			artisanpackResponsive: { attributes: [ 'border' ] },
		} )

		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="acme/card"
				attributes={ deepFreeze( { style: { border: { radius: { topLeft: '4px', topRight: '4px' } } } } ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { style: { border: { radius: { topLeft: '8px', topRight: '4px' } } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: { 'style.border.radius': { tablet: { topLeft: '8px' } } },
		} )
	} )

	it( 'splits a first border edit under a `border` root into its property paths', () => {
		__setBlockType( 'acme/card', {
			artisanpackResponsive: { attributes: [ 'border' ] },
		} )

		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="acme/card"
				attributes={ {} }
				setAttributes={ setAttributes }
			/> as never,
		)

		captured?.setAttributes( { style: { border: { width: '2px', style: 'solid' } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.border.width': { tablet: '2px' },
				'style.border.style': { tablet: 'solid' },
			},
		} )
	} )
} )

describe( 'withResponsiveAttributes — coarse roots beyond spacing (#918)', () => {
	beforeEach( () => {
		act( () => {
			setActiveBreakpoint( 'tablet' )
		} )
	} )

	function renderBlock( roots: string[], attributes: Record<string, unknown> ): ReturnType<typeof vi.fn> {
		__setBlockType( 'acme/box', { artisanpackResponsive: { attributes: roots } } )

		const setAttributes = vi.fn()

		render(
			<Wrapped
				name="acme/box"
				attributes={ deepFreeze( attributes ) }
				setAttributes={ setAttributes }
			/> as never,
		)

		return setAttributes
	}

	it( 'splits a first dimensions edit under a `dimensions` root', () => {
		const setAttributes = renderBlock( [ 'dimensions' ], {} )

		captured?.setAttributes( { style: { dimensions: { minHeight: '50vh' } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: { 'style.dimensions.minHeight': { tablet: '50vh' } },
		} )
	} )

	it( 'splits a first typography edit under a `typography` root, keeping its #887 pair', () => {
		const setAttributes = renderBlock( [ 'typography' ], {} )

		captured?.setAttributes( { style: { typography: { fontSize: '20px', lineHeight: '1.4' } } } )

		expect( setAttributes ).toHaveBeenCalledWith( {
			responsive: {
				'style.typography.fontSize': { tablet: '20px' },
				'style.typography.lineHeight': { tablet: '1.4' },
				fontSize: { tablet: false },
			},
		} )
	} )

	it( 'clears only the stored property paths when a whole spacing object is reset', () => {
		const setAttributes = renderBlock( [ 'spacing' ], {
			responsive: {
				'style.spacing.padding': { tablet: '2rem', mobile: '1rem' },
				'style.spacing.margin': { mobile: '0' },
			},
		} )

		captured?.setAttributes( { style: { spacing: undefined } } )

		const responsive = setAttributes.mock.calls[ 0 ][ 0 ].responsive

		expect( Object.keys( responsive ).sort() ).toEqual( [ 'style.spacing.margin', 'style.spacing.padding' ] )
		expect( responsive[ 'style.spacing.padding' ] ).toEqual( { mobile: '1rem' } )
		expect( responsive[ 'style.spacing.margin' ] ).toEqual( { mobile: '0' } )
	} )
} )

describe( 'buildOverlay — spacing matches the front end (#918)', () => {
	const registry = getResponsiveRegistry()

	it( 'reads legacy deep and shallow keys at the property path', () => {
		expect( buildOverlay( {
			'style.spacing.blockGap.left': { tablet: '3em' },
			'style.spacing': { tablet: { padding: '1rem' } },
		}, 'tablet', registry ) ).toEqual( {
			style: { spacing: { blockGap: { left: '3em' }, padding: '1rem' } },
		} )
	} )

	it( 'prefers the property path over a legacy key at the same breakpoint', () => {
		expect( buildOverlay( {
			'style.spacing.blockGap': { tablet: { top: '1em' } },
			'style.spacing.blockGap.left': { tablet: '3em', mobile: '4em' },
		}, 'tablet', registry ) ).toEqual( {
			style: { spacing: { blockGap: { top: '1em' } } },
		} )
	} )

	it( 'combines sides across the cascade the way the media rules do', () => {
		expect( buildOverlay( {
			'style.spacing.padding': { tablet: { top: '1rem' }, mobile: { left: '2rem' } },
			'style.spacing.margin': { tablet: '3rem', mobile: { bottom: '0' } },
		}, 'mobile', registry ) ).toEqual( {
			style: {
				spacing: {
					padding: { left: '2rem', top: '1rem' },
					margin: { bottom: '0', top: '3rem', right: '3rem', left: '3rem' },
				},
			},
		} )
	} )

	it( 'fills the sides an override leaves out from a scalar base value', () => {
		expect( buildOverlay(
			{ 'style.spacing.blockGap': { tablet: { left: '3em' } } },
			'tablet',
			registry,
			{ style: { spacing: { blockGap: '2em' } } },
		) ).toEqual( {
			style: { spacing: { blockGap: { left: '3em', top: '2em' } } },
		} )
	} )
} )
