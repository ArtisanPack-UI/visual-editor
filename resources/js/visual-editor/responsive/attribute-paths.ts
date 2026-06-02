/**
 * Path-keyed attribute read/write helpers (#487).
 *
 * The responsive HOC mediates writes from the native Gutenberg
 * panels by intercepting `setAttributes({style: {...}})` calls,
 * comparing the resulting attributes tree against what was there
 * before, and routing the diff into `attributes.responsive` when the
 * active breakpoint is not `base`.
 *
 * This file gives us the path arithmetic those steps depend on:
 *  - Read a value at a dotted path (`style.spacing.padding`).
 *  - Set a value at a dotted path (immutable, returns a new tree).
 *  - Diff two attribute trees and return the changed leaf paths.
 *  - Decide whether a path falls under one of the opt-in roots
 *    (e.g. `["spacing", "align"]` matches `style.spacing.padding` and
 *    `align` but not `verticalAlignment`).
 *
 * Pure data manipulation. No React, no Gutenberg, fully testable in
 * isolation.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

/**
 * Reads a nested value by dotted path. Returns `undefined` when any
 * segment misses.
 */
export function readPath<T = unknown>( source: unknown, path: string ): T | undefined {
    if ( null === source || 'object' !== typeof source ) {
        return undefined
    }

    const segments = path.split( '.' )
    let cursor: unknown = source

    for ( const segment of segments ) {
        if ( null === cursor || 'object' !== typeof cursor || Array.isArray( cursor ) ) {
            return undefined
        }

        cursor = ( cursor as Record<string, unknown> )[ segment ]
    }

    return cursor as T | undefined
}

/**
 * Defense-in-depth guard against prototype-pollution via crafted
 * dotted paths or object keys. Internal callers pass Gutenberg
 * attribute trees that never contain these segments, but the helpers
 * are exported reusable utilities — a single guard keeps the public
 * surface safe even if a future caller routes externally-influenced
 * keys through them.
 */
const FORBIDDEN_KEYS = new Set( [ '__proto__', 'constructor', 'prototype' ] )

/**
 * Returns a new tree with `path` set to `value`. Intermediate objects
 * are created as plain `{}`; arrays are NOT created. Used both for
 * applying responsive overrides back into the merged read view and
 * for shimming a write under `attributes.responsive.<path>.<bp>`.
 *
 * Setting `undefined` deletes the leaf and prunes empty parents.
 *
 * Path segments matching `__proto__`, `constructor`, or `prototype`
 * are silently dropped — see `FORBIDDEN_KEYS` for rationale.
 */
export function setPath<T extends Record<string, unknown>>(
    source: T | undefined | null,
    path: string,
    value: unknown,
): T {
    const segments = path.split( '.' )
    const root     = source ? { ...source } : ( {} as T )

    let cursor: Record<string, unknown> = root as Record<string, unknown>

    for ( let i = 0; i < segments.length - 1; i++ ) {
        const segment = segments[ i ]

        if ( FORBIDDEN_KEYS.has( segment ) ) {
            return root
        }

        const existing = cursor[ segment ]

        const next: Record<string, unknown> =
            existing && 'object' === typeof existing && ! Array.isArray( existing )
                ? { ...( existing as Record<string, unknown> ) }
                : {}

        cursor[ segment ] = next
        cursor            = next
    }

    const leaf = segments[ segments.length - 1 ]

    if ( FORBIDDEN_KEYS.has( leaf ) ) {
        return root
    }

    if ( undefined === value ) {
        delete cursor[ leaf ]
    } else {
        cursor[ leaf ] = value
    }

    return pruneEmpty( root ) as T
}

/**
 * Recursively drop empty `{}` branches so an `attributes.style` that
 * only had `{spacing: {}}` after a delete collapses back to absent.
 * Operates on a copy; never mutates the input.
 */
function pruneEmpty<T>( node: T ): T {
    if ( null === node || 'object' !== typeof node || Array.isArray( node ) ) {
        return node
    }

    const result: Record<string, unknown> = {}

    for ( const [ key, value ] of Object.entries( node as Record<string, unknown> ) ) {
        if ( value && 'object' === typeof value && ! Array.isArray( value ) ) {
            const cleaned = pruneEmpty( value )

            if ( 0 !== Object.keys( cleaned as Record<string, unknown> ).length ) {
                result[ key ] = cleaned
            }
        } else if ( undefined !== value ) {
            result[ key ] = value
        }
    }

    return result as T
}

/**
 * Walks an updates object emitted by a native panel and yields every
 * changed leaf path (relative to the attributes root). Only deep
 * objects are recursed; arrays + scalars are leaves.
 */
export function diffPaths(
    updates: Record<string, unknown>,
    previous: Record<string, unknown>,
    prefix: string = '',
): Array<{ path: string; value: unknown }> {
    const out: Array<{ path: string; value: unknown }> = []

    for ( const [ key, value ] of Object.entries( updates ) ) {
        const path = '' === prefix ? key : prefix + '.' + key

        const isObjectValue =
            null !== value && 'object' === typeof value && ! Array.isArray( value )

        const prev = previous[ key ]

        if ( isObjectValue && prev && 'object' === typeof prev && ! Array.isArray( prev ) ) {
            out.push(
                ...diffPaths(
                    value as Record<string, unknown>,
                    prev as Record<string, unknown>,
                    path,
                ),
            )
            continue
        }

        if ( value === prev ) {
            continue
        }

        out.push( { path, value } )
    }

    return out
}

/**
 * Returns true when the given dotted path falls under any of the
 * opt-in roots. A root of `spacing` matches `style.spacing.padding`,
 * `spacing.blockGap`, and `spacing` itself; it does NOT match
 * `spacingScale` or `lineSpacing`.
 *
 * Match rules:
 *  - exact equality (`path === root`)
 *  - prefix with a `.` separator (`path` starts with `root + "."`)
 *  - the root appears as a complete segment anywhere in the path
 *    (`style.spacing.padding` matches root `spacing` because
 *    `.spacing.` is a segment boundary)
 */
export function pathMatchesAnyRoot( path: string, roots: string[] ): boolean {
    for ( const root of roots ) {
        if ( path === root ) {
            return true
        }

        if ( path.startsWith( root + '.' ) ) {
            return true
        }

        if ( path.includes( '.' + root + '.' ) || path.endsWith( '.' + root ) ) {
            return true
        }
    }

    return false
}

/**
 * Recursively clone a plain JSON value. Arrays are cloned element-wise;
 * plain objects are cloned key-by-key. Non-object scalars pass through
 * unchanged.
 */
export function deepClone<T>( value: T ): T {
    if ( null === value || undefined === value || 'object' !== typeof value ) {
        return value
    }

    if ( Array.isArray( value ) ) {
        return value.map( ( item ) => deepClone( item ) ) as unknown as T
    }

    const result: Record<string, unknown> = {}
    for ( const key of Object.keys( value as Record<string, unknown> ) ) {
        result[ key ] = deepClone( ( value as Record<string, unknown> )[ key ] )
    }

    return result as unknown as T
}

/**
 * Build a top-level patch where each touched key carries a full
 * subtree (deep-cloned from `attributes`) with the supplied leaves
 * overwritten. This ensures that `updateBlockAttributes` (which does
 * a shallow merge at the top level) does NOT clobber siblings of the
 * changed leaves.
 */
export function buildTopLevelPatch(
    attributes: Record<string, unknown>,
    entriesByTopKey: Map<string, Array<{ path: string; value: unknown }>>,
): Record<string, unknown> {
    const patch: Record<string, unknown> = {}

    for ( const [ topKey, entries ] of entriesByTopKey ) {
        const current = attributes[ topKey ]
        let topValue: unknown =
            current && 'object' === typeof current && ! Array.isArray( current )
                ? deepClone( current )
                : current

        for ( const { path, value } of entries ) {
            const segments = path.split( '.' )

            if ( 1 === segments.length ) {
                topValue = value
                continue
            }

            if ( ! topValue || 'object' !== typeof topValue || Array.isArray( topValue ) ) {
                topValue = {}
            }

            let cursor = topValue as Record<string, unknown>
            for ( let i = 1; i < segments.length - 1; i++ ) {
                const segment  = segments[ i ]
                const existing = cursor[ segment ]
                if ( ! existing || 'object' !== typeof existing || Array.isArray( existing ) ) {
                    cursor[ segment ] = {}
                }
                cursor = cursor[ segment ] as Record<string, unknown>
            }

            cursor[ segments[ segments.length - 1 ] ] = value
        }

        patch[ topKey ] = topValue
    }

    return patch
}

/**
 * Deep-merge two plain objects. Right wins on scalar collisions;
 * nested objects merge recursively. Arrays are replaced (not merged).
 * Used to overlay the active breakpoint's overrides on top of base
 * attributes for the read view.
 */
export function deepMerge<T extends Record<string, unknown>>(
    base: T,
    overlay: Record<string, unknown> | null | undefined,
): T {
    if ( ! overlay ) {
        return base
    }

    const result: Record<string, unknown> = { ...base }

    for ( const [ key, value ] of Object.entries( overlay ) ) {
        if ( FORBIDDEN_KEYS.has( key ) ) {
            continue
        }

        const baseValue = result[ key ]

        if (
            value &&
            'object' === typeof value &&
            ! Array.isArray( value ) &&
            baseValue &&
            'object' === typeof baseValue &&
            ! Array.isArray( baseValue )
        ) {
            result[ key ] = deepMerge(
                baseValue as Record<string, unknown>,
                value as Record<string, unknown>,
            )
            continue
        }

        result[ key ] = value
    }

    return result as T
}
