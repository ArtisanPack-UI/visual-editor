/**
 * Tests for the canvas preview-width hook and its published store
 * (#617 · #805).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, renderHook } from '@testing-library/react';

import {
	getCanvasPreviewWidth,
	publishCanvasPreviewWidth,
	subscribeCanvasPreviewWidth,
	useCanvasPreviewWidth,
} from '../use-canvas-preview-width';

afterEach( () => {
	publishCanvasPreviewWidth( null );
} );

describe( 'useCanvasPreviewWidth', () => {
	it( 'collapses base to null and publishes the active width', () => {
		const { result } = renderHook( () => useCanvasPreviewWidth() );

		act( () => result.current.handleViewportChange( 'tablet', 768 ) );

		expect( result.current.canvasPreviewWidthPx ).toBe( 768 );
		expect( getCanvasPreviewWidth() ).toBe( 768 );

		act( () => result.current.handleViewportChange( 'base', 0 ) );

		expect( result.current.canvasPreviewWidthPx ).toBeNull();
		expect( getCanvasPreviewWidth() ).toBeNull();
	} );

	it( 'publishes its initial base state on mount', () => {
		publishCanvasPreviewWidth( 375 );

		renderHook( () => useCanvasPreviewWidth() );

		expect( getCanvasPreviewWidth() ).toBeNull();
	} );
} );

describe( 'useCanvasPreviewWidth with measureBase', () => {
	let resizeCallback: ( () => void ) | null = null
	const disconnect = vi.fn()

	beforeEach( () => {
		resizeCallback = null
		disconnect.mockClear()
		vi.stubGlobal( 'ResizeObserver', class {
			constructor( callback: () => void ) {
				resizeCallback = callback
			}

			observe(): void {}

			disconnect(): void {
				disconnect()
			}
		} )
	} )

	afterEach( () => {
		vi.unstubAllGlobals()
	} )

	function canvasOfWidth( width: { current: number } ): HTMLElement {
		const element = document.createElement( 'div' )
		element.getBoundingClientRect = () => ( { width: width.current } ) as DOMRect

		return element
	}

	it( 'publishes the measured canvas width at base and follows resizes', () => {
		const width      = { current: 700.4 }
		const { result } = renderHook( () => useCanvasPreviewWidth( { measureBase: true } ) )

		act( () => result.current.canvasRef( canvasOfWidth( width ) ) )
		expect( getCanvasPreviewWidth() ).toBe( 700 )

		width.current = 1180
		act( () => resizeCallback?.() )
		expect( getCanvasPreviewWidth() ).toBe( 1180 )
	} )

	it( 'prefers the previewed device width and stops observing', () => {
		const { result } = renderHook( () => useCanvasPreviewWidth( { measureBase: true } ) )

		act( () => result.current.canvasRef( canvasOfWidth( { current: 700 } ) ) )
		act( () => result.current.handleViewportChange( 'mobile', 375 ) )

		expect( getCanvasPreviewWidth() ).toBe( 375 )
		expect( disconnect ).toHaveBeenCalled()
	} )

	it( 'falls back to null for a collapsed canvas or a detached ref', () => {
		const width      = { current: 0 }
		const { result } = renderHook( () => useCanvasPreviewWidth( { measureBase: true } ) )

		act( () => result.current.canvasRef( canvasOfWidth( width ) ) )
		expect( getCanvasPreviewWidth() ).toBeNull()

		width.current = 900
		act( () => resizeCallback?.() )
		act( () => result.current.canvasRef( null ) )
		expect( getCanvasPreviewWidth() ).toBeNull()
	} )

	it( 'does not measure without the option', () => {
		const { result } = renderHook( () => useCanvasPreviewWidth() )

		act( () => result.current.canvasRef( canvasOfWidth( { current: 700 } ) ) )

		expect( getCanvasPreviewWidth() ).toBeNull()
		expect( resizeCallback ).toBeNull()
	} )
} )

describe( 'canvas preview-width store', () => {
	it( 'notifies subscribers only on change', () => {
		const listener    = vi.fn();
		const unsubscribe = subscribeCanvasPreviewWidth( listener );

		publishCanvasPreviewWidth( 375 );
		publishCanvasPreviewWidth( 375 );
		unsubscribe();
		publishCanvasPreviewWidth( 768 );

		expect( listener ).toHaveBeenCalledTimes( 1 );
		expect( listener ).toHaveBeenCalledWith( 375 );
	} );
} );
