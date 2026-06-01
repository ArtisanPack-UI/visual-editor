/**
 * Client-side state registry (#488).
 *
 * Mirrors the PHP `StateRegistry` so the editor can resolve
 * inheritance chains and surface state metadata without
 * round-tripping to the server. Hydrated from `editor-settings`'s
 * `states` array which the bootstrap path stamps from the merged PHP
 * config + theme.json.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY, type StateDefinition, type StateRegistrySnapshot } from './types'

const MAX_CHAIN_DEPTH = 16

export const DEFAULT_STATES: StateDefinition[] = [
	{
		key:            'idle',
		label:          'Idle',
		selector:       '',
		icon:           'circle',
		inheritsFrom:   null,
		hoverMediaWrap: false,
	},
	{
		key:            'hover',
		label:          'Hover',
		selector:       '&:hover',
		icon:           'cursor',
		inheritsFrom:   'idle',
		hoverMediaWrap: true,
	},
	{
		key:            'focus',
		label:          'Focus',
		selector:       '&:focus',
		icon:           'target',
		inheritsFrom:   'idle',
		hoverMediaWrap: false,
	},
	{
		key:            'focus-visible',
		label:          'Focus visible',
		selector:       '&:focus-visible',
		icon:           'target-arrow',
		inheritsFrom:   'focus',
		hoverMediaWrap: false,
	},
	{
		key:            'active',
		label:          'Active',
		selector:       '&:active',
		icon:           'click',
		inheritsFrom:   'hover',
		hoverMediaWrap: false,
	},
	{
		key:            'disabled',
		label:          'Disabled',
		selector:       '&:disabled, &[aria-disabled="true"]',
		icon:           'block',
		inheritsFrom:   'idle',
		hoverMediaWrap: false,
	},
]

export class StateRegistry {
	protected readonly states: Map<string, StateDefinition>

	constructor( states: StateDefinition[] = DEFAULT_STATES ) {
		const idle = states.find( ( s ) => BASE_KEY === s.key )
		if ( ! idle ) {
			throw new Error( `State registry is missing the reserved "${ BASE_KEY }" base state.` )
		}

		this.states = new Map()

		// Hoist `idle` to the front so iteration is stable.
		this.states.set( BASE_KEY, { ...idle, inheritsFrom: null, selector: '' } )

		for ( const state of states ) {
			if ( BASE_KEY === state.key ) {
				continue
			}

			this.states.set( state.key, { ...state } )
		}
	}

	all(): StateDefinition[] {
		return Array.from( this.states.values() )
	}

	keys(): string[] {
		return Array.from( this.states.keys() )
	}

	get( key: string ): StateDefinition | null {
		return this.states.get( key ) ?? null
	}

	has( key: string ): boolean {
		return this.states.has( key )
	}

	/**
	 * Returns the chain of state keys to walk when resolving a value
	 * at the given state, starting at `state` itself and following
	 * `inheritsFrom` until `idle`. Unknown states collapse to
	 * `[idle]`. The chain always terminates at `idle`.
	 */
	inheritanceChain( state: string ): string[] {
		if ( BASE_KEY === state || ! this.has( state ) ) {
			return [ BASE_KEY ]
		}

		const chain: string[]   = []
		let current: string | null = state
		let depth                  = 0

		while ( null !== current && depth < MAX_CHAIN_DEPTH ) {
			chain.push( current )

			if ( BASE_KEY === current ) {
				return chain
			}

			const definition: StateDefinition | null = this.states.get( current ) ?? null
			current                                  = definition?.inheritsFrom ?? null
			depth++
		}

		if ( ! chain.includes( BASE_KEY ) ) {
			chain.push( BASE_KEY )
		}

		return chain
	}

	toJSON(): StateRegistrySnapshot {
		return { states: this.all() }
	}
}

/**
 * Build a registry from a serialized snapshot — typically the JSON
 * the editor bootstrap stamps into
 * `window.artisanpackVisualEditor.settings`.
 */
export function registryFromSnapshot( snapshot: StateRegistrySnapshot | undefined ): StateRegistry {
	if ( ! snapshot || ! Array.isArray( snapshot.states ) || 0 === snapshot.states.length ) {
		return new StateRegistry()
	}

	return new StateRegistry( snapshot.states )
}
