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

import { useCallback, useState } from 'react'

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
}

export function useCanvasPreviewWidth(): CanvasPreviewWidthApi {
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

	return { canvasPreviewWidthPx, handleViewportChange }
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
