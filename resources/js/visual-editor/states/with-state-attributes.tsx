/**
 * `editor.BlockEdit` HOC — invisibly route attribute reads and writes
 * through the active interactive state (#488).
 *
 * Every block that declares `supports.artisanpackStates` is wrapped.
 * The wrapper does two things, both transparent to the native
 * Gutenberg panels:
 *
 *  1. **Reads.** Builds a view where the attributes the panels see
 *     are the base values overlaid with anything stored under
 *     `attributes.states.<path>.<activeState>`. So when the editor
 *     switches the inspector state chip to `hover`, the color panel
 *     shows the hover background color instead of the idle one.
 *
 *  2. **Writes.** When the active state is `idle`, writes fall through
 *     to the normal `setAttributes` (touching
 *     `attributes.style.color.background` as today). When the active
 *     state is non-`idle`, the writes are diffed against the
 *     pre-merge attributes; any leaf that landed under one of the
 *     opt-in roots (e.g. `style.color.background`) is stored under
 *     `attributes.states.<path>.<activeState>` instead of mutating
 *     the base. Leaves outside the opt-in roots fall through to the
 *     base.
 *
 *  Editors see zero new UI inside the panels. They pick a state in
 *  the inspector, edit colors/borders like always, and the value
 *  sticks to that state.
 *
 * Mirrors `withResponsiveAttributes`; the two HOCs compose cleanly
 * because they route through orthogonal storage bags
 * (`attributes.responsive` vs `attributes.states`).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { hasBlockSupport, getBlockType } from '@wordpress/blocks'
import { createHigherOrderComponent } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { useCallback, useMemo, useSyncExternalStore } from 'react'
import type { ComponentType } from 'react'

import {
	deepMerge,
	diffPaths,
	pathMatchesAnyRoot,
	setPath,
} from '../responsive/attribute-paths'
import { getActiveState, subscribeActiveState } from './active-state'
import { getStateRegistry } from './registry'
import { BASE_KEY } from './types'

const FILTER_HOOK      = 'editor.BlockEdit'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/state-attributes'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.state-attributes.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockEditProps {
	name: string
	attributes: Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
	[key: string]: unknown
}

interface StateSupports {
	attributes?: string[]
	states?: string[]
}

type StateOverridesByPath = Record<string, Record<string, unknown>>

function getStateRoots( name: string ): string[] | null {
	const blockType = getBlockType( name )

	if ( ! blockType ) {
		return null
	}

	if ( ! hasBlockSupport( blockType, 'artisanpackStates', false ) ) {
		return null
	}

	const support = ( blockType.supports as Record<string, unknown> )
		.artisanpackStates

	if ( ! support || 'object' !== typeof support ) {
		return null
	}

	const roots = ( support as StateSupports ).attributes

	if ( ! Array.isArray( roots ) || 0 === roots.length ) {
		return null
	}

	return roots
}

/**
 * Build the overlay object that, when deep-merged into the base
 * attributes, produces the read view for the active state.
 *
 * Mirrors the PHP `StateValueResolver` cascade: every state path is
 * walked, taking the first non-null value in the inheritance chain.
 *
 * Resolves the chain against the *current* runtime registry via
 * {@link getStateRegistry} so custom states declared in theme.json
 * participate in the cascade. Pinning to a module-level default
 * registry here would silently strand any host-configured chains.
 */
function buildOverlay(
	states: StateOverridesByPath | null | undefined,
	activeState: string,
): Record<string, unknown> {
	if ( ! states || BASE_KEY === activeState ) {
		return {}
	}

	const chain = getStateRegistry().inheritanceChain( activeState )

	// Skip `idle` — it's the base attribute already, not an overlay
	// source.
	const overlayChain = chain.filter( ( key ) => BASE_KEY !== key )

	if ( 0 === overlayChain.length ) {
		return {}
	}

	const overlay: Record<string, unknown> = {}

	for ( const path of Object.keys( states ) ) {
		const overridesForPath = states[ path ]

		if ( ! overridesForPath || 'object' !== typeof overridesForPath ) {
			continue
		}

		for ( const key of overlayChain ) {
			if ( key in overridesForPath ) {
				const value = overridesForPath[ key ]

				if ( null === value || undefined === value ) {
					continue
				}

				writeInto( overlay, path, value )
				break
			}
		}
	}

	return overlay
}

function writeInto( target: Record<string, unknown>, path: string, value: unknown ): void {
	const segments = path.split( '.' )
	let cursor: Record<string, unknown> = target

	for ( let i = 0; i < segments.length - 1; i++ ) {
		const segment  = segments[ i ]
		const existing = cursor[ segment ]

		if ( ! existing || 'object' !== typeof existing || Array.isArray( existing ) ) {
			const next: Record<string, unknown> = {}
			cursor[ segment ] = next
			cursor = next
		} else {
			cursor = existing as Record<string, unknown>
		}
	}

	cursor[ segments[ segments.length - 1 ] ] = value
}

export const withStateAttributes = createHigherOrderComponent(
	( BlockEdit: ComponentType<BlockEditProps> ) => {
		function StateBlockEdit( props: BlockEditProps ): JSX.Element {
			const { name, attributes, setAttributes } = props

			const roots = useMemo( () => getStateRoots( name ), [ name ] )

			const activeState = useSyncExternalStore(
				subscribeActiveState,
				getActiveState,
				getActiveState,
			)

			const states = ( attributes.states as StateOverridesByPath | null | undefined ) ?? null

			const mergedAttributes = useMemo( () => {
				if ( ! roots || BASE_KEY === activeState ) {
					return attributes
				}

				const overlay = buildOverlay( states, activeState )

				if ( 0 === Object.keys( overlay ).length ) {
					return attributes
				}

				return deepMerge( attributes, overlay )
			}, [ attributes, states, activeState, roots ] )

			const wrappedSetAttributes = useCallback(
				( updates: Record<string, unknown> ): void => {
					if ( ! roots || BASE_KEY === activeState ) {
						setAttributes( updates )
						return
					}

					// Diff what the native panel just produced against the
					// merged read view it was looking at — only the leaves
					// the editor actually changed should be routed.
					const changedLeaves = diffPaths( updates, mergedAttributes )

					if ( 0 === changedLeaves.length ) {
						return
					}

					const baseUpdates: Record<string, unknown> = {}
					let statesPatch: StateOverridesByPath | null = null

					for ( const { path, value } of changedLeaves ) {
						if ( pathMatchesAnyRoot( path, roots ) ) {
							statesPatch = statesPatch ?? {}
							statesPatch[ path ] = {
								...( states?.[ path ] ?? {} ),
								[ activeState ]: value,
							}
							continue
						}

						Object.assign(
							baseUpdates,
							setPath( baseUpdates, path, value ),
						)
					}

					const finalUpdates: Record<string, unknown> = { ...baseUpdates }

					if ( statesPatch ) {
						finalUpdates.states = {
							...( states ?? {} ),
							...statesPatch,
						}
					}

					setAttributes( finalUpdates )
				},
				[ activeState, mergedAttributes, states, roots, setAttributes ],
			)

			if ( ! roots ) {
				return <BlockEdit { ...props } />
			}

			return (
				<BlockEdit
					{ ...props }
					attributes={ mergedAttributes }
					setAttributes={ wrappedSetAttributes }
				/>
			)
		}

		StateBlockEdit.displayName = 'StateBlockEdit'

		return StateBlockEdit
	},
	'withStateAttributes',
)

/**
 * Idempotent — safe to call from both the post-editor and site-editor
 * bootstrap paths. Late callers see the sentinel and no-op.
 */
export function registerStateAttributesFilter(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, withStateAttributes )
	host[ REGISTERED_KEY ] = true
}
