/**
 * Vitest coverage for the ViewportSwitcher (#617).
 *
 * Since #820 the switcher offers `base` plus the desktop-first device
 * breakpoints (Tablet, Mobile) only.
 *
 * Focuses on the parts #617 changes:
 *   1. Rendering the registry's `label` (falling back to key).
 *   2. Emitting `previewWidthPx` (not `minWidthPx`) in the onChange
 *      payload.
 *   3. `base` selection reports `0` so host shells can distinguish
 *      "unconstrained" from a real device width.
 */

import { act, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { resetActiveBreakpoint } from '../active-breakpoint'
import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from '../registry'
import { ViewportSwitcher } from '../ViewportSwitcher'

const DEFAULT_REGISTRY = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

describe( 'ViewportSwitcher (#617)', () => {
	afterEach( () => {
		resetActiveBreakpoint()
	} )

	it( 'renders All sizes then Tablet then Mobile for the ship defaults (#820)', () => {
		render( <ViewportSwitcher registry={ DEFAULT_REGISTRY } /> )

		const names = screen.getAllByRole( 'button' ).map( ( button ) => button.textContent )

		expect( names ).toEqual( [ 'All sizes', 'Tablet', 'Mobile' ] )
		expect( screen.queryByRole( 'button', { name: 'Desktop' } ) ).toBeNull()
	} )

	it( 'describes device breakpoints with "and below" semantics (#820)', () => {
		render( <ViewportSwitcher registry={ DEFAULT_REGISTRY } /> )

		expect( screen.getByRole( 'button', { name: 'Mobile' } ) ).toHaveAttribute(
			'title',
			'Applies at 767px and below. Smaller devices inherit this value unless overridden.'
		)
		expect( screen.getByRole( 'button', { name: 'Tablet' } ).getAttribute( 'title' ) ).toContain( '1023px and below' )
	} )

	it( 'falls back to the key when a breakpoint has no registered label', () => {
		const registry = new BreakpointRegistry( [
			{ key: 'zoom', maxWidthPx: 900 },
		] )

		render( <ViewportSwitcher registry={ registry } /> )

		expect( screen.getByRole( 'button', { name: 'zoom' } ) ).toBeInTheDocument()
	} )

	it( 'lets the `labels` prop override registry labels', () => {
		render(
			<ViewportSwitcher
				registry={ DEFAULT_REGISTRY }
				labels={ { mobile: 'Phone', tablet: 'Slate', base: 'Full width' } }
			/>
		)

		expect( screen.getByRole( 'button', { name: 'Full width' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Phone' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Slate' } ) ).toBeInTheDocument()
	} )

	it( 'emits the breakpoint key + previewWidthPx when a preset is selected', () => {
		const onChange = vi.fn()

		render(
			<ViewportSwitcher registry={ DEFAULT_REGISTRY } onChange={ onChange } />
		)

		act( () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'Mobile' } ) )
		} )
		expect( onChange ).toHaveBeenLastCalledWith( 'mobile', 375 )

		act( () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'Tablet' } ) )
		} )
		expect( onChange ).toHaveBeenLastCalledWith( 'tablet', 768 )
	} )

	it( 'reports previewWidthPx=0 for the base selection so hosts can drop their inline width', () => {
		const onChange = vi.fn()

		render(
			<ViewportSwitcher registry={ DEFAULT_REGISTRY } onChange={ onChange } />
		)

		act( () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'All sizes' } ) )
		} )
		expect( onChange ).toHaveBeenLastCalledWith( 'base', 0 )
	} )

	it( 'reflects the active selection via aria-pressed', () => {
		render( <ViewportSwitcher registry={ DEFAULT_REGISTRY } /> )

		const mobile = screen.getByRole( 'button', { name: 'Mobile' } )
		act( () => {
			fireEvent.click( mobile )
		} )
		expect( mobile ).toHaveAttribute( 'aria-pressed', 'true' )

		const tablet = screen.getByRole( 'button', { name: 'Tablet' } )
		expect( tablet ).toHaveAttribute( 'aria-pressed', 'false' )
	} )
} )
