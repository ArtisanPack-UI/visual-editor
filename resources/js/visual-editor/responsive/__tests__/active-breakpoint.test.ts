import { afterEach, describe, expect, it, vi } from 'vitest'

import {
	getActiveBreakpoint,
	resetActiveBreakpoint,
	setActiveBreakpoint,
	subscribeActiveBreakpoint,
} from '../active-breakpoint'

afterEach( () => {
	resetActiveBreakpoint()
} )

describe( 'active-breakpoint store', () => {
	it( 'starts at base', () => {
		expect( getActiveBreakpoint() ).toBe( 'base' )
	} )

	it( 'notifies subscribers when the breakpoint changes', () => {
		const listener = vi.fn()
		const unsubscribe = subscribeActiveBreakpoint( listener )

		setActiveBreakpoint( 'md' )

		expect( listener ).toHaveBeenCalledWith( 'md' )
		expect( getActiveBreakpoint() ).toBe( 'md' )

		unsubscribe()
	} )

	it( 'does not notify when the breakpoint is set to the current value', () => {
		setActiveBreakpoint( 'md' )

		const listener = vi.fn()
		const unsubscribe = subscribeActiveBreakpoint( listener )

		setActiveBreakpoint( 'md' )

		expect( listener ).not.toHaveBeenCalled()

		unsubscribe()
	} )
} )
