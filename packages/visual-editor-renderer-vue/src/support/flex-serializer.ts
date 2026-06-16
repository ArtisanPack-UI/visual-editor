/**
 * Flex layout serializer — Vue renderer (#595).
 *
 * Mirrors `resources/js/visual-editor/blocks/_shared/flex-controls/
 * serializer.ts`. The single source of truth lives in the editor; this
 * copy plus the matching vitest suite over the shared fixtures keeps
 * the Vue renderer in lockstep.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.2.0
 */

const BASE_KEY = 'base' as const

const TAILWIND_DEFAULTS: ReadonlyArray<{ key: string; minWidthPx: number }> = [
	{ key: 'sm', minWidthPx: 640 },
	{ key: 'md', minWidthPx: 768 },
	{ key: 'lg', minWidthPx: 1024 },
	{ key: 'xl', minWidthPx: 1280 },
	{ key: '2xl', minWidthPx: 1536 },
]

const JUSTIFY_TOKEN: Record<string, string> = {
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

const ALIGN_ITEMS_TOKEN: Record<string, string> = {
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

const ALIGN_CONTENT_TOKEN: Record<string, string> = {
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

const ALIGN_SELF_TOKEN: Record<string, string> = {
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

const DIRECTION_TOKEN: Record<string, string> = {
	'row': 'row',
	'row-reverse': 'row-reverse',
	'column': 'col',
	'column-reverse': 'col-reverse',
}

const WRAP_TOKEN: Record<string, string> = {
	'nowrap': 'nowrap',
	'wrap': 'wrap',
	'wrap-reverse': 'wrap-reverse',
}

const NUMERIC_GROW_SHRINK = new Set<number>( [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ] )
const NUMERIC_ORDER       = new Set<number>( [ -1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ] )
const BASIS_KEYWORDS      = new Set<string>( [
	'auto', '0', 'full', 'fit-content', 'max-content', 'min-content',
] )

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

function keysWithBase(): string[] {
	return [ BASE_KEY, ...TAILWIND_DEFAULTS.map( ( b ) => b.key ) ]
}

function resolveValue<T>( attribute: unknown, breakpoint: string ): T | null {
	if ( null === attribute || undefined === attribute ) {
		return null
	}

	if ( 'object' !== typeof attribute || Array.isArray( attribute ) ) {
		return attribute as T
	}

	const obj = attribute as Record<string, T | null | undefined>
	const cascade = ( BASE_KEY === breakpoint
		? [ BASE_KEY ]
		: keysWithBase().slice( 0, keysWithBase().indexOf( breakpoint ) + 1 ).reverse()
	)

	for ( const key of cascade ) {
		if ( ! ( key in obj ) ) {
			continue
		}
		const value = obj[ key ]
		if ( null === value || undefined === value ) {
			continue
		}
		return value
	}

	return null
}

function distinctOverrides<T>( attribute: unknown ): Record<string, T> {
	const out: Record<string, T> = {}
	let previous: T | null       = null
	let first                    = true

	for ( const key of keysWithBase() ) {
		const value = resolveValue<T>( attribute, key )
		if ( null === value ) {
			continue
		}
		if ( first || value !== previous ) {
			out[ key ] = value
			previous   = value
			first      = false
		}
	}
	return out
}

function prefix( breakpoint: string ): string {
	return BASE_KEY === breakpoint ? '' : `${ breakpoint }:`
}

function bracket( value: string ): string {
	return `[${ value.replace( /\s+/g, '_' ) }]`
}

function emitFor<T>(
	attribute: unknown,
	cb: ( value: T, breakpoint: string ) => void,
): void {
	const overrides = distinctOverrides<T>( attribute )
	for ( const bp of keysWithBase() ) {
		if ( ! ( bp in overrides ) ) {
			continue
		}
		cb( overrides[ bp ], bp )
	}
}

export function serializeFlex( flex: unknown ): SerializeResult {
	const classes: string[] = []
	const arbitraryRules: ArbitraryRule[] = []

	if ( ! flex || 'object' !== typeof flex ) {
		return { classes, arbitraryRules }
	}

	const c = ( flex as Record<string, unknown> ).container
	if ( c && 'object' === typeof c ) {
		const container = c as Record<string, unknown>

		emitFor<boolean | null>( container.enabled, ( v, bp ) => {
			if ( true === v ) {
				classes.push( `${ prefix( bp ) }ap-flex` )
			}
		} )

		emitFor<string | null>( container.direction, ( v, bp ) => {
			const t = DIRECTION_TOKEN[ v as string ]
			if ( t ) {
				classes.push( `${ prefix( bp ) }ap-flex-${ t }` )
			}
		} )

		emitFor<string | null>( container.wrap, ( v, bp ) => {
			const t = WRAP_TOKEN[ v as string ]
			if ( t ) {
				classes.push( `${ prefix( bp ) }ap-flex-${ t }` )
			}
		} )

		emitFor<string | null>( container.justifyContent, ( v, bp ) => {
			const t = JUSTIFY_TOKEN[ v as string ]
			if ( t ) {
				classes.push( `${ prefix( bp ) }ap-justify-${ t }` )
			}
		} )

		emitFor<string | null>( container.alignItems, ( v, bp ) => {
			const t = ALIGN_ITEMS_TOKEN[ v as string ]
			if ( t ) {
				classes.push( `${ prefix( bp ) }ap-items-${ t }` )
			}
		} )

		emitFor<string | null>( container.alignContent, ( v, bp ) => {
			const t = ALIGN_CONTENT_TOKEN[ v as string ]
			if ( t ) {
				classes.push( `${ prefix( bp ) }ap-content-${ t }` )
			}
		} )

		emitFor<string | null>( container.placeContent, ( v, bp ) => {
			const className = `${ prefix( bp ) }ap-place-content-${ bracket( v as string ) }`
			classes.push( className )
			arbitraryRules.push( { className, property: 'place-content', value: v as string, breakpoint: bp } )
		} )

		const gap = container.gap as Record<string, unknown> | undefined
		if ( gap ) {
			emitFor<string | null>( gap.row, ( v, bp ) => {
				const className = `${ prefix( bp ) }ap-gap-y-${ bracket( v as string ) }`
				classes.push( className )
				arbitraryRules.push( { className, property: 'row-gap', value: v as string, breakpoint: bp } )
			} )
			emitFor<string | null>( gap.column, ( v, bp ) => {
				const className = `${ prefix( bp ) }ap-gap-x-${ bracket( v as string ) }`
				classes.push( className )
				arbitraryRules.push( { className, property: 'column-gap', value: v as string, breakpoint: bp } )
			} )
		}
	}

	const i = ( flex as Record<string, unknown> ).item
	if ( i && 'object' === typeof i ) {
		const item = i as Record<string, unknown>

		emitFor<string | null>( item.alignSelf, ( v, bp ) => {
			const t = ALIGN_SELF_TOKEN[ v as string ]
			if ( t ) {
				classes.push( `${ prefix( bp ) }ap-self-${ t }` )
			}
		} )

		emitFor<number | null>( item.grow, ( v, bp ) => emitNumeric( 'flex-grow', 'ap-grow', v, bp, NUMERIC_GROW_SHRINK, classes, arbitraryRules ) )
		emitFor<number | null>( item.shrink, ( v, bp ) => emitNumeric( 'flex-shrink', 'ap-shrink', v, bp, NUMERIC_GROW_SHRINK, classes, arbitraryRules ) )
		emitFor<number | null>( item.order, ( v, bp ) => emitNumeric( 'order', 'ap-order', v, bp, NUMERIC_ORDER, classes, arbitraryRules ) )

		emitFor<string | null>( item.basis, ( v, bp ) => {
			if ( BASIS_KEYWORDS.has( v as string ) ) {
				classes.push( `${ prefix( bp ) }ap-basis-${ v }` )
				return
			}
			const className = `${ prefix( bp ) }ap-basis-${ bracket( v as string ) }`
			classes.push( className )
			arbitraryRules.push( { className, property: 'flex-basis', value: v as string, breakpoint: bp } )
		} )
	}

	return { classes, arbitraryRules }
}

function emitNumeric(
	property: string,
	prefixToken: string,
	value: number,
	bp: string,
	canonical: Set<number>,
	classes: string[],
	rules: ArbitraryRule[],
): void {
	const num = Number( value )
	if ( Number.isFinite( num ) && canonical.has( num ) ) {
		classes.push( `${ prefix( bp ) }${ prefixToken }-${ num }` )
		return
	}
	const raw = String( value )
	const className = `${ prefix( bp ) }${ prefixToken }-${ bracket( raw ) }`
	classes.push( className )
	rules.push( { className, property, value: raw, breakpoint: bp } )
}

export function flexClassNames( flex: unknown ): string[] {
	return serializeFlex( flex ).classes
}
