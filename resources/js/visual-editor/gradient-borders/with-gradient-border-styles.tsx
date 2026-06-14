/**
 * Gradient border CSS emission for the editor canvas + saved markup
 * (#490).
 *
 * Mirrors `withStateStyles` (#488) for the gradient border feature.
 * Without this HOC, the inspector picker writes the attribute but
 * nothing paints — the server renderer's `compileGradientBorder()`
 * only fires at render time, not during editor preview.
 *
 * Three filter hooks:
 *
 *  1. **`editor.BlockListBlock`** — runs in the canvas. Computes the
 *     scope class from a stable per-block `_gradientScopeId` and:
 *     - Injects a `<style>` element with the emitted CSS
 *     - Adds the scope class to the wrapper props so the rules match
 *
 *  2. **`blocks.getSaveContent.extraProps`** — stamps the scope class
 *     on the saved block root.
 *
 *  3. **`blocks.getSaveElement`** — wraps the saved tree with a
 *     sibling `<style>` element so published markup carries the same
 *     rules without requiring the Blade renderer.
 *
 * The `_gradientScopeId` is minted once per block the first time it
 * authors any gradient configuration and persisted on
 * `attributes.style.border._gradientScopeId`. Storing it on the
 * attributes (rather than regenerating each render) keeps the editor,
 * saved markup, and Blade-rendered output in lockstep.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { getBlockType } from '@wordpress/blocks'
import { createHigherOrderComponent } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { Fragment, createElement, useEffect, useMemo, useRef } from 'react'
import type { ComponentType, ReactNode } from 'react'

import { TAILWIND_V4_DEFAULTS, BreakpointRegistry } from '../responsive/registry'
import { DEFAULT_STATES, StateRegistry } from '../states/registry'
import { emitGradientBorderCss } from './emitter'
import { resolveGradientBorder } from './resolver'
import type { GradientBorderAttributes } from './types'

const BLOCK_FILTER_HOOK = 'editor.BlockListBlock'
const SAVE_PROPS_HOOK   = 'blocks.getSaveContent.extraProps'
const SAVE_ELEMENT_HOOK = 'blocks.getSaveElement'

const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/gradient-border-styles'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.gradient-border-styles.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockListBlockProps {
	name: string
	clientId: string
	attributes: GradientBorderAttributes & Record<string, unknown>
	setAttributes?: ( updates: Record<string, unknown> ) => void
	wrapperProps?: Record<string, unknown>
	[key: string]: unknown
}

interface BlockSettingsLike {
	supports?: Record<string, unknown>
	[key: string]: unknown
}

interface BorderSubtreeWithScope {
	gradient?: string | null
	width?: string | null
	radius?: string | Record<string, unknown> | null
	_gradientScopeId?: string
	[key: string]: unknown
}

const SCOPE_ID_KEY = '_gradientScopeId'

// Whitelist of characters allowed in a persisted scope id. The id is
// interpolated directly into the wrapper's class attribute AND into
// emitted CSS rule selectors (`.ve-gb-<id>::before { ... }`), so a
// crafted block tree carrying selector control characters in
// `_gradientScopeId` could escape the scope and inject arbitrary CSS.
// The mint function below produces base-36 (`[a-z0-9]{9}`) ids that
// trivially pass; anything that fails is treated as hostile /
// corrupted and re-minted. Mirrors the PHP-side guard in
// `BlockSupports::resolveGradientBorderScopeClass`.
const SCOPE_ID_PATTERN = /^[a-z0-9][a-z0-9_-]{0,63}$/i

// Module-level registries — same instances used by the PHP server-side
// emitter via `fromLayers()` defaults. Eventually these should be
// hydrated from the editor's themed settings so custom states/
// breakpoints declared in `theme.json` participate; the v1 surface
// uses the static defaults so the editor preview matches the renderer
// when no custom registries are configured.
const states      = new StateRegistry( DEFAULT_STATES )
const breakpoints = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

function blockSupportsGradientBorder( name: string ): boolean {
	const blockType = getBlockType( name )
	if ( ! blockType ) {
		return false
	}

	const support = ( blockType.supports as Record<string, unknown> | undefined )?.[ '__experimentalBorder' ]
		?? ( blockType.supports as Record<string, unknown> | undefined )?.[ 'border' ]

	if ( ! support || 'object' !== typeof support ) {
		return false
	}

	return true === ( support as Record<string, unknown> ).gradient
}

function scopeIdToClass( scopeId: string ): string {
	return `ve-gb-${ scopeId }`
}

function readScopeId(
	attributes: GradientBorderAttributes | undefined,
): string | null {
	const border = attributes?.style?.border as BorderSubtreeWithScope | undefined | null
	const raw    = border?.[ SCOPE_ID_KEY ]

	if ( 'string' !== typeof raw ) {
		return null
	}

	const trimmed = raw.trim()
	return SCOPE_ID_PATTERN.test( trimmed ) ? trimmed : null
}

function hasAuthoredAnyGradient(
	attributes: GradientBorderAttributes | undefined,
): boolean {
	return null !== resolveGradientBorder( attributes )
}

// `Math.random()` is fine here — collisions only matter within a
// single page render and the keyspace (~10^14) leaves the
// birthday-paradox floor well above any realistic block count. Same
// rationale as `mintScopeId()` in `with-state-styles.tsx`.
function mintScopeId(): string {
	return Math.random().toString( 36 ).slice( 2, 11 )
}

// Tracks which clientId currently owns each scope id so duplicated
// blocks (which inherit the parent's `_gradientScopeId` via attribute
// copy) detect the collision and re-mint. Mirrors `scopeIdOwners` in
// `with-state-styles.tsx`.
const scopeIdOwners = new Map<string, string>()

function claimScopeId( scopeId: string, clientId: string ): boolean {
	const owner = scopeIdOwners.get( scopeId )
	if ( ! owner ) {
		scopeIdOwners.set( scopeId, clientId )
		return true
	}

	return owner === clientId
}

function releaseScopeId( scopeId: string, clientId: string ): void {
	if ( scopeIdOwners.get( scopeId ) === clientId ) {
		scopeIdOwners.delete( scopeId )
	}
}

function writeScopeId(
	attributes: GradientBorderAttributes,
	setAttributes: ( updates: Record<string, unknown> ) => void,
	nextId: string,
): void {
	const style = ( attributes.style && 'object' === typeof attributes.style )
		? ( attributes.style as Record<string, unknown> )
		: {}
	const border = ( style.border && 'object' === typeof style.border )
		? ( style.border as Record<string, unknown> )
		: {}

	setAttributes( {
		style: {
			...style,
			border: {
				...border,
				[ SCOPE_ID_KEY ]: nextId,
			},
		},
	} )
}

export const withGradientBorderStyles = createHigherOrderComponent(
	( BlockListBlock: ComponentType<BlockListBlockProps> ) => {
		function GradientBorderStyledBlock( props: BlockListBlockProps ): JSX.Element {
			const { name, clientId, attributes, setAttributes, wrapperProps } = props

			const supported = blockSupportsGradientBorder( name )

			const scopeId = useMemo(
				() => readScopeId( attributes ),
				[ attributes ],
			)

			const hasOverrides = useMemo(
				() => hasAuthoredAnyGradient( attributes ),
				[ attributes ],
			)

			const persistedRef = useRef( scopeId )
			useEffect( () => {
				persistedRef.current = scopeId
			}, [ scopeId ] )

			useEffect( () => {
				if ( ! supported || ! setAttributes ) {
					return
				}

				const needsMint    = ! scopeId && hasOverrides
				const hasCollision = scopeId !== null && ! claimScopeId( scopeId, clientId )

				if ( ! needsMint && ! hasCollision ) {
					return
				}

				const nextId = mintScopeId()
				claimScopeId( nextId, clientId )
				writeScopeId( attributes, setAttributes, nextId )
			}, [ supported, scopeId, hasOverrides, attributes, setAttributes, clientId ] )

			useEffect( () => {
				if ( ! scopeId ) {
					return
				}

				return () => releaseScopeId( scopeId, clientId )
			}, [ scopeId, clientId ] )

			const effectiveScopeId = scopeId ?? persistedRef.current

			const css = useMemo( () => {
				if ( ! supported || ! effectiveScopeId ) {
					return ''
				}

				const payload = resolveGradientBorder( attributes )
				if ( ! payload ) {
					return ''
				}

				return emitGradientBorderCss(
					`.${ scopeIdToClass( effectiveScopeId ) }`,
					payload,
					states,
					breakpoints,
				)
			}, [ supported, effectiveScopeId, attributes ] )

			const effectiveWrapperProps: Record<string, unknown> = useMemo( () => {
				if ( ! supported || ! effectiveScopeId ) {
					return wrapperProps ?? {}
				}

				const existingClass = ( wrapperProps?.className as string | undefined ) ?? ''
				const scopeClass    = scopeIdToClass( effectiveScopeId )

				if ( existingClass.split( ' ' ).includes( scopeClass ) ) {
					return wrapperProps ?? {}
				}

				return {
					...( wrapperProps ?? {} ),
					className: existingClass ? `${ existingClass } ${ scopeClass }` : scopeClass,
				}
			}, [ supported, wrapperProps, effectiveScopeId ] )

			if ( ! supported ) {
				return <BlockListBlock { ...props } />
			}

			const wrapped = (
				<BlockListBlock
					{ ...props }
					wrapperProps={ effectiveWrapperProps }
				/>
			)

			if ( '' === css ) {
				return wrapped
			}

			return (
				<Fragment>
					<style data-ap-gradient-border-scope={ effectiveScopeId }>{ css }</style>
					{ wrapped }
				</Fragment>
			)
		}

		GradientBorderStyledBlock.displayName = 'GradientBorderStyledBlock'

		return GradientBorderStyledBlock
	},
	'withGradientBorderStyles',
)

function blockSupportsConfigOnSettings(
	blockType: BlockSettingsLike | undefined,
): boolean {
	const supports = blockType?.supports as Record<string, unknown> | undefined
	const border   = ( supports?.[ '__experimentalBorder' ] ?? supports?.[ 'border' ] ) as
		| Record<string, unknown>
		| undefined

	return Boolean( border && 'object' === typeof border && true === border.gradient )
}

function addSaveProps(
	extraProps: Record<string, unknown>,
	blockType: BlockSettingsLike,
	attributes: GradientBorderAttributes & Record<string, unknown>,
): Record<string, unknown> {
	if ( ! blockSupportsConfigOnSettings( blockType ) ) {
		return extraProps
	}

	const scopeId = readScopeId( attributes )
	if ( ! scopeId || ! hasAuthoredAnyGradient( attributes ) ) {
		return extraProps
	}

	const existingClass = ( extraProps.className as string | undefined ) ?? ''
	const scopeClass    = scopeIdToClass( scopeId )

	if ( existingClass.split( ' ' ).includes( scopeClass ) ) {
		return extraProps
	}

	return {
		...extraProps,
		className: existingClass ? `${ existingClass } ${ scopeClass }` : scopeClass,
	}
}

function wrapSaveElement(
	element: unknown,
	blockType: BlockSettingsLike,
	attributes: GradientBorderAttributes & Record<string, unknown>,
): unknown {
	if ( ! blockSupportsConfigOnSettings( blockType ) ) {
		return element
	}

	const scopeId = readScopeId( attributes )
	if ( ! scopeId ) {
		return element
	}

	const payload = resolveGradientBorder( attributes )
	if ( ! payload ) {
		return element
	}

	const css = emitGradientBorderCss(
		`.${ scopeIdToClass( scopeId ) }`,
		payload,
		states,
		breakpoints,
	)

	if ( '' === css ) {
		return element
	}

	return createElement(
		Fragment,
		null,
		element as ReactNode,
		createElement( 'style', { 'data-ap-gradient-border-scope': scopeId }, css ),
	)
}

/**
 * Idempotent — safe to call from both the post-editor and site-editor
 * bootstrap paths. Late callers see the sentinel and no-op.
 */
export function registerGradientBorderStylesFilters(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( BLOCK_FILTER_HOOK, FILTER_NAMESPACE, withGradientBorderStyles )
	addFilter( SAVE_PROPS_HOOK, FILTER_NAMESPACE, addSaveProps )
	addFilter( SAVE_ELEMENT_HOOK, FILTER_NAMESPACE, wrapSaveElement )
	host[ REGISTERED_KEY ] = true
}
