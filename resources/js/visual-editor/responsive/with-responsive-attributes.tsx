/**
 * `editor.BlockEdit` HOC — invisibly route attribute reads and writes
 * through the active breakpoint (#487).
 *
 * Every block that declares `supports.artisanpackResponsive` is
 * wrapped. The wrapper does two things, both transparent to the
 * native Gutenberg panels:
 *
 *  1. **Reads.** Builds a view where the attributes the panels see
 *     are the base values overlaid with anything stored under
 *     `attributes.responsive.<path>.<activeBreakpoint>`. So when the
 *     editor switches the top-bar viewport to `md`, the spacing
 *     panel's BoxControl shows the `md` value instead of the base.
 *
 *  2. **Writes.** When the active breakpoint is `base`, writes fall
 *     through to the normal `setAttributes` (touching
 *     `attributes.style.spacing.padding` as today). When the active
 *     breakpoint is non-`base`, the writes are diffed against the
 *     pre-merge attributes; any leaf that landed under one of the
 *     opt-in roots (e.g. `spacing`, `align`, `columns.count`) is
 *     stored under `attributes.responsive.<path>.<activeBreakpoint>`
 *     instead of mutating the base. Leaves outside the opt-in roots
 *     fall through to the base, so non-responsive attributes (e.g.
 *     `verticalAlignment`) still write directly.
 *
 *  Editors see zero new UI inside the panels. They pick a viewport
 *  in the top bar, edit padding/margin/border like always, and the
 *  value sticks to that viewport.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { hasBlockSupport, getBlockType } from '@wordpress/blocks'
import { createHigherOrderComponent } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { useCallback, useMemo, useSyncExternalStore } from 'react'
import type { ComponentType } from 'react'

import { getActiveBreakpoint, subscribeActiveBreakpoint } from './active-breakpoint'
import {
    deepMerge,
    diffPaths,
    isPlainObject,
    pathMatchesAnyRoot,
    setPath,
} from './attribute-paths'
import { type BreakpointRegistry, getResponsiveRegistry } from './registry'
import { BASE_KEY } from './types'

const FILTER_HOOK      = 'editor.BlockEdit'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/responsive-attributes'

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.responsive-attributes.registered',
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

interface ResponsiveSupports {
    attributes: string[]
}

type ResponsiveOverridesByPath = Record<string, Record<string, unknown>>

/**
 * Responsive paths that store one CSS property two ways: a preset slug
 * and a custom value (#887). Each maps to its twin.
 */
export const PAIRED_RESPONSIVE_PATHS: Readonly<Record<string, string>> = {
    fontSize: 'style.typography.fontSize',
    'style.typography.fontSize': 'fontSize',
    textColor: 'style.color.text',
    'style.color.text': 'textColor',
    backgroundColor: 'style.color.background',
    'style.color.background': 'backgroundColor',
}

/**
 * Stored at a breakpoint for one half of a {@link PAIRED_RESPONSIVE_PATHS}
 * pair when the other half is set there (#887). Unlike `null` ("inherit"),
 * it stops the cascade, so a narrower breakpoint's preset is not shown
 * alongside a wider breakpoint's custom value. The renderers emit nothing
 * for it, and never let it displace the twin's value.
 */
export const RESPONSIVE_TOMBSTONE = false

function isTombstone( path: string, value: unknown ): boolean {
    return RESPONSIVE_TOMBSTONE === value && path in PAIRED_RESPONSIVE_PATHS
}

function isOverrideValue( value: unknown ): boolean {
    return null !== value && undefined !== value && '' !== value && RESPONSIVE_TOMBSTONE !== value
}

/**
 * Add the tombstones a routed responsive patch implies (#887): a value
 * written for one half of a pair at the breakpoint tombstones the other
 * half there, and clearing one half drops the other half's stored
 * tombstone so the cascade resumes for both.
 */
export function withPairTombstones(
    patch: ResponsiveOverridesByPath,
    responsive: ResponsiveOverridesByPath | null | undefined,
    breakpoint: string,
): ResponsiveOverridesByPath {
    const out: ResponsiveOverridesByPath = { ...patch }

    for ( const [ path, twin ] of Object.entries( PAIRED_RESPONSIVE_PATHS ) ) {
        if ( ! ( path in patch ) ) {
            continue
        }

        if ( isOverrideValue( patch[ path ][ breakpoint ] ) ) {
            if ( ! isOverrideValue( patch[ twin ]?.[ breakpoint ] ) ) {
                out[ twin ] = {
                    ...( patch[ twin ] ?? responsive?.[ twin ] ?? {} ),
                    [ breakpoint ]: RESPONSIVE_TOMBSTONE,
                }
            }

            continue
        }

        const stored = responsive?.[ twin ]

        if ( ! ( twin in patch ) && stored && RESPONSIVE_TOMBSTONE === stored[ breakpoint ] ) {
            const rest = { ...stored }
            delete rest[ breakpoint ]
            out[ twin ] = rest
        }
    }

    return out
}

function getResponsiveRoots( name: string ): string[] | null {
    const blockType = getBlockType( name )

    if ( ! blockType ) {
        return null
    }

    const supports = hasBlockSupport( blockType, 'artisanpackResponsive', false )
        ? ( blockType.supports as Record<string, unknown> ).artisanpackResponsive
        : null

    if ( ! supports || 'object' !== typeof supports ) {
        return null
    }

    const roots = ( supports as ResponsiveSupports ).attributes

    if ( ! Array.isArray( roots ) || 0 === roots.length ) {
        return null
    }

    return roots
}

/**
 * Build the overlay object that, when deep-merged into the base
 * attributes, produces the read view for the active breakpoint.
 *
 * Mirrors the PHP `ResponsiveValueResolver` cascade: every responsive
 * path takes the first defined value along the registry cascade for the
 * active breakpoint — for a device key that is the key itself, then the
 * larger devices (Mobile inherits from Tablet), then any legacy keys
 * that also apply at its preview width (#820). A tombstone on a paired
 * path stops the cascade and clears that path from the read view (#887).
 */
export function buildOverlay(
    responsive: ResponsiveOverridesByPath | null | undefined,
    activeBreakpoint: string,
    registry: BreakpointRegistry,
): Record<string, unknown> {
    if ( ! responsive || BASE_KEY === activeBreakpoint || ! registry.has( activeBreakpoint ) ) {
        return {}
    }

    // `base` lives in the plain attributes, not the overlay.
    const cascade = registry.cascade( activeBreakpoint ).filter( ( key ) => BASE_KEY !== key )
    const overlay: Record<string, unknown> = {}

    for ( const path of Object.keys( responsive ) ) {
        const overridesForPath = responsive[ path ]

        if ( ! overridesForPath || 'object' !== typeof overridesForPath ) {
            continue
        }

        for ( const bp of cascade ) {
            if ( bp in overridesForPath ) {
                const value = overridesForPath[ bp ]

                if ( null === value || undefined === value ) {
                    continue
                }

                overlay[ '__placeholder' ] = true
                writeInto( overlay, path, isTombstone( path, value ) ? undefined : value )
                break
            }
        }
    }

    delete overlay[ '__placeholder' ]
    return overlay
}

/**
 * Split a changed leaf into the parts a responsive write routes and the
 * parts that fall through to the base. A leaf under an opt-in root routes
 * whole; a plain object outside every root is walked until a sub-path
 * matches one (#887). Without the walk, a block with no base `style` saw
 * `style.typography.fontSize` arrive as one `style` leaf, which matches
 * no root, so a breakpoint font size or colour was written to the base.
 */
function splitLeaf(
    path: string,
    value: unknown,
    roots: string[],
    out: { responsive: Array<{ path: string; value: unknown }>; base: Array<{ path: string; value: unknown }> },
): void {
    if ( pathMatchesAnyRoot( path, roots ) ) {
        out.responsive.push( { path, value } )
        return
    }

    if ( isPlainObject( value ) && 0 !== Object.keys( value ).length ) {
        for ( const [ key, child ] of Object.entries( value ) ) {
            splitLeaf( path + '.' + key, child, roots, out )
        }

        return
    }

    out.base.push( { path, value } )
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

export const withResponsiveAttributes = createHigherOrderComponent(
    ( BlockEdit: ComponentType<BlockEditProps> ) => {
        function ResponsiveBlockEdit( props: BlockEditProps ): JSX.Element {
            const { name, attributes, setAttributes } = props

            const roots = useMemo( () => getResponsiveRoots( name ), [ name ] )

            const activeBreakpoint = useSyncExternalStore(
                subscribeActiveBreakpoint,
                getActiveBreakpoint,
                getActiveBreakpoint,
            )

            const responsive = ( attributes.responsive as ResponsiveOverridesByPath | null | undefined ) ?? null

            const mergedAttributes = useMemo( () => {
                if ( ! roots || BASE_KEY === activeBreakpoint ) {
                    return attributes
                }

                const overlay = buildOverlay( responsive, activeBreakpoint, getResponsiveRegistry() )

                if ( 0 === Object.keys( overlay ).length ) {
                    return attributes
                }

                return deepMerge( attributes, overlay )
            }, [ attributes, responsive, activeBreakpoint, roots ] )

            const wrappedSetAttributes = useCallback(
                ( updates: Record<string, unknown> ): void => {
                    if ( ! roots || BASE_KEY === activeBreakpoint ) {
                        setAttributes( updates )
                        return
                    }

                    // Diff what the native panel just produced against
                    // the read view it was looking at.
                    const changedLeaves = diffPaths( updates, mergedAttributes )

                    if ( 0 === changedLeaves.length ) {
                        return
                    }

                    const baseUpdates: Record<string, unknown> = {}
                    let responsivePatch: ResponsiveOverridesByPath | null = null
                    const routed = { responsive: [] as Array<{ path: string; value: unknown }>, base: [] as Array<{ path: string; value: unknown }> }

                    for ( const { path, value } of changedLeaves ) {
                        splitLeaf( path, value, roots, routed )
                    }

                    for ( const { path, value } of routed.responsive ) {
                        responsivePatch = responsivePatch ?? {}
                        responsivePatch[ path ] = {
                            ...( responsive?.[ path ] ?? {} ),
                            [ activeBreakpoint ]: value,
                        }
                    }

                    for ( const { path, value } of routed.base ) {
                        Object.assign(
                            baseUpdates,
                            setPath( baseUpdates, path, value ),
                        )
                    }

                    const finalUpdates: Record<string, unknown> = { ...baseUpdates }

                    if ( responsivePatch ) {
                        finalUpdates.responsive = {
                            ...( responsive ?? {} ),
                            ...withPairTombstones( responsivePatch, responsive, activeBreakpoint ),
                        }
                    }

                    setAttributes( finalUpdates )
                },
                [ activeBreakpoint, mergedAttributes, responsive, roots, setAttributes ],
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

        ResponsiveBlockEdit.displayName = 'ResponsiveBlockEdit'

        return ResponsiveBlockEdit
    },
    'withResponsiveAttributes',
)

/**
 * Idempotent — safe to call from both the post-editor and site-editor
 * bootstrap paths. Late callers see the sentinel and no-op.
 */
export function registerResponsiveAttributesFilter(): void {
    const host = globalThis as unknown as GlobalSentinelHost

    if ( host[ REGISTERED_KEY ] ) {
        return
    }

    addFilter( FILTER_HOOK, FILTER_NAMESPACE, withResponsiveAttributes )
    host[ REGISTERED_KEY ] = true
}
