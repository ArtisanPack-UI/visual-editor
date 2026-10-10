/**
 * Tests for the gradient-border canvas HOC's use of the shared canvas
 * style host (FE-6 follow-on): the scoped CSS must land in a single
 * `<style data-ap-canvas-styles="gradient-border">` in the document
 * head, never as a `<style>` sibling of the block.
 */

import { describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType: ( name: string ) => (
		'core/group' === name
			? { supports: { border: { gradient: true } } }
			: { supports: {} }
	),
} ) )

import { getCanvasScopedStyleElement } from '../../support/canvas-scoped-styles'
import {
	GRADIENT_BORDER_STYLE_CHANNEL,
	withGradientBorderStyles,
} from '../with-gradient-border-styles'

const BlockListBlock = ( props: { wrapperProps?: { className?: string } } ) => (
	<div data-testid="block" className={ props.wrapperProps?.className ?? '' } />
)
const Wrapped = withGradientBorderStyles( BlockListBlock )

function gradientAttributes( scopeId: string ) {
	return {
		style: {
			border: {
				gradient:         'linear-gradient(135deg, #ff0000, #0000ff)',
				width:            '2px',
				_gradientScopeId: scopeId,
			},
		},
	}
}

function hostStyle(): HTMLStyleElement | null {
	return getCanvasScopedStyleElement( GRADIENT_BORDER_STYLE_CHANNEL )
}

describe( 'withGradientBorderStyles canvas style host', () => {
	it( 'renders no <style> sibling and publishes into the head host', () => {
		const { container, getAllByTestId, unmount } = render(
			<div data-testid="list">
				<Wrapped
					name="core/group"
					clientId="first"
					attributes={ gradientAttributes( 'gbfirst01' ) }
					setAttributes={ () => undefined }
				/>
				<Wrapped
					name="core/group"
					clientId="second"
					attributes={ gradientAttributes( 'gbsecond2' ) }
					setAttributes={ () => undefined }
				/>
			</div>,
		)

		const [ first, second ] = getAllByTestId( 'block' )

		expect( container.querySelector( 'style' ) ).toBeNull()
		expect( first?.previousElementSibling ).toBeNull()
		expect( second?.previousElementSibling ).toBe( first )
		expect( first?.className ).toContain( 've-gb-gbfirst01' )

		expect( hostStyle()?.parentElement ).toBe( document.head )
		expect(
			document.querySelectorAll( `style[data-ap-canvas-styles="${ GRADIENT_BORDER_STYLE_CHANNEL }"]` ),
		).toHaveLength( 1 )
		expect( hostStyle()?.textContent ).toContain( '.ve-gb-gbfirst01' )
		expect( hostStyle()?.textContent ).toContain( '.ve-gb-gbsecond2' )

		unmount()

		expect( hostStyle() ).toBeNull()
	} )

	it( 'publishes nothing for blocks without gradient support', () => {
		const { container } = render(
			<Wrapped
				name="core/paragraph"
				clientId="plain"
				attributes={ gradientAttributes( 'gbplain01' ) }
			/>,
		)

		expect( container.querySelector( 'style' ) ).toBeNull()
		expect( hostStyle() ).toBeNull()
	} )
} )
