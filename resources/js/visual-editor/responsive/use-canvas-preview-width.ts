/**
 * Shared canvas-preview-width state for the two editor shells (#617).
 *
 * The post editor (`editor/editor-app.tsx`) and the site editor
 * (`site-editor/site-editor-app.tsx`) both react to the viewport
 * switcher by holding a `number | null` slot for the active preview
 * width and stamping it onto their canvas container as an inline
 * `max-width` (post editor) or a CSS custom property (site editor).
 * Before this hook existed the two shells carried byte-identical
 * copies of the same `useState + handleViewportChange` pair, plus the
 * subtle invariant that the switcher emits `previewWidthPx === 0` for
 * `base` (so `<= 0` already subsumes the `key === 'base'` guard).
 *
 * Both shells now call this hook and pass `handleViewportChange`
 * straight into `<TopBar onViewportChange={...} />`; the returned
 * `canvasPreviewWidthPx` is what they read for the inline style.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useCallback, useEffect, useRef, useState } from 'react'

type PreviewWidthListener = ( width: number | null ) => void

let publishedPreviewWidthPx: number | null = null
/**
 * Token of the hook instance that last published, so an unmounting
 * shell only clears the width it owns.
 */
let previewWidthOwner: object | null = null
const previewWidthListeners: Set<PreviewWidthListener> = new Set()

/**
 * The width the mounted shell's canvas is rendering at (#805): the
 * previewed device width, the measured canvas width at `base` for a
 * shell that opts into `measureBase`, or `null` when the canvas width
 * is left to `@media` queries (the post editor's iframe at `base`).
 * Lets block-level filters (e.g. the visibility canvas preview) follow
 * the canvas without reaching into shell state.
 */
export function getCanvasPreviewWidth(): number | null {
	return publishedPreviewWidthPx
}

export function subscribeCanvasPreviewWidth( listener: PreviewWidthListener ): () => void {
	previewWidthListeners.add( listener )

	return () => {
		previewWidthListeners.delete( listener )
	}
}

/**
 * Publishes a shell's preview width. Called by
 * {@link useCanvasPreviewWidth}; exported for tests.
 */
export function publishCanvasPreviewWidth( width: number | null ): void {
	if ( width === publishedPreviewWidthPx ) {
		return
	}

	publishedPreviewWidthPx = width
	previewWidthListeners.forEach( ( listener ) => listener( width ) )
}

/**
 * Silently resets the published width to `null` without notifying
 * listeners. For tests, so one test's width can't leak into the next.
 *
 * @since 1.13.0
 */
export function resetCanvasPreviewWidth(): void {
	publishedPreviewWidthPx = null
	previewWidthOwner       = null
}

export interface CanvasPreviewWidthApi {
	/**
	 * Active preview width in pixels, or `null` when the switcher is
	 * at `base` (no width constraint — canvas fills the editor).
	 */
	canvasPreviewWidthPx: number | null
	/**
	 * Direct pass-through for `<TopBar onViewportChange={...} />`. The
	 * switcher emits `0` for `base` and a positive int for named
	 * breakpoints; both collapse to `null` / a positive width here so
	 * downstream JSX only has to check for `null`.
	 */
	handleViewportChange: ( key: string, previewWidthPx: number ) => void
	/**
	 * Callback ref for the canvas container. Only measured when the
	 * hook runs with `measureBase`; harmless to attach otherwise.
	 */
	canvasRef: ( element: HTMLElement | null ) => void
}

export interface CanvasPreviewWidthOptions {
	/**
	 * Measure the canvas container at `base` and publish its width
	 * (#805). For shells that render blocks in the main document —
	 * there a `@media` query tests the browser window, not the canvas
	 * squeezed between the sidebars.
	 */
	measureBase?: boolean
}

export function useCanvasPreviewWidth( { measureBase = false }: CanvasPreviewWidthOptions = {} ): CanvasPreviewWidthApi {
	const [ canvasPreviewWidthPx, setCanvasPreviewWidthPx ] = useState<number | null>( null )

	const handleViewportChange = useCallback(
		// The switcher's contract is documented in
		// `ViewportSwitcher.tsx`: emits `previewWidthPx === 0` for
		// `base` and a positive int otherwise. `<= 0` covers both
		// the base case and any host that supplies a weird
		// registry entry.
		( _key: string, previewWidthPx: number ): void => {
			setCanvasPreviewWidthPx( previewWidthPx > 0 ? previewWidthPx : null )
		},
		[],
	)

	const [ canvasElement, canvasRef ] = useState<HTMLElement | null>( null )

	const ownerToken = useRef<object>( {} )

	// Clear the published width when the owning shell unmounts, so a
	// remount (HMR, switching site-editor sections) doesn't render gated
	// blocks against a stale width for a frame.
	useEffect( () => {
		const token = ownerToken.current

		return () => {
			if ( previewWidthOwner === token ) {
				publishCanvasPreviewWidth( null )
				previewWidthOwner = null
			}
		}
	}, [] )

	useEffect( () => {
		previewWidthOwner = ownerToken.current

		if (
			canvasPreviewWidthPx !== null
			|| ! measureBase
			|| canvasElement === null
			|| typeof ResizeObserver === 'undefined'
		) {
			publishCanvasPreviewWidth( canvasPreviewWidthPx )

			return
		}

		// A collapsed (zero-width) canvas has nothing meaningful to
		// measure, so fall back to `@media` queries.
		const publishMeasured = (): void => {
			const width = Math.round( canvasElement.getBoundingClientRect().width )
			publishCanvasPreviewWidth( width > 0 ? width : null )
		}

		publishMeasured()

		const observer = new ResizeObserver( publishMeasured )
		observer.observe( canvasElement )

		return () => observer.disconnect()
	}, [ canvasPreviewWidthPx, measureBase, canvasElement ] )

	return { canvasPreviewWidthPx, handleViewportChange, canvasRef }
}

/**
 * Site-editor-shaped JSX props for a canvas container div. Both the
 * `showEntityEditor` and `isLazySection` branches in
 * `site-editor-app.tsx` need the same `data-preview-width` +
 * CSS-custom-property style block; this helper computes it once so
 * they can't drift on the attribute name or the base sentinel.
 *
 * The returned object is intended to be spread onto the container
 * div. `data-preview-width="base"` is always stamped so CSS selectors
 * can match `[data-preview-width="base"]` deterministically instead
 * of testing for attribute absence.
 */
export interface SiteEditorCanvasPreviewProps {
	'data-preview-width': string
	style?: { [ key: string ]: string }
}

export function siteEditorCanvasPreviewProps( canvasPreviewWidthPx: number | null ): SiteEditorCanvasPreviewProps {
	if ( canvasPreviewWidthPx === null ) {
		return { 'data-preview-width': 'base' }
	}

	return {
		'data-preview-width': String( canvasPreviewWidthPx ),
		style: { '--ap-site-editor-canvas-preview-width': `${ canvasPreviewWidthPx }px` },
	}
}
