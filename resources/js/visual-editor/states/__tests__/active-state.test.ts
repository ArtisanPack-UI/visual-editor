import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
	getActiveBlock,
	getActiveState,
	getPreviewState,
	resetStateStores,
	setActiveBlock,
	setActiveState,
	setPreviewState,
	subscribeActiveState,
	subscribePreviewState,
} from '../active-state'

beforeEach( () => {
	resetStateStores()
} )

describe( 'active-state store', () => {
	it( 'starts in idle and ignores no-op writes', () => {
		expect( getActiveState() ).toBe( 'idle' )

		const listener = vi.fn()
		subscribeActiveState( listener )

		setActiveState( 'idle' )
		expect( listener ).not.toHaveBeenCalled()

		setActiveState( 'hover' )
		expect( listener ).toHaveBeenCalledWith( 'hover' )
		expect( getActiveState() ).toBe( 'hover' )
	} )

	it( 'allows unsubscribing', () => {
		const listener   = vi.fn()
		const unsubscribe = subscribeActiveState( listener )

		setActiveState( 'hover' )
		unsubscribe()
		setActiveState( 'focus' )

		expect( listener ).toHaveBeenCalledTimes( 1 )
	} )
} )

describe( 'preview-state store', () => {
	it( 'normalizes idle and null to a single cleared state', () => {
		const listener = vi.fn()
		subscribePreviewState( listener )

		setPreviewState( 'idle' )
		expect( getPreviewState() ).toBeNull()
		expect( listener ).not.toHaveBeenCalled()

		setPreviewState( 'hover' )
		expect( getPreviewState() ).toBe( 'hover' )
		expect( listener ).toHaveBeenCalledWith( 'hover' )

		setPreviewState( null )
		expect( getPreviewState() ).toBeNull()
	} )
} )

describe( 'block scope', () => {
	it( 'resets both active and preview state when the selected block changes', () => {
		setActiveState( 'hover' )
		setPreviewState( 'hover' )

		setActiveBlock( 'block-1' )
		expect( getActiveBlock() ).toBe( 'block-1' )
		expect( getActiveState() ).toBe( 'idle' )
		expect( getPreviewState() ).toBeNull()

		setActiveState( 'focus' )
		setActiveBlock( 'block-2' )
		expect( getActiveState() ).toBe( 'idle' )
	} )

	it( 'does nothing when re-selecting the same block', () => {
		setActiveBlock( 'block-1' )
		setActiveState( 'hover' )
		setActiveBlock( 'block-1' )
		expect( getActiveState() ).toBe( 'hover' )
	} )
} )
