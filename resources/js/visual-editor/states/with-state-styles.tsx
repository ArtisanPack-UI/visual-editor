/**
 * State CSS emission (#488).
 *
 * Wires the JS `emitStateCss()` helper to:
 *
 *  1. **The editor canvas** — `editor.BlockListBlock` HOC. Computes a
 *     stable scope class per `clientId`, injects a `<style>` element
 *     into the block wrapper, and stamps the scope class onto the
 *     wrapper props so the rules actually match.
 *
 *  2. **Saved markup** — `blocks.getSaveContent.extraProps` stamps the
 *     scope class on the block root, and `blocks.getSaveElement`
 *     wraps the saved tree with a sibling `<style>` element so the
 *     same rules ride along into the published page. No server-side
 *     renderer changes required for the v1.0 surface.
 *
 * The scope class is derived from a stable per-block id stored on
 * `attributes.states._scopeId`. The HOC mints one the first time a
 * block carries any state override; downstream reads use whatever id
 * is already there. Storing it on the attributes (rather than
 * regenerating each render) keeps the editor and save markup in
 * lockstep so editor previews always reflect the published output.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { hasBlockSupport, getBlockType } from '@wordpress/blocks'
import { createHigherOrderComponent } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { Fragment, createElement, useEffect, useMemo, useRef } from 'react'
import type { ComponentType, ReactNode } from 'react'

import { emitStateCss, type StatesByPath } from './css-emitter'
import { getStateRegistry } from './registry'

const BLOCK_FILTER_HOOK = 'editor.BlockListBlock'
const SAVE_PROPS_HOOK   = 'blocks.getSaveContent.extraProps'
const SAVE_ELEMENT_HOOK = 'blocks.getSaveElement'

const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/state-styles'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.state-styles.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface StateBag {
	_scopeId?: string
	[path: string]: unknown
}

interface BlockListBlockProps {
	name: string
	clientId: string
	attributes: Record<string, unknown>
	setAttributes?: ( updates: Record<string, unknown> ) => void
	wrapperProps?: Record<string, unknown>
	[key: string]: unknown
}

interface BlockSettingsLike {
	supports?: Record<string, unknown>
	attributes?: Record<string, unknown>
	[key: string]: unknown
}

function blockSupportsStates( name: string ): boolean {
	const blockType = getBlockType( name )
	if ( ! blockType ) {
		return false
	}

	return hasBlockSupport( blockType, 'artisanpackStates', false )
}

/**
 * Split the `_scopeId` housekeeping field out of the state bag so the
 * CSS emitter only sees attribute-path keys.
 */
function partitionStates( bag: unknown ): { scopeId: string | null; paths: StatesByPath } {
	if ( ! bag || 'object' !== typeof bag || Array.isArray( bag ) ) {
		return { scopeId: null, paths: {} }
	}

	const { _scopeId, ...paths } = bag as StateBag
	return {
		scopeId: 'string' === typeof _scopeId && '' !== _scopeId ? _scopeId : null,
		paths:   paths as StatesByPath,
	}
}

function scopeIdToClass( scopeId: string ): string {
	return `ap-state-${ scopeId }`
}

function hasAuthoredOverrides( paths: StatesByPath ): boolean {
	for ( const value of Object.values( paths ) ) {
		if ( value && 'object' === typeof value && ! Array.isArray( value ) ) {
			return true
		}
	}

	return false
}

// `Math.random()` is fine here — collisions only matter within a
// single page and 36^9 ≈ 10^14 keeps the birthday-paradox floor well
// above any realistic block count.
function mintScopeId(): string {
	return Math.random().toString( 36 ).slice( 2, 11 )
}

/**
 * Tracks which clientId currently owns each scope id so duplicated
 * blocks (which inherit the parent's `_scopeId` via attribute copy)
 * can detect the collision and re-mint. Living at module scope so
 * every HOC instance shares the same ledger; cleared via WeakMap-style
 * write semantics in {@see takeScopeId} / {@see releaseScopeId}.
 */
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

export const withStateStyles = createHigherOrderComponent(
	( BlockListBlock: ComponentType<BlockListBlockProps> ) => {
		function StateStyledBlock( props: BlockListBlockProps ): JSX.Element {
			const { name, clientId, attributes, setAttributes, wrapperProps } = props

			// Compute support up front, then run every hook unconditionally —
			// the React Rules of Hooks require a stable hook order across
			// renders, so the gated branch must come after the hook calls.
			const supportsStates = blockSupportsStates( name )

			const { scopeId, paths } = useMemo(
				() => partitionStates( attributes.states ),
				[ attributes.states ],
			)

			// Once a block has authored any state overrides, persist a
			// stable scope id so editor + save markup stay in lockstep.
			// Skipped when the block has no overrides yet — keeps unused
			// blocks from accumulating noise in their attribute trees.
			const persistedRef = useRef( scopeId )
			useEffect( () => {
				persistedRef.current = scopeId
			}, [ scopeId ] )

			useEffect( () => {
				if ( ! supportsStates || ! setAttributes ) {
					return
				}

				// Re-mint when the block has overrides but no scope id
				// yet, OR when its scope id is already claimed by a
				// different clientId (block duplication copies the
				// attribute bag verbatim, including `_scopeId`, which
				// would otherwise produce two blocks sharing one CSS
				// scope and bleed state styles across them).
				const needsMint    = ! scopeId && hasAuthoredOverrides( paths )
				const hasCollision = scopeId !== null && ! claimScopeId( scopeId, clientId )

				if ( ! needsMint && ! hasCollision ) {
					return
				}

				const nextId   = mintScopeId()
				claimScopeId( nextId, clientId )

				const nextBag  = {
					...( ( attributes.states as StateBag | null | undefined ) ?? {} ),
					_scopeId: nextId,
				}

				setAttributes( { states: nextBag } )
			}, [ supportsStates, scopeId, paths, attributes.states, setAttributes, clientId ] )

			// Release the scope id when this block instance unmounts so
			// later blocks can re-use it cleanly. The ledger only needs
			// to enforce uniqueness *within the lifetime of the editor
			// tree*; once the owning block is gone, the scope id is
			// free again.
			useEffect( () => {
				if ( ! scopeId ) {
					return
				}

				return () => releaseScopeId( scopeId, clientId )
			}, [ scopeId, clientId ] )

			const effectiveScopeId = scopeId ?? persistedRef.current

			const css = useMemo( () => {
				if ( ! supportsStates || ! effectiveScopeId ) {
					return ''
				}

				return emitStateCss( `.${ scopeIdToClass( effectiveScopeId ) }`, paths, getStateRegistry() )
			}, [ supportsStates, effectiveScopeId, paths ] )

			const effectiveWrapperProps: Record<string, unknown> = useMemo( () => {
				if ( ! supportsStates || ! effectiveScopeId ) {
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
			}, [ supportsStates, wrapperProps, effectiveScopeId ] )

			if ( ! supportsStates ) {
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
					<style data-ap-state-scope={ effectiveScopeId }>{ css }</style>
					{ wrapped }
				</Fragment>
			)
		}

		StateStyledBlock.displayName = 'StateStyledBlock'

		return StateStyledBlock
	},
	'withStateStyles',
)

/**
 * Returns true only when the block's `supports.artisanpackStates`
 * carries a truthy value (object or `true`). Key-only checks would
 * green-light blocks that explicitly opted out via
 * `supports.artisanpackStates: false`.
 */
function blockSupportsConfig( blockType: BlockSettingsLike | undefined ): boolean {
	const support = blockType?.supports?.artisanpackStates
	return Boolean( support )
}

function addSaveProps(
	extraProps: Record<string, unknown>,
	blockType: BlockSettingsLike,
	attributes: Record<string, unknown>,
): Record<string, unknown> {
	if ( ! blockSupportsConfig( blockType ) ) {
		return extraProps
	}

	const { scopeId } = partitionStates( attributes.states )
	if ( ! scopeId ) {
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
	attributes: Record<string, unknown>,
): unknown {
	if ( ! blockSupportsConfig( blockType ) ) {
		return element
	}

	const { scopeId, paths } = partitionStates( attributes.states )
	if ( ! scopeId || ! hasAuthoredOverrides( paths ) ) {
		return element
	}

	const css = emitStateCss( `.${ scopeIdToClass( scopeId ) }`, paths, getStateRegistry() )
	if ( '' === css ) {
		return element
	}

	return createElement( Fragment, null,
		element as ReactNode,
		createElement( 'style', { 'data-ap-state-scope': scopeId }, css ),
	)
}

/**
 * Idempotent — safe to call from both the post-editor and site-editor
 * bootstrap paths. Late callers see the sentinel and no-op.
 */
export function registerStateStylesFilters(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( BLOCK_FILTER_HOOK, FILTER_NAMESPACE, withStateStyles )
	addFilter( SAVE_PROPS_HOOK, FILTER_NAMESPACE, addSaveProps )
	addFilter( SAVE_ELEMENT_HOOK, FILTER_NAMESPACE, wrapSaveElement )
	host[ REGISTERED_KEY ] = true
}
