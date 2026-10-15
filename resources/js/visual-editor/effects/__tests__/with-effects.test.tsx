/**
 * Effects panel (#881): Custom CSS validity is exposed and announced
 * (M-10, R-27), option labels are translated at render time (R-18), text
 * clip warns when nothing paints behind the text (R-16) and the canvas
 * honours `effects.custom_css_allow_urls` (F-11).
 */

import { act, render, screen, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ComponentType, ReactNode } from 'react'

const { speak, translate, translateWithContext } = vi.hoisted( () => ( {
	speak:                vi.fn(),
	translate:            vi.fn( ( text: string ) => text ),
	translateWithContext: vi.fn( ( text: string ) => text ),
} ) )

vi.mock( '@wordpress/a11y', () => ( { speak } ) )

vi.mock( '@wordpress/i18n', async ( importOriginal ) => ( {
	...( await importOriginal<typeof import( '@wordpress/i18n' )>() ),
	__: translate,
	_x: translateWithContext,
} ) )

vi.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children }: { children?: ReactNode } ) => <div data-testid="inspector">{ children }</div>,
} ) )

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType: () => ( { supports: {} } ),
} ) )

// Show every tools-panel item, so each control renders without the
// panel's "which items are shown" menu state.
vi.mock( '@wordpress/components', async ( importOriginal ) => ( {
	...( await importOriginal<typeof import( '@wordpress/components' )>() ),
	__experimentalToolsPanel: ( { children }: { children?: ReactNode } ) => <div>{ children }</div>,
	__experimentalToolsPanelItem: ( { children }: { children?: ReactNode } ) => <div>{ children }</div>,
} ) )

import { setBlockCssAccess, setCustomCssUrlAccess } from '../access'
import { CUSTOM_CSS_ANNOUNCE_DELAY, withEffectsControl, withEffectsStyles } from '../with-effects'
import { getCanvasScopedStyleElement } from '../../support/canvas-scoped-styles'

type Props = Record<string, unknown>

const BlockEdit: ComponentType<Props> = () => <div data-testid="block-edit" />

const EffectsControlled = withEffectsControl( BlockEdit as never ) as unknown as ComponentType<Props>

function panelProps( effects: Record<string, unknown>, attributes: Record<string, unknown> = {} ): Props {
	return {
		name:          'core/paragraph',
		clientId:      'block-1',
		attributes:    { artisanpackEffects: effects, ...attributes },
		setAttributes: vi.fn(),
		isSelected:    true,
	}
}

function textarea(): HTMLTextAreaElement {
	return screen.getByLabelText( 'Custom CSS' ) as HTMLTextAreaElement
}

beforeEach( () => {
	setBlockCssAccess( true )
	setCustomCssUrlAccess( true )
	speak.mockClear()
	translate.mockClear()
	translateWithContext.mockClear()
} )

afterEach( () => {
	vi.useRealTimers()
	setBlockCssAccess( false )
	setCustomCssUrlAccess( true )
} )

describe( 'Custom CSS field', () => {
	it( 'marks invalid CSS with aria-invalid and an Error: prefix', () => {
		render( <EffectsControlled { ...panelProps( { customCss: 'color: rgb(' } ) } /> )

		expect( textarea() ).toHaveAttribute( 'aria-invalid', 'true' )
		expect( screen.getByText( 'Error:' ) ).toBeInTheDocument()
	} )

	it( 'leaves valid CSS unmarked', () => {
		render( <EffectsControlled { ...panelProps( { customCss: 'color: red;' } ) } /> )

		expect( textarea() ).not.toHaveAttribute( 'aria-invalid' )
		expect( screen.queryByText( 'Error:' ) ).toBeNull()
	} )

	it( 'is left-to-right with spellcheck off', () => {
		render( <EffectsControlled { ...panelProps( { customCss: '' } ) } /> )

		expect( textarea() ).toHaveAttribute( 'dir', 'ltr' )
		expect( textarea() ).toHaveAttribute( 'spellcheck', 'false' )
	} )

	it( 'announces a validity change once it settles, not on each keystroke', () => {
		vi.useFakeTimers()

		const { rerender } = render( <EffectsControlled { ...panelProps( { customCss: 'color: red;' } ) } /> )

		for ( const css of [ 'color: red; a(', 'color: red; a(b', 'color: red; a(b ' ] ) {
			rerender( <EffectsControlled { ...panelProps( { customCss: css } ) } /> )
			act( () => {
				vi.advanceTimersByTime( 100 )
			} )
		}

		expect( speak ).not.toHaveBeenCalled()

		act( () => {
			vi.advanceTimersByTime( CUSTOM_CSS_ANNOUNCE_DELAY )
		} )

		expect( speak ).toHaveBeenCalledTimes( 1 )
		expect( speak.mock.calls[ 0 ][ 0 ] ).toContain( 'error' )

		rerender( <EffectsControlled { ...panelProps( { customCss: 'color: red; a(b)' } ) } /> )
		act( () => {
			vi.advanceTimersByTime( CUSTOM_CSS_ANNOUNCE_DELAY )
		} )

		expect( speak ).toHaveBeenCalledTimes( 2 )
		expect( speak.mock.calls[ 1 ][ 0 ] ).toBe( 'Custom CSS is valid.' )
	} )

	it( 'stays quiet when validity flips back before it settles', () => {
		vi.useFakeTimers()

		const { rerender } = render( <EffectsControlled { ...panelProps( { customCss: 'color: red;' } ) } /> )

		rerender( <EffectsControlled { ...panelProps( { customCss: 'color: red; a(' } ) } /> )
		rerender( <EffectsControlled { ...panelProps( { customCss: 'color: red; a()' } ) } /> )
		act( () => {
			vi.advanceTimersByTime( CUSTOM_CSS_ANNOUNCE_DELAY * 2 )
		} )

		expect( speak ).not.toHaveBeenCalled()
	} )

	it( 'is hidden without the block-CSS ability', () => {
		setBlockCssAccess( false )

		render( <EffectsControlled { ...panelProps( { customCss: 'color: red;' } ) } /> )

		expect( screen.queryByLabelText( 'Custom CSS' ) ).toBeNull()
	} )

	it( 'explains a URL rejection when the site disallows URLs', () => {
		setCustomCssUrlAccess( false )

		render( <EffectsControlled { ...panelProps( { customCss: 'background: url("a.png");' } ) } /> )

		expect( textarea() ).toHaveAttribute( 'aria-invalid', 'true' )
		expect( screen.getByText( /doesn’t allow Custom CSS to load URLs/ ) ).toBeInTheDocument()
	} )
} )

describe( 'Text clip', () => {
	it( 'warns when the block has no gradient or background image', () => {
		const { container } = render( <EffectsControlled { ...panelProps( { textClip: true } ) } /> )

		expect( within( container ).getByText( /no gradient or background image/ ) ).toBeInTheDocument()
	} )

	it( 'stays quiet over a gradient', () => {
		const { container } = render( <EffectsControlled { ...panelProps( { textClip: true }, { gradient: 'cool-to-warm' } ) } /> )

		expect( within( container ).queryByText( /no gradient or background image/ ) ).toBeNull()
	} )
} )

describe( 'Option labels', () => {
	it( 'translates labels at render time, not at import', () => {
		expect( translate.mock.calls.map( ( [ text ] ) => text ) ).not.toContain( 'Circle' )
		expect( translateWithContext ).not.toHaveBeenCalled()

		translateWithContext.mockImplementation( ( text: string ) => `T:${ text }` )
		render( <EffectsControlled { ...panelProps( {} ) } /> )
		translateWithContext.mockImplementation( ( text: string ) => text )

		expect( translate.mock.calls.map( ( [ text ] ) => text ) ).toContain( 'Circle' )
		expect( screen.getAllByRole( 'option', { name: 'T:Hidden' } ) ).toHaveLength( 2 )
		expect( screen.getByRole( 'option', { name: 'T:Color dodge' } ) ).toHaveValue( 'color-dodge' )
	} )

	it( 'uses US spelling in the z-index help', () => {
		render( <EffectsControlled { ...panelProps( {} ) } /> )

		expect( screen.getByText( /above or below its neighbors/ ) ).toBeInTheDocument()
	} )
} )

describe( 'Canvas preview', () => {
	const BlockListBlock: ComponentType<Props> = ( { wrapperProps } ) => (
		<div id="block-block-2" className={ ( wrapperProps as { className?: string } | undefined )?.className } />
	)
	const EffectsStyled = withEffectsStyles( BlockListBlock as never ) as unknown as ComponentType<Props>
	const attributes = { artisanpackEffects: { opacity: 50, customCss: 'background: url("a.png");' } }

	it( 'prints Custom CSS URLs by default', () => {
		const { unmount } = render( <EffectsStyled clientId="block-2" name="core/paragraph" attributes={ attributes } /> )

		expect( getCanvasScopedStyleElement( 'effects' )?.textContent ).toContain( 'url("a.png")' )
		unmount()
	} )

	it( 'drops Custom CSS that loads a URL when the site disallows URLs', () => {
		setCustomCssUrlAccess( false )

		const { unmount } = render( <EffectsStyled clientId="block-2" name="core/paragraph" attributes={ attributes } /> )

		expect( getCanvasScopedStyleElement( 'effects' )?.textContent ).toMatch( /^\.ve-fx-[0-9a-f]{8}\{opacity:0\.5\}$/ )
		unmount()
	} )
} )
