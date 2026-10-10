/**
 * Tests for the canvas preview-width hook and its published store
 * (#617 · #805).
 */

import { afterEach, describe, expect, it, vi } from 'vitest';
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
