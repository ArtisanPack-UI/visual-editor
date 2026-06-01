import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it } from 'vitest'

import { resetStateStores } from '../active-state'
import { DEFAULT_STATES, StateRegistry } from '../registry'
import { StateSwitcher } from '../StateSwitcher'

beforeEach( () => {
	resetStateStores()
} )

function renderSwitcher(
	props: Partial<React.ComponentProps<typeof StateSwitcher>> = {},
): HTMLElement {
	const registry = new StateRegistry( DEFAULT_STATES )
	const { container } = render(
		<StateSwitcher
			registry={ registry }
			supportsStates
			{ ...props }
		/>,
	)
	return container
}

function chipFor( label: string ): HTMLButtonElement {
	const chips = screen.getAllByRole( 'button' ) as HTMLButtonElement[]

	const match = chips.find( ( chip ) => {
		const labelSpan = chip.querySelector( 'span:not([aria-hidden="true"])' )
		return labelSpan?.textContent === label
	} )

	if ( ! match ) {
		throw new Error( `No chip found with label "${ label }"` )
	}

	return match
}

describe( 'StateSwitcher', () => {
	it( 'renders the unsupported message when the block opts out', () => {
		render(
			<StateSwitcher
				registry={ new StateRegistry( DEFAULT_STATES ) }
				supportsStates={ false }
			/>,
		)

		expect( screen.getByRole( 'status' ) ).toBeInTheDocument()
	} )

	it( 'renders one chip per registered state when supportsStates is true', () => {
		renderSwitcher()

		const chipButtons = screen.getAllByRole( 'button' )
		// idle, hover, focus, focus-visible, active, disabled.
		expect( chipButtons ).toHaveLength( 6 )
	} )

	it( 'filters chips by allowedStates while always keeping idle visible', () => {
		renderSwitcher( { allowedStates: [ 'hover' ] } )

		// Idle is always rendered; only the allowed states join it.
		expect( chipFor( 'Idle' ) ).toBeInTheDocument()
		expect( chipFor( 'Hover' ) ).toBeInTheDocument()
		expect( () => chipFor( 'Focus' ) ).toThrow()
	} )

	it( 'marks a chip as has-override when its state has a stored override', () => {
		renderSwitcher( {
			attributes: {
				states: {
					backgroundColor: { hover: 'error' },
				},
			},
		} )

		expect( chipFor( 'Hover' ).getAttribute( 'data-has-override' ) ).toBe( 'true' )
		expect( chipFor( 'Focus' ).getAttribute( 'data-has-override' ) ).toBe( 'false' )
	} )

	it( 'never marks the idle chip as has-override', () => {
		renderSwitcher( {
			attributes: {
				states: {
					backgroundColor: { hover: 'error', idle: 'warning' },
				},
			},
		} )

		expect( chipFor( 'Idle' ).getAttribute( 'data-has-override' ) ).toBe( 'false' )
	} )

	it( 'ignores housekeeping keys (e.g. _scopeId) when computing has-override', () => {
		renderSwitcher( {
			attributes: {
				states: {
					_scopeId: 'abc123',
				},
			},
		} )

		for ( const label of [ 'Hover', 'Focus', 'Active', 'Disabled' ] ) {
			expect( chipFor( label ).getAttribute( 'data-has-override' ) ).toBe( 'false' )
		}
	} )
} )
