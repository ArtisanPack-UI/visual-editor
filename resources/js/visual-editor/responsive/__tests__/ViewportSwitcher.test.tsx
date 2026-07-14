/**
 * Vitest coverage for the ViewportSwitcher (#617).
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

	it( 'renders Mobile/Tablet/Desktop labels for the ship defaults', () => {
		render( <ViewportSwitcher registry={ DEFAULT_REGISTRY } /> )

		expect( screen.getByRole( 'button', { name: 'All sizes' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Mobile' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Tablet' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Desktop' } ) ).toBeInTheDocument()
	} )

	it( 'falls back to the key when a breakpoint has no registered label', () => {
		const registry = new BreakpointRegistry( [
			{ key: 'zoom', minWidthPx: 900 },
		] )

		render( <ViewportSwitcher registry={ registry } /> )

		expect( screen.getByRole( 'button', { name: 'zoom' } ) ).toBeInTheDocument()
	} )

	it( 'lets the `labels` prop override registry labels', () => {
		render(
			<ViewportSwitcher
				registry={ DEFAULT_REGISTRY }
				labels={ { sm: 'Phone', md: 'Slate', base: 'Full width' } }
			/>
		)

		expect( screen.getByRole( 'button', { name: 'Full width' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Phone' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Slate' } ) ).toBeInTheDocument()
		expect( screen.getByRole( 'button', { name: 'Desktop' } ) ).toBeInTheDocument()
	} )

	it( 'emits the breakpoint key + previewWidthPx when a preset is selected', () => {
		const onChange = vi.fn()

		render(
			<ViewportSwitcher registry={ DEFAULT_REGISTRY } onChange={ onChange } />
		)

		act( () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'Mobile' } ) )
		} )
		expect( onChange ).toHaveBeenLastCalledWith( 'sm', 375 )

		act( () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'Tablet' } ) )
		} )
		expect( onChange ).toHaveBeenLastCalledWith( 'md', 768 )

		act( () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'Desktop' } ) )
		} )
		expect( onChange ).toHaveBeenLastCalledWith( 'lg', 1440 )
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

		const desktop = screen.getByRole( 'button', { name: 'Desktop' } )
		expect( desktop ).toHaveAttribute( 'aria-pressed', 'false' )
	} )
} )
