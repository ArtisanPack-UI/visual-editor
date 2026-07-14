/**
 * Position CSS emission — editor canvas + saved markup (#643).
 *
 * Mirrors `with-box-shadow-styles.tsx` from #607: a mintable scope id
 * (`_positionScopeId`) lives on the position subtree; the wrapper
 * carries a `ve-pos-<id>` class; and a `<style>` element scoped by
 * that class carries the emitted CSS. Both the canvas
 * (`editor.BlockListBlock`) and the save path
 * (`blocks.getSaveContent.extraProps` + `blocks.getSaveElement`)
 * apply the same emission.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { getBlockType } from '@wordpress/blocks'
import { createHigherOrderComponent } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { Fragment, createElement, useEffect, useMemo, useRef } from 'react'
import type { ComponentType, ReactNode } from 'react'

import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from '../responsive/registry'
import { emitPositionCss, mergedBreakpointLayers } from './emitter'
import { positionEnabled } from './extend-supports'
import { resolvePosition } from './resolver'
import type { PositionAttributes, PositionSubtree } from './types'

const BLOCK_FILTER_HOOK = 'editor.BlockListBlock'
const SAVE_PROPS_HOOK   = 'blocks.getSaveContent.extraProps'
const SAVE_ELEMENT_HOOK = 'blocks.getSaveElement'

const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/position-styles'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.position-styles.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockListBlockProps {
	name: string
	clientId: string
	attributes: PositionAttributes & Record<string, unknown>
	setAttributes?: ( updates: Record<string, unknown> ) => void
	wrapperProps?: Record<string, unknown>
	[key: string]: unknown
}

interface BlockSettingsLike {
	supports?: Record<string, unknown>
	[key: string]: unknown
}

interface PositionSubtreeWithScope extends PositionSubtree {
	_positionScopeId?: string
}

const SCOPE_ID_KEY     = '_positionScopeId'
const SCOPE_ID_PATTERN = /^[a-z0-9][a-z0-9_-]{0,63}$/i

const breakpoints = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

function blockSupportsPosition( name: string ): boolean {
	const blockType = getBlockType( name )
	if ( ! blockType ) {
		return false
	}

	return positionEnabled( blockType.supports as Record<string, unknown> | undefined )
}

function scopeIdToClass( scopeId: string ): string {
	return `ve-pos-${ scopeId }`
}

function readScopeId( attributes: PositionAttributes | undefined ): string | null {
	const raw = attributes?.style?.position
	if ( ! raw || 'object' !== typeof raw ) {
		return null
	}

	const scoped = raw as PositionSubtreeWithScope
	const value  = scoped[ SCOPE_ID_KEY ]

	if ( 'string' !== typeof value ) {
		return null
	}

	const trimmed = value.trim()
	return SCOPE_ID_PATTERN.test( trimmed ) ? trimmed : null
}

function hasAuthoredAnyPosition(
	attributes: PositionAttributes | undefined,
): boolean {
	return null !== resolvePosition( attributes )
}

function mintScopeId(): string {
	return Math.random().toString( 36 ).slice( 2, 11 )
}

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
	attributes: PositionAttributes,
	setAttributes: ( updates: Record<string, unknown> ) => void,
	nextId: string,
): void {
	const style = ( attributes.style && 'object' === typeof attributes.style )
		? ( attributes.style as Record<string, unknown> )
		: {}
	const rawPosition = style.position
	// `position` may be a bare string (legacy Gutenberg sticky) — widen
	// it to a structured subtree so we have a slot to write the scope
	// id onto without clobbering the value.
	const positionObj =
		rawPosition && 'object' === typeof rawPosition
			? ( rawPosition as Record<string, unknown> )
			: ( 'string' === typeof rawPosition
				? { value: rawPosition }
				: {} )

	setAttributes( {
		style: {
			...style,
			position: {
				...positionObj,
				[ SCOPE_ID_KEY ]: nextId,
			},
		},
	} )
}

function emitCssForBlock(
	scopeId: string,
	attributes: PositionAttributes,
): string {
	const payload = resolvePosition( attributes )
	if ( ! payload ) {
		return ''
	}

	const merged = mergedBreakpointLayers( payload, breakpoints )

	return emitPositionCss(
		`.${ scopeIdToClass( scopeId ) }`,
		payload,
		breakpoints,
		merged,
	)
}

export const withPositionStyles = createHigherOrderComponent(
	( BlockListBlock: ComponentType<BlockListBlockProps> ) => {
		function PositionStyledBlock( props: BlockListBlockProps ): JSX.Element {
			const { name, clientId, attributes, setAttributes, wrapperProps } = props

			const supported = blockSupportsPosition( name )

			const scopeId = useMemo( () => readScopeId( attributes ), [ attributes ] )

			const hasOverrides = useMemo(
				() => hasAuthoredAnyPosition( attributes ),
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
				return emitCssForBlock( effectiveScopeId, attributes )
			}, [ supported, effectiveScopeId, attributes ] )

			const effectiveWrapperProps: Record<string, unknown> = useMemo( () => {
				if ( ! supported || ! effectiveScopeId ) {
					return wrapperProps ?? {}
				}

				const existingClass = ( wrapperProps?.className as string | undefined ) ?? ''
				const scopeClass    = scopeIdToClass( effectiveScopeId )

				if ( existingClass.split( /\s+/ ).includes( scopeClass ) ) {
					return wrapperProps ?? {}
				}

				return {
					...( wrapperProps ?? {} ),
					className: existingClass
						? `${ existingClass } ${ scopeClass }`
						: scopeClass,
					// Diagnostic hook — surfaces the scope id in DevTools
					// so a QA pass can spot the presence/absence at a
					// glance without opening the block's props panel.
					'data-ap-position-scope': effectiveScopeId,
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
					<style data-ap-position-scope={ effectiveScopeId }>{ css }</style>
					{ wrapped }
				</Fragment>
			)
		}

		PositionStyledBlock.displayName = 'PositionStyledBlock'

		return PositionStyledBlock
	},
	'withPositionStyles',
)

function blockSupportsConfigOnSettings( blockType: BlockSettingsLike | undefined ): boolean {
	return positionEnabled( blockType?.supports as Record<string, unknown> | undefined )
}

function addSaveProps(
	extraProps: Record<string, unknown>,
	blockType: BlockSettingsLike,
	attributes: PositionAttributes & Record<string, unknown>,
): Record<string, unknown> {
	if ( ! blockSupportsConfigOnSettings( blockType ) ) {
		return extraProps
	}

	const scopeId = readScopeId( attributes )
	if ( ! scopeId || ! hasAuthoredAnyPosition( attributes ) ) {
		return extraProps
	}

	const css = emitCssForBlock( scopeId, attributes )
	if ( '' === css ) {
		return extraProps
	}

	const existingClass = ( extraProps.className as string | undefined ) ?? ''
	const scopeClass    = scopeIdToClass( scopeId )

	const nextClass = existingClass.split( /\s+/ ).includes( scopeClass )
		? existingClass
		: ( existingClass ? `${ existingClass } ${ scopeClass }` : scopeClass )

	// Also stamp the BASE layer as inline styles on the wrapper. The
	// `<style>` element `wrapSaveElement` appends below carries the same
	// declarations for the base rule PLUS every `@media` breakpoint
	// override, but `<style>` tags inside serialized block markup get
	// dropped by `wp_kses_post` / the equivalent sanitizer on plenty of
	// hosts. Inline styles survive that filter path and inherit the
	// wrapper's inline-style specificity (higher than the `!important`
	// class rule we emit for the editor canvas). Breakpoint overrides
	// still need `<style>` — no way to express `@media` inline.
	const baseStyle = baseInlineStyle( attributes )
	if ( '' === baseStyle ) {
		return {
			...extraProps,
			className: nextClass,
		}
	}

	const existingStyle = ( extraProps.style as Record<string, string> | string | undefined ) ?? undefined

	// Gutenberg's core panels sometimes stamp `style` as an object and
	// sometimes as a string — merge either into the resulting string
	// without duplicating declarations we've already stamped for a
	// prior render pass (same-attribute idempotency).
	const mergedStyle = mergeStyle( existingStyle, baseStyle )

	return {
		...extraProps,
		className: nextClass,
		style: mergedStyle,
	}
}

/**
 * Build a semicolon-terminated inline declaration string for the base
 * layer only — media-query overrides continue to ride the `<style>`
 * element from `wrapSaveElement`.
 */
function baseInlineStyle(
	attributes: PositionAttributes & Record<string, unknown>,
): string {
	const payload = resolvePosition( attributes )
	if ( ! payload ) {
		return ''
	}

	const base = payload.base
	if ( ! base || null === base.value || 'static' === base.value ) {
		return ''
	}

	// Mirror the PHP counterpart's `!important` — the inline fallback
	// only exists for hosts that strip the `<style data-ap-position-scope>`
	// element, and without `!important` a theme's `!important` reset on
	// the wrapper class outranks us in that exact scenario.
	const parts: string[] = [ `position: ${ base.value } !important` ]

	if ( null !== base.offsets.top ) {
		parts.push( `top: ${ formatInlineOffset( base.offsets.top ) } !important` )
	}
	if ( null !== base.offsets.right ) {
		parts.push( `right: ${ formatInlineOffset( base.offsets.right ) } !important` )
	}
	if ( null !== base.offsets.bottom ) {
		parts.push( `bottom: ${ formatInlineOffset( base.offsets.bottom ) } !important` )
	}
	if ( null !== base.offsets.left ) {
		parts.push( `left: ${ formatInlineOffset( base.offsets.left ) } !important` )
	}
	if ( null !== base.zIndex ) {
		parts.push( `z-index: ${ base.zIndex } !important` )
	}

	return parts.join( '; ' ) + ';'
}

function formatInlineOffset( offset: { value: number; unit: string } ): string {
	if ( 'auto' === offset.unit ) {
		return 'auto'
	}
	return `${ offset.value }${ offset.unit }`
}

// Anchor `position:` to a declaration boundary (start-of-string or
// after a semicolon), NOT any substring. `background-position:`,
// `object-position:`, `transition: position …` would otherwise trip
// the idempotency guard and silently drop our inline stamp — the
// wrapper renders unpositioned even though the user configured a value.
const POSITION_DECL_RE = /(?:^|;)\s*position\s*:/i

function mergeStyle(
	existing: Record<string, string> | string | undefined,
	next: string,
): string {
	if ( ! existing ) {
		return next
	}

	if ( 'string' === typeof existing ) {
		if ( POSITION_DECL_RE.test( existing ) ) {
			return existing
		}
		const trimmed = existing.trim()
		if ( '' === trimmed ) {
			return next
		}
		const separator = trimmed.endsWith( ';' ) ? ' ' : '; '
		return `${ trimmed }${ separator }${ next }`
	}

	// Object form — convert to string.
	const stringified = Object.entries( existing )
		.map( ( [ prop, val ] ) => `${ toKebab( prop ) }: ${ val }` )
		.join( '; ' )

	if ( '' === stringified ) {
		return next
	}

	if ( POSITION_DECL_RE.test( stringified ) ) {
		return stringified + ';'
	}

	return `${ stringified }; ${ next }`
}

function toKebab( value: string ): string {
	return value.replace( /[A-Z]/g, ( m ) => `-${ m.toLowerCase() }` )
}

function wrapSaveElement(
	element: unknown,
	blockType: BlockSettingsLike,
	attributes: PositionAttributes & Record<string, unknown>,
): unknown {
	if ( ! blockSupportsConfigOnSettings( blockType ) ) {
		return element
	}

	const scopeId = readScopeId( attributes )
	if ( ! scopeId ) {
		return element
	}

	const css = emitCssForBlock( scopeId, attributes )
	if ( '' === css ) {
		return element
	}

	return createElement(
		Fragment,
		null,
		element as ReactNode,
		createElement( 'style', { 'data-ap-position-scope': scopeId }, css ),
	)
}

/**
 * Idempotent — safe to call from both the post-editor and site-editor
 * bootstrap paths.
 */
export function registerPositionStylesFilters(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( BLOCK_FILTER_HOOK, FILTER_NAMESPACE, withPositionStyles )
	addFilter( SAVE_PROPS_HOOK, FILTER_NAMESPACE, addSaveProps )
	addFilter( SAVE_ELEMENT_HOOK, FILTER_NAMESPACE, wrapSaveElement )
	host[ REGISTERED_KEY ] = true
}
