/**
 * Flex layout serializer (#595).
 *
 * Pure function `(artisanpackFlex, registry) => { classes, arbitraryRules }`.
 * Source of truth for class generation; the Blade `FlexSupport`, the
 * React renderer, and the Vue renderer all mirror this exactly. Parity
 * is asserted via the shared `fixtures.json` test set.
 *
 * Class shape mirrors Tailwind:
 *   ap-flex
 *   ap-flex-{row|col|row-reverse|col-reverse}
 *   ap-flex-{nowrap|wrap|wrap-reverse}
 *   ap-justify-{start|center|end|between|around|evenly|stretch|baseline|left|right}
 *   ap-items-{start|center|end|stretch|baseline|self-start|self-end|first-baseline|last-baseline}
 *   ap-content-{start|center|end|between|around|evenly|stretch|baseline}
 *   ap-place-content-[value]
 *   ap-gap-x-[value]  ap-gap-y-[value]
 *   ap-self-{auto|start|center|end|stretch|baseline|self-start|self-end}
 *   ap-grow-{n}  ap-grow-[n]
 *   ap-shrink-{n}  ap-shrink-[n]
 *   ap-basis-{auto|...}  ap-basis-[value]
 *   ap-order-{n}  ap-order-[n]
 *
 * Responsive prefix: `{bp}:` (sm:, md:, lg:, xl:, 2xl:).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { BreakpointRegistry } from '../../../responsive/registry'
import { BASE_KEY, type ResponsiveAttribute } from '../../../responsive/types'
import { distinctOverrides } from '../../../responsive/resolver'
import type {
	AlignContentValue,
	AlignItemsValue,
	AlignSelfValue,
	ArtisanpackFlexAttribute,
	FlexDirectionValue,
	FlexWrapValue,
	JustifyContentValue,
} from './types'

export interface SerializeResult {
	classes: string[]
	arbitraryRules: ArbitraryRule[]
}

export interface ArbitraryRule {
	className: string
	property: string
	value: string
	breakpoint: string
}

const JUSTIFY_TOKEN: Record<JustifyContentValue, string> = {
	'flex-start': 'start',
	'flex-end': 'end',
	'center': 'center',
	'space-between': 'between',
	'space-around': 'around',
	'space-evenly': 'evenly',
	'start': 'start',
	'end': 'end',
	'left': 'left',
	'right': 'right',
	'stretch': 'stretch',
}

const ALIGN_ITEMS_TOKEN: Record<AlignItemsValue, string> = {
	'stretch': 'stretch',
	'flex-start': 'start',
	'flex-end': 'end',
	'center': 'center',
	'baseline': 'baseline',
	'start': 'start',
	'end': 'end',
	'self-start': 'self-start',
	'self-end': 'self-end',
	'first baseline': 'first-baseline',
	'last baseline': 'last-baseline',
}

const ALIGN_CONTENT_TOKEN: Record<AlignContentValue, string> = {
	'stretch': 'stretch',
	'flex-start': 'start',
	'flex-end': 'end',
	'center': 'center',
	'space-between': 'between',
	'space-around': 'around',
	'space-evenly': 'evenly',
	'start': 'start',
	'end': 'end',
	'baseline': 'baseline',
}

const ALIGN_SELF_TOKEN: Record<AlignSelfValue, string> = {
	'auto': 'auto',
	'flex-start': 'start',
	'flex-end': 'end',
	'center': 'center',
	'stretch': 'stretch',
	'baseline': 'baseline',
	'start': 'start',
	'end': 'end',
	'self-start': 'self-start',
	'self-end': 'self-end',
}

const DIRECTION_TOKEN: Record<FlexDirectionValue, string> = {
	'row': 'row',
	'row-reverse': 'row-reverse',
	'column': 'col',
	'column-reverse': 'col-reverse',
}

const WRAP_TOKEN: Record<FlexWrapValue, string> = {
	'nowrap': 'nowrap',
	'wrap': 'wrap',
	'wrap-reverse': 'wrap-reverse',
}

const NUMERIC_GROW_SHRINK = new Set<number>( [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ] )
const NUMERIC_ORDER       = new Set<number>( [ -1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ] )
const BASIS_KEYWORDS      = new Set<string>( [
	'auto',
	'0',
	'full',
	'fit-content',
	'max-content',
	'min-content',
] )

function prefix( breakpoint: string ): string {
	return BASE_KEY === breakpoint ? '' : `${ breakpoint }:`
}

function isPlainNumberToken( raw: string ): raw is `${ number }` {
	return /^-?\d+$/.test( raw )
}

function bracket( value: string ): string {
	return `[${ value.replace( /\s+/g, '_' ) }]`
}

function emitForEachBreakpoint<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	registry: BreakpointRegistry,
	cb: ( value: T, breakpoint: string ) => void,
): void {
	if ( null === attribute || undefined === attribute ) {
		return
	}

	const overrides = distinctOverrides<T>( attribute, registry )

	for ( const breakpoint of registry.keysWithBase() ) {
		if ( ! ( breakpoint in overrides ) ) {
			continue
		}

		const value = overrides[ breakpoint ]

		if ( null === value || undefined === value ) {
			continue
		}

		cb( value, breakpoint )
	}
}

/**
 * Container serializer. Returns class list + any arbitrary-value rules
 * the renderer must emit as scoped CSS (gap, place-content with
 * non-canonical values).
 */
export function serializeFlexContainer(
	flex: ArtisanpackFlexAttribute | null | undefined,
	registry: BreakpointRegistry,
): SerializeResult {
	const classes: string[] = []
	const arbitraryRules: ArbitraryRule[] = []

	if ( ! flex?.container ) {
		return { classes, arbitraryRules }
	}

	const container = flex.container

	emitForEachBreakpoint<boolean | null>( container.enabled, registry, ( value, bp ) => {
		if ( true === value ) {
			classes.push( `${ prefix( bp ) }ap-flex` )
		}
	} )

	emitForEachBreakpoint<FlexDirectionValue | null>( container.direction, registry, ( value, bp ) => {
		const token = DIRECTION_TOKEN[ value as FlexDirectionValue ]
		if ( token ) {
			classes.push( `${ prefix( bp ) }ap-flex-${ token }` )
		}
	} )

	emitForEachBreakpoint<FlexWrapValue | null>( container.wrap, registry, ( value, bp ) => {
		const token = WRAP_TOKEN[ value as FlexWrapValue ]
		if ( token ) {
			classes.push( `${ prefix( bp ) }ap-flex-${ token }` )
		}
	} )

	emitForEachBreakpoint<JustifyContentValue | null>( container.justifyContent, registry, ( value, bp ) => {
		const token = JUSTIFY_TOKEN[ value as JustifyContentValue ]
		if ( token ) {
			classes.push( `${ prefix( bp ) }ap-justify-${ token }` )
		}
	} )

	emitForEachBreakpoint<AlignItemsValue | null>( container.alignItems, registry, ( value, bp ) => {
		const token = ALIGN_ITEMS_TOKEN[ value as AlignItemsValue ]
		if ( token ) {
			classes.push( `${ prefix( bp ) }ap-items-${ token }` )
		}
	} )

	emitForEachBreakpoint<AlignContentValue | null>( container.alignContent, registry, ( value, bp ) => {
		const token = ALIGN_CONTENT_TOKEN[ value as AlignContentValue ]
		if ( token ) {
			classes.push( `${ prefix( bp ) }ap-content-${ token }` )
		}
	} )

	emitForEachBreakpoint<string>( container.placeContent as never, registry, ( value, bp ) => {
		const className = `${ prefix( bp ) }ap-place-content-${ bracket( value ) }`
		classes.push( className )
		arbitraryRules.push( {
			className,
			property: 'place-content',
			value,
			breakpoint: bp,
		} )
	} )

	if ( container.gap ) {
		emitForEachBreakpoint<string>( container.gap.row as never, registry, ( value, bp ) => {
			const className = `${ prefix( bp ) }ap-gap-y-${ bracket( value ) }`
			classes.push( className )
			arbitraryRules.push( {
				className,
				property: 'row-gap',
				value,
				breakpoint: bp,
			} )
		} )

		emitForEachBreakpoint<string>( container.gap.column as never, registry, ( value, bp ) => {
			const className = `${ prefix( bp ) }ap-gap-x-${ bracket( value ) }`
			classes.push( className )
			arbitraryRules.push( {
				className,
				property: 'column-gap',
				value,
				breakpoint: bp,
			} )
		} )
	}

	return { classes, arbitraryRules }
}

/**
 * Item serializer. Returns wrapper classes for a flex child.
 */
export function serializeFlexItem(
	flex: ArtisanpackFlexAttribute | null | undefined,
	registry: BreakpointRegistry,
): SerializeResult {
	const classes: string[] = []
	const arbitraryRules: ArbitraryRule[] = []

	if ( ! flex?.item ) {
		return { classes, arbitraryRules }
	}

	const item = flex.item

	emitForEachBreakpoint<AlignSelfValue | null>( item.alignSelf, registry, ( value, bp ) => {
		const token = ALIGN_SELF_TOKEN[ value as AlignSelfValue ]
		if ( token ) {
			classes.push( `${ prefix( bp ) }ap-self-${ token }` )
		}
	} )

	emitForEachBreakpoint<number | null>( item.grow, registry, ( value, bp ) => {
		const numeric = Number( value )
		if ( Number.isFinite( numeric ) && NUMERIC_GROW_SHRINK.has( numeric ) ) {
			classes.push( `${ prefix( bp ) }ap-grow-${ numeric }` )
			return
		}

		const raw = String( value )
		const className = `${ prefix( bp ) }ap-grow-${ bracket( raw ) }`
		classes.push( className )
		arbitraryRules.push( {
			className,
			property: 'flex-grow',
			value: raw,
			breakpoint: bp,
		} )
	} )

	emitForEachBreakpoint<number | null>( item.shrink, registry, ( value, bp ) => {
		const numeric = Number( value )
		if ( Number.isFinite( numeric ) && NUMERIC_GROW_SHRINK.has( numeric ) ) {
			classes.push( `${ prefix( bp ) }ap-shrink-${ numeric }` )
			return
		}

		const raw = String( value )
		const className = `${ prefix( bp ) }ap-shrink-${ bracket( raw ) }`
		classes.push( className )
		arbitraryRules.push( {
			className,
			property: 'flex-shrink',
			value: raw,
			breakpoint: bp,
		} )
	} )

	emitForEachBreakpoint<string>( item.basis as never, registry, ( value, bp ) => {
		if ( BASIS_KEYWORDS.has( value ) ) {
			classes.push( `${ prefix( bp ) }ap-basis-${ value }` )
			return
		}

		const className = `${ prefix( bp ) }ap-basis-${ bracket( value ) }`
		classes.push( className )
		arbitraryRules.push( {
			className,
			property: 'flex-basis',
			value,
			breakpoint: bp,
		} )
	} )

	emitForEachBreakpoint<number | null>( item.order, registry, ( value, bp ) => {
		const numeric = Number( value )
		if ( Number.isFinite( numeric ) && NUMERIC_ORDER.has( numeric ) ) {
			classes.push( `${ prefix( bp ) }ap-order-${ numeric }` )
			return
		}

		const raw = String( value )
		const className = `${ prefix( bp ) }ap-order-${ bracket( raw ) }`
		classes.push( className )
		arbitraryRules.push( {
			className,
			property: 'order',
			value: raw,
			breakpoint: bp,
		} )
	} )

	return { classes, arbitraryRules }
}

/**
 * Convenience that runs both container + item serializers and returns
 * a single merged result. Most call sites need only one of the two,
 * but the Blade renderer's wrapper helper uses this.
 */
export function serializeFlex(
	flex: ArtisanpackFlexAttribute | null | undefined,
	registry: BreakpointRegistry,
): SerializeResult {
	const container = serializeFlexContainer( flex, registry )
	const item      = serializeFlexItem( flex, registry )

	return {
		classes: [ ...container.classes, ...item.classes ],
		arbitraryRules: [ ...container.arbitraryRules, ...item.arbitraryRules ],
	}
}

export const FLEX_SERIALIZER_VERSION = 1

// Suppress unused import lint while preserving the type for downstream re-exports.
export type { ArtisanpackFlexAttribute }

// Ensure isPlainNumberToken is not pruned by tree-shakers when consumed via dynamic import.
void isPlainNumberToken
