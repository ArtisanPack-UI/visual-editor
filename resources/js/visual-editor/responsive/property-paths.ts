/**
 * The responsive property paths the renderers emit CSS for (#918).
 *
 * The renderers read `attributes.responsive[<path>][<breakpoint>]` for
 * a fixed set of exact paths (`BlockSupports::RESPONSIVE_CSS_PROPERTY_MAP`
 * in Blade, `RESPONSIVE_CSS_PROPERTY_MAP` in the React and Vue
 * renderers). The editor's responsive wrapper routes whatever leaf a
 * native panel changed, and that leaf can sit deeper than one of those
 * paths (`style.spacing.blockGap.left`, when the base gap is already
 * per-axis) or shallower (`style.spacing`, when the block had no spacing
 * yet). This module is the editor's copy of the renderers' path list,
 * plus the helpers that move a routed leaf, or a key already saved under
 * a legacy path, onto the path the renderers read.
 *
 * Pure data manipulation, shared by the write path and the canvas read
 * overlay so both agree with the front end.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.2
 */

import { isPlainObject, readPath, setPath } from './attribute-paths'

/**
 * Path-keyed breakpoint overrides, as stored in `attributes.responsive`.
 *
 * @since 1.15.2
 */
export type ResponsiveOverridesByPath = Record<string, Record<string, unknown>>

/**
 * Every path the generic responsive emitter reads. Mirrors the keys of
 * `BlockSupports::RESPONSIVE_CSS_PROPERTY_MAP` and the React / Vue
 * `RESPONSIVE_CSS_PROPERTY_MAP`; keep the three in step.
 *
 * @since 1.15.2
 */
export const RESPONSIVE_PROPERTY_PATHS: readonly string[] = [
    'style.spacing.padding',
    'style.spacing.margin',
    'style.spacing.blockGap',
    'style.border.radius',
    'style.border.width',
    'style.border.style',
    'style.border.color',
    'style.typography.fontSize',
    'fontSize',
    'style.color.text',
    'textColor',
    'style.color.background',
    'backgroundColor',
    'style.dimensions.minHeight',
    'height',
    'minHeight',
]

/**
 * The keys of a property whose value may be a per-side object, in the
 * order the renderers emit them. Mirrors `BlockSupports::RESPONSIVE_OBJECT_SIDES`.
 * A per-axis gap is `{top, left}`; a per-corner radius uses the
 * Gutenberg corner names.
 *
 * @since 1.15.2
 */
export const RESPONSIVE_PROPERTY_SIDES: Readonly<Record<string, readonly string[]>> = {
    'style.spacing.padding': [ 'top', 'right', 'bottom', 'left' ],
    'style.spacing.margin': [ 'top', 'right', 'bottom', 'left' ],
    'style.spacing.blockGap': [ 'top', 'left' ],
    'style.border.radius': [ 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ],
}

const PROPERTY_PATH_SET = new Set( RESPONSIVE_PROPERTY_PATHS )

/**
 * One write against a property path: the whole breakpoint value, or
 * (with `side`) one side of a per-side value.
 *
 * @since 1.15.2
 */
export interface ResponsivePropertyWrite {
    path: string
    side?: string
    value: unknown
    /** Only clear the path where it is already stored. */
    clearOnly?: boolean
}

function isFilled( value: unknown ): boolean {
    return null !== value && undefined !== value && '' !== value
}

/**
 * The property path and side a path one side deeper than a per-side
 * property names (`style.spacing.blockGap.left` → gap, `left`), or null.
 *
 * @since 1.15.2
 */
export function sideOfPropertyPath( path: string ): { path: string; side: string } | null {
    for ( const [ propertyPath, sides ] of Object.entries( RESPONSIVE_PROPERTY_SIDES ) ) {
        if ( ! path.startsWith( propertyPath + '.' ) ) {
            continue
        }

        const side = path.slice( propertyPath.length + 1 )

        return sides.includes( side ) ? { path: propertyPath, side } : null
    }

    return null
}

/**
 * The property paths nested under a shallower path (`style.spacing` →
 * padding, margin, blockGap).
 *
 * @since 1.15.2
 */
export function propertyPathsUnder( path: string ): string[] {
    return RESPONSIVE_PROPERTY_PATHS.filter( ( propertyPath ) => propertyPath.startsWith( path + '.' ) )
}

/**
 * A scalar spread over every side of a per-side property, so one side
 * can be replaced without losing the others.
 */
function expandScalar( path: string, value: unknown ): Record<string, unknown> {
    const sides = RESPONSIVE_PROPERTY_SIDES[ path ]

    if ( ! sides || ! isFilled( value ) || ( 'string' !== typeof value && 'number' !== typeof value ) ) {
        return {}
    }

    return Object.fromEntries( sides.map( ( side ) => [ side, value ] ) )
}

/**
 * `existing` with one side set, or removed when `value` is empty.
 * Returns `undefined` once no side is left.
 */
function withSide( path: string, existing: unknown, side: string, value: unknown ): Record<string, unknown> | undefined {
    const next = isPlainObject( existing ) ? { ...existing } : expandScalar( path, existing )

    if ( isFilled( value ) ) {
        next[ side ] = value
    } else {
        delete next[ side ]
    }

    return 0 === Object.keys( next ).length ? undefined : next
}

/**
 * Lay a later value over an earlier one for the same property: objects
 * merge side by side, a scalar replaces.
 */
function mergeValue( path: string, existing: unknown, value: unknown ): unknown {
    if ( ! isPlainObject( value ) ) {
        return value
    }

    const base = isPlainObject( existing ) ? existing : expandScalar( path, existing )

    return { ...base, ...value }
}

/**
 * Turn one routed leaf into writes against the property paths (#918).
 *
 * - A property path writes as-is.
 * - A path one side deeper than a per-side property writes that side.
 * - A shallower path holding an object is split into its children, so
 *   `style.spacing = {padding, blockGap}` writes each property; cleared
 *   to nothing, it clears every property beneath it.
 * - Anything else keeps its own path, as before.
 *
 * @since 1.15.2
 */
export function propertyWritesFor( path: string, value: unknown ): ResponsivePropertyWrite[] {
    if ( PROPERTY_PATH_SET.has( path ) ) {
        return [ { path, value } ]
    }

    const sided = sideOfPropertyPath( path )

    if ( sided ) {
        return [ { ...sided, value } ]
    }

    const nested = propertyPathsUnder( path )

    if ( 0 === nested.length ) {
        return [ { path, value } ]
    }

    if ( isPlainObject( value ) ) {
        return Object.entries( value ).flatMap( ( [ key, child ] ) => propertyWritesFor( path + '.' + key, child ) )
    }

    if ( null === value || undefined === value ) {
        return nested.map( ( propertyPath ) => ( { path: propertyPath, value: undefined, clearOnly: true } ) )
    }

    return [ { path, value } ]
}

/**
 * Apply property writes for one breakpoint, returning the touched paths
 * with their full per-breakpoint maps. `stored` is the (folded) bag the
 * writes build on.
 *
 * @since 1.15.2
 */
export function applyPropertyWrites(
    writes: ResponsivePropertyWrite[],
    stored: ResponsiveOverridesByPath | null | undefined,
    breakpoint: string,
): ResponsiveOverridesByPath {
    const patch: ResponsiveOverridesByPath = {}

    for ( const write of writes ) {
        const current = patch[ write.path ] ?? stored?.[ write.path ]

        if ( write.clearOnly && ( ! current || ! ( breakpoint in current ) ) ) {
            continue
        }

        const value = undefined === write.side
            ? write.value
            : withSide( write.path, current?.[ breakpoint ], write.side, write.value )

        patch[ write.path ] = { ...( current ?? {} ), [ breakpoint ]: value }
    }

    return patch
}

/**
 * Fold overrides saved under a legacy path into the property path the
 * renderers read (#918), the same way the renderers' `compileResponsive`
 * does: a key one side deeper than a per-side property
 * (`style.spacing.blockGap.left`) and a shallower key holding an object
 * (`style.spacing`) both contribute to the property's per-breakpoint
 * value, later keys over earlier ones. A breakpoint the property path
 * already sets keeps its own value. Whatever a shallow key holds beyond
 * the property paths stays under that key.
 *
 * Returns the bag untouched when nothing needs folding.
 *
 * @since 1.15.2
 */
export function foldLegacyResponsivePaths<T extends ResponsiveOverridesByPath | null | undefined>( responsive: T ): T {
    if ( ! isPlainObject( responsive ) ) {
        return responsive
    }

    const bag = responsive as ResponsiveOverridesByPath

    const isLegacy = ( key: string ): boolean => ! PROPERTY_PATH_SET.has( key )
        && isPlainObject( bag[ key ] )
        && ( null !== sideOfPropertyPath( key ) || 0 !== propertyPathsUnder( key ).length )

    if ( ! Object.keys( bag ).some( isLegacy ) ) {
        return responsive
    }

    const out: ResponsiveOverridesByPath = {}
    const legacy = new Map<string, Record<string, unknown>>()

    const contribute = ( path: string, breakpoint: string, merge: ( existing: unknown ) => unknown ): void => {
        const byBreakpoint = legacy.get( path ) ?? {}

        byBreakpoint[ breakpoint ] = merge( byBreakpoint[ breakpoint ] )
        legacy.set( path, byBreakpoint )
    }

    for ( const [ key, overrides ] of Object.entries( bag ) ) {
        if ( ! isLegacy( key ) ) {
            out[ key ] = overrides
            continue
        }

        const sided = sideOfPropertyPath( key )

        if ( sided ) {
            for ( const [ breakpoint, value ] of Object.entries( overrides ) ) {
                if ( isFilled( value ) && [ 'string', 'number', 'boolean' ].includes( typeof value ) ) {
                    contribute( sided.path, breakpoint, ( existing ) => withSide( sided.path, existing, sided.side, value ) )
                }
            }

            continue
        }

        const remainder: Record<string, unknown> = {}

        for ( const [ breakpoint, value ] of Object.entries( overrides ) ) {
            if ( ! isPlainObject( value ) ) {
                continue
            }

            let rest: Record<string, unknown> = value

            for ( const propertyPath of propertyPathsUnder( key ) ) {
                const subPath = propertyPath.slice( key.length + 1 )
                const nested  = readPath( value, subPath )

                if ( null === nested || undefined === nested ) {
                    continue
                }

                contribute( propertyPath, breakpoint, ( existing ) => mergeValue( propertyPath, existing, nested ) )
                rest = setPath( rest, subPath, undefined )
            }

            if ( 0 !== Object.keys( rest ).length ) {
                remainder[ breakpoint ] = rest
            }
        }

        if ( 0 !== Object.keys( remainder ).length ) {
            out[ key ] = remainder
        }
    }

    for ( const [ path, byBreakpoint ] of legacy ) {
        const canonical = isPlainObject( out[ path ] ) ? out[ path ] : {}
        const merged: Record<string, unknown> = { ...canonical }

        for ( const [ breakpoint, value ] of Object.entries( byBreakpoint ) ) {
            if ( undefined !== value && ( null === canonical[ breakpoint ] || undefined === canonical[ breakpoint ] ) ) {
                merged[ breakpoint ] = value
            }
        }

        out[ path ] = merged
    }

    return out as T
}

/**
 * A per-side property's value at a breakpoint, combined along the
 * cascade the way the front end's media rules combine (#918): each side
 * comes from the narrowest breakpoint that sets it, and a scalar fills
 * every side a narrower breakpoint left open. Returns `undefined` when
 * nothing along the cascade sets the property.
 *
 * @since 1.15.2
 */
export function cascadePropertySides(
    path: string,
    overrides: Record<string, unknown>,
    cascade: readonly string[],
): unknown {
    const sides = RESPONSIVE_PROPERTY_SIDES[ path ] ?? []
    let combined: Record<string, unknown> | null = null

    for ( const breakpoint of cascade ) {
        if ( ! ( breakpoint in overrides ) ) {
            continue
        }

        const value = overrides[ breakpoint ]

        if ( null === value || undefined === value ) {
            continue
        }

        if ( ! isPlainObject( value ) ) {
            if ( null === combined ) {
                return value
            }

            for ( const side of sides ) {
                if ( ! ( side in combined ) ) {
                    combined[ side ] = value
                }
            }

            return combined
        }

        combined = combined ?? {}

        for ( const [ side, sideValue ] of Object.entries( value ) ) {
            if ( ! ( side in combined ) && isFilled( sideValue ) ) {
                combined[ side ] = sideValue
            }
        }

        if ( sides.every( ( side ) => side in ( combined as Record<string, unknown> ) ) ) {
            return combined
        }
    }

    return null === combined || 0 === Object.keys( combined ).length ? undefined : combined
}

/**
 * Fill the sides a per-side override leaves out from a scalar base value
 * (#918): a base `padding: 2rem` with a Tablet `padding-left` override
 * renders `2rem` on the other three sides at Tablet.
 *
 * @since 1.15.2
 */
export function fillSidesFromBase( path: string, value: unknown, baseValue: unknown ): unknown {
    if ( ! isPlainObject( value ) ) {
        return value
    }

    return { ...expandScalar( path, baseValue ), ...value }
}
