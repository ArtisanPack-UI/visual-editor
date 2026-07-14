/**
 * CSS emission for position attributes (#643).
 *
 * Takes a resolved payload (`resolvePosition` output) and produces the
 * `<style>` string appended alongside a block's wrapper. Base layer
 * emits an unscoped rule; per-breakpoint overrides emit inside
 * `@media (min-width:<n>px)` blocks matching the responsive breakpoint
 * registry.
 *
 * Contracts (per #643 acceptance criteria):
 *
 *  - `position: static` emits NOTHING — offsets and z-index are
 *    preserved in attributes but silent while static.
 *  - `unit: 'auto'` renders as `top: auto` etc.
 *  - Legacy Gutenberg sticky is a no-op at the emitter level: the
 *    resolver already widens the bare string, and static-only base
 *    layers with no other data resolve to null upstream.
 *  - Breakpoints inherit from smaller breakpoints; the emitter
 *    consumes already-merged per-breakpoint layers so the media query
 *    body carries the fully-resolved position/offset/zIndex set.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import type { BreakpointRegistry } from '../responsive/registry'
import { BASE_KEY } from '../responsive/types'
import { mergeLayers } from './resolver'
import type {
	OffsetValue,
	ResolvedPosition,
	ResolvedPositionLayer,
} from './types'

function formatOffset( offset: OffsetValue ): string {
	if ( 'auto' === offset.unit ) {
		return 'auto'
	}

	return `${ offset.value }${ offset.unit }`
}

/**
 * Emit the declaration list for a single layer. Callers wrap this in
 * whatever selector / media query they need. Returns an empty string
 * when the layer effectively contributes nothing (e.g. `static` with
 * no z-index override).
 */
export function layerDeclarations( layer: ResolvedPositionLayer | null ): string {
	if ( ! layer ) {
		return ''
	}

	const parts: string[] = []

	if ( null !== layer.value && 'static' !== layer.value ) {
		// Every declaration ships `!important`. Gutenberg's editor
		// canvas emits an internal `.block-editor-block-list__layout
		// .block-editor-block-list__block { position: relative }` rule
		// (0,2,0 specificity) that outranks a single `.ve-pos-xyz`
		// class selector (0,1,0), so an unmarked `position: sticky`
		// silently loses at author time. Users who pick a value on the
		// panel explicitly WANT it applied — `!important` matches that
		// intent and is consistent with how core WordPress overrides
		// several of its own layout defaults.
		parts.push( `position:${ layer.value } !important` )

		if ( null !== layer.offsets.top ) {
			parts.push( `top:${ formatOffset( layer.offsets.top ) } !important` )
		}
		if ( null !== layer.offsets.right ) {
			parts.push( `right:${ formatOffset( layer.offsets.right ) } !important` )
		}
		if ( null !== layer.offsets.bottom ) {
			parts.push( `bottom:${ formatOffset( layer.offsets.bottom ) } !important` )
		}
		if ( null !== layer.offsets.left ) {
			parts.push( `left:${ formatOffset( layer.offsets.left ) } !important` )
		}

		if ( null !== layer.zIndex ) {
			parts.push( `z-index:${ layer.zIndex } !important` )
		}
	}

	return parts.join( ';' )
}

/**
 * Emit scoped CSS for a block. Uses media queries for breakpoint
 * overrides. Returns an empty string when nothing meaningful renders.
 *
 * The per-breakpoint layers passed in must already be merged with the
 * base layer — the emitter emits the full declaration list for each
 * breakpoint so the media query body carries a self-contained set of
 * declarations (no cascade tricks required).
 */
export function emitPositionCss(
	scope: string,
	payload: ResolvedPosition | null | undefined,
	breakpoints: BreakpointRegistry,
	mergedBreakpointLayers: Record<string, ResolvedPositionLayer>,
): string {
	const trimmedScope = scope.trim()

	if ( '' === trimmedScope || ! payload ) {
		return ''
	}

	const rules: string[] = []

	const baseDecls = layerDeclarations( payload.base )
	if ( '' !== baseDecls ) {
		rules.push( `${ trimmedScope }{${ baseDecls }}` )
	}

	// Emit breakpoints in ascending min-width order so the cascade is
	// natural — a later, larger breakpoint wins over an earlier one at
	// the same specificity when both match.
	const ordered = breakpoints
		.all()
		.slice()
		.sort( ( a, b ) => a.minWidthPx - b.minWidthPx )

	for ( const bp of ordered ) {
		const key = bp.key
		if ( BASE_KEY === key ) {
			continue
		}

		const layer = mergedBreakpointLayers[ key ]
		if ( ! layer ) {
			continue
		}

		const decls = layerDeclarations( layer )
		if ( '' === decls ) {
			continue
		}

		rules.push(
			`@media (min-width:${ bp.minWidthPx }px){${ trimmedScope }{${ decls }}}`,
		)
	}

	return rules.join( '' )
}

/**
 * Convenience: given a resolved payload, produce the merged
 * per-breakpoint layer map the emitter consumes. Walks each defined
 * breakpoint override and folds it on top of every smaller layer.
 * Kept separate from the emitter so the inspector can reuse it when
 * previewing a specific breakpoint.
 */
export function mergedBreakpointLayers(
	payload: ResolvedPosition,
	breakpoints: BreakpointRegistry,
): Record<string, ResolvedPositionLayer> {
	const out: Record<string, ResolvedPositionLayer> = {}

	const ordered = breakpoints.keysWithBase()
	let running: ResolvedPositionLayer | null = payload.base

	for ( let i = 1; i < ordered.length; i++ ) {
		const key     = ordered[ i ]
		const overlay = payload.breakpoints[ key ]

		if ( overlay ) {
			running = mergeLayers( running, overlay )
			if ( null !== running ) {
				out[ key ] = running
			}
		}
	}

	return out
}
