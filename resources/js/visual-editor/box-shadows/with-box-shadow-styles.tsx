/**
 * Box-shadow CSS emission for the editor canvas + saved markup (#607).
 *
 * Mirrors `with-gradient-border-styles.tsx` (#490) one-to-one — see
 * that file's docblock for the three-filter lifecycle (canvas style,
 * save props, save element wrap) and the `_…ScopeId` rationale.
 *
 * The shadow scope id is persisted on
 * `attributes.style.shadow._shadowScopeId` (matching the gradient-
 * border `_gradientScopeId` pattern). The scope class is
 * `ve-bs-<id>`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { getBlockType } from '@wordpress/blocks'
import { createHigherOrderComponent } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { Fragment, createElement, useEffect, useMemo, useRef } from 'react'
import type { ComponentType, ReactNode } from 'react'

import { TAILWIND_V4_DEFAULTS, BreakpointRegistry } from '../responsive/registry'
import { DEFAULT_STATES, StateRegistry } from '../states/registry'
import { emitBoxShadowCss } from './emitter'
import { resolveBoxShadow } from './resolver'
import type { BoxShadowAttributes, ShadowSubtree } from './types'

const BLOCK_FILTER_HOOK = 'editor.BlockListBlock'
const SAVE_PROPS_HOOK   = 'blocks.getSaveContent.extraProps'
const SAVE_ELEMENT_HOOK = 'blocks.getSaveElement'

const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/box-shadow-styles'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.box-shadow-styles.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockListBlockProps {
	name: string
	clientId: string
	attributes: BoxShadowAttributes & Record<string, unknown>
	setAttributes?: ( updates: Record<string, unknown> ) => void
	wrapperProps?: Record<string, unknown>
	[key: string]: unknown
}

interface BlockSettingsLike {
	supports?: Record<string, unknown>
	[key: string]: unknown
}

interface ShadowSubtreeWithScope extends ShadowSubtree {
	_shadowScopeId?: string
}

const SCOPE_ID_KEY     = '_shadowScopeId'
const SCOPE_ID_PATTERN = /^[a-z0-9][a-z0-9_-]{0,63}$/i

const states      = new StateRegistry( DEFAULT_STATES )
const breakpoints = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

function blockSupportsBoxShadow( name: string ): boolean {
	const blockType = getBlockType( name )
	if ( ! blockType ) {
		return false
	}

	const supports = blockType.supports as Record<string, unknown> | undefined
	const support  = supports?.[ '__experimentalBorder' ] ?? supports?.[ 'border' ]

	return Boolean( support && 'object' === typeof support )
}

function scopeIdToClass( scopeId: string ): string {
	return `ve-bs-${ scopeId }`
}

function readScopeId(
	attributes: BoxShadowAttributes | undefined,
): string | null {
	const shadow = attributes?.style?.shadow as ShadowSubtreeWithScope | undefined | null
	const raw    = shadow?.[ SCOPE_ID_KEY ]

	if ( 'string' !== typeof raw ) {
		return null
	}

	const trimmed = raw.trim()
	return SCOPE_ID_PATTERN.test( trimmed ) ? trimmed : null
}

function hasAuthoredAnyShadow(
	attributes: BoxShadowAttributes | undefined,
): boolean {
	return null !== resolveBoxShadow( attributes )
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
	attributes: BoxShadowAttributes,
	setAttributes: ( updates: Record<string, unknown> ) => void,
	nextId: string,
): void {
	const style = ( attributes.style && 'object' === typeof attributes.style )
		? ( attributes.style as Record<string, unknown> )
		: {}
	const shadow = ( style.shadow && 'object' === typeof style.shadow )
		? ( style.shadow as Record<string, unknown> )
		: {}

	setAttributes( {
		style: {
			...style,
			shadow: {
				...shadow,
				[ SCOPE_ID_KEY ]: nextId,
			},
		},
	} )
}

export const withBoxShadowStyles = createHigherOrderComponent(
	( BlockListBlock: ComponentType<BlockListBlockProps> ) => {
		function BoxShadowStyledBlock( props: BlockListBlockProps ): JSX.Element {
			const { name, clientId, attributes, setAttributes, wrapperProps } = props

			const supported = blockSupportsBoxShadow( name )

			const scopeId = useMemo(
				() => readScopeId( attributes ),
				[ attributes ],
			)

			const hasOverrides = useMemo(
				() => hasAuthoredAnyShadow( attributes ),
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

				const payload = resolveBoxShadow( attributes )
				if ( ! payload ) {
					return ''
				}

				return emitBoxShadowCss(
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
					<style data-ap-box-shadow-scope={ effectiveScopeId }>{ css }</style>
					{ wrapped }
				</Fragment>
			)
		}

		BoxShadowStyledBlock.displayName = 'BoxShadowStyledBlock'

		return BoxShadowStyledBlock
	},
	'withBoxShadowStyles',
)

function blockSupportsConfigOnSettings(
	blockType: BlockSettingsLike | undefined,
): boolean {
	const supports = blockType?.supports as Record<string, unknown> | undefined
	const border   = ( supports?.[ '__experimentalBorder' ] ?? supports?.[ 'border' ] ) as
		| Record<string, unknown>
		| undefined

	return Boolean( border && 'object' === typeof border )
}

function addSaveProps(
	extraProps: Record<string, unknown>,
	blockType: BlockSettingsLike,
	attributes: BoxShadowAttributes & Record<string, unknown>,
): Record<string, unknown> {
	if ( ! blockSupportsConfigOnSettings( blockType ) ) {
		return extraProps
	}

	const scopeId = readScopeId( attributes )
	if ( ! scopeId || ! hasAuthoredAnyShadow( attributes ) ) {
		return extraProps
	}

	// Mirror `wrapSaveElement`'s emit-and-check below: don't stamp
	// the scope class onto saved markup unless the emitter actually
	// has rules to ship under that scope. Prevents an orphan class
	// landing on the wrapper when a resolved layer simplifies to an
	// empty rule (e.g. preset slug that fails sanitization).
	const payload = resolveBoxShadow( attributes )
	if ( ! payload ) {
		return extraProps
	}

	const css = emitBoxShadowCss(
		`.${ scopeIdToClass( scopeId ) }`,
		payload,
		states,
		breakpoints,
	)
	if ( '' === css ) {
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
	attributes: BoxShadowAttributes & Record<string, unknown>,
): unknown {
	if ( ! blockSupportsConfigOnSettings( blockType ) ) {
		return element
	}

	const scopeId = readScopeId( attributes )
	if ( ! scopeId ) {
		return element
	}

	const payload = resolveBoxShadow( attributes )
	if ( ! payload ) {
		return element
	}

	const css = emitBoxShadowCss(
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
		createElement( 'style', { 'data-ap-box-shadow-scope': scopeId }, css ),
	)
}

/**
 * Idempotent — safe to call from both the post-editor and site-editor
 * bootstrap paths.
 */
export function registerBoxShadowStylesFilters(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( BLOCK_FILTER_HOOK, FILTER_NAMESPACE, withBoxShadowStyles )
	addFilter( SAVE_PROPS_HOOK, FILTER_NAMESPACE, addSaveProps )
	addFilter( SAVE_ELEMENT_HOOK, FILTER_NAMESPACE, wrapSaveElement )
	host[ REGISTERED_KEY ] = true
}
