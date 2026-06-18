/**
 * Photo Grid wrapper serializer (#594).
 *
 * Mirrors `PhotoGridSupport::wrapper()` on the Blade renderer. Given a
 * block's attribute bag, returns the CSS class + inline style props
 * the editor save / canvas-side block wrapper needs to switch the
 * container into Photo Grid mode.
 *
 * The wrapper emits one class (`has-photo-grid`) and three CSS custom
 * properties (`--ap-photo-grid-aspect`, `--ap-photo-grid-fit`,
 * `--ap-photo-grid-position`) that the matching stylesheet
 * (`photo-grid.css`) targets to size image-bearing descendants.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import type { CSSProperties } from 'react'

import type { PhotoGridAttribute } from './types'

export interface PhotoGridWrapperProps {
	className?: string
	style?: CSSProperties
}

interface AttributeBag {
	photoGrid?: PhotoGridAttribute | null
}

/**
 * Sanitise an aspect ratio token. Accepts the dropdown presets, a
 * custom `W/H` string (digits, slash, optional decimals), or `null`
 * for "inherit container". Anything else collapses to `null` so the
 * wrapper falls back to the descendant's natural aspect.
 */
export function normaliseAspectRatio(value: unknown): string | null {
	if (value === null || value === undefined || value === '') {
		return null
	}
	if (typeof value !== 'string') {
		return null
	}
	const trimmed = value.trim()
	if (trimmed === '' || trimmed === 'auto' || trimmed === 'inherit') {
		return null
	}
	// Accept `W/H` with positive numeric W and H. Reject negatives,
	// zero, and anything that isn't a pair of numbers separated by a
	// single slash.
	if (!/^\d+(\.\d+)?\/\d+(\.\d+)?$/.test(trimmed)) {
		return null
	}
	const [w, h] = trimmed.split('/').map(Number)
	if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) {
		return null
	}
	return trimmed
}

function normaliseObjectFit(value: unknown): 'cover' | 'contain' {
	return value === 'contain' ? 'contain' : 'cover'
}

function normaliseObjectPosition(value: unknown): string {
	if (typeof value !== 'string') {
		return '50% 50%'
	}
	const trimmed = value.trim()
	if (trimmed === '') {
		return '50% 50%'
	}
	// Reject CSS declaration / rule delimiters so a tampered
	// `objectPosition` cannot break out of the `--ap-photo-grid-
	// position` declaration and inject sibling rules. Mirrors
	// `PhotoGridSupport::normaliseObjectPosition()` on the PHP side.
	if (/[;{}<>]/.test(trimmed)) {
		return '50% 50%'
	}
	return trimmed
}

/**
 * Resolve the wrapper props for a block. Returns an empty object
 * when the setting is disabled or missing — callers should spread the
 * result into their existing wrapper props.
 */
export function getPhotoGridWrapperProps(
	attributes: AttributeBag | null | undefined,
): PhotoGridWrapperProps {
	const photoGrid = attributes?.photoGrid ?? null
	if (!photoGrid || photoGrid.enabled !== true) {
		return {}
	}

	const aspect = normaliseAspectRatio(photoGrid.aspectRatio)
	const fit = normaliseObjectFit(photoGrid.objectFit)
	const position = normaliseObjectPosition(photoGrid.objectPosition)

	const style: Record<string, string> = {
		'--ap-photo-grid-fit': fit,
		'--ap-photo-grid-position': position,
	}
	if (aspect !== null) {
		style['--ap-photo-grid-aspect'] = aspect
	}

	return {
		className: 'has-photo-grid',
		style: style as CSSProperties,
	}
}
