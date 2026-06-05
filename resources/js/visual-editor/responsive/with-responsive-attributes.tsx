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
    pathMatchesAnyRoot,
    setPath,
} from './attribute-paths'
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
 * path is walked, taking the largest defined value ≤ the active
 * breakpoint and using it.
 */
function buildOverlay(
    responsive: ResponsiveOverridesByPath | null | undefined,
    activeBreakpoint: string,
    breakpointOrder: string[],
): Record<string, unknown> {
    if ( ! responsive || BASE_KEY === activeBreakpoint ) {
        return {}
    }

    const activeIndex = breakpointOrder.indexOf( activeBreakpoint )

    if ( -1 === activeIndex ) {
        return {}
    }

    // Largest breakpoint at or below active, walking down.
    const cascade = breakpointOrder.slice( 0, activeIndex + 1 ).reverse()
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
                writeInto( overlay, path, value )
                break
            }
        }
    }

    delete overlay[ '__placeholder' ]
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

/**
 * Default breakpoint ordering — must match `TAILWIND_V4_DEFAULTS` in
 * registry.ts. Hard-coded here to keep this module independent of the
 * registry singleton (which won't be hydrated until the editor's
 * bootstrap snapshot lands; see plan §7.2).
 */
const DEFAULT_BREAKPOINT_ORDER = [ BASE_KEY, 'sm', 'md', 'lg', 'xl', '2xl' ]

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

                const overlay = buildOverlay( responsive, activeBreakpoint, DEFAULT_BREAKPOINT_ORDER )

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

                    for ( const { path, value } of changedLeaves ) {
                        if ( pathMatchesAnyRoot( path, roots ) ) {
                            responsivePatch = responsivePatch ?? {}
                            responsivePatch[ path ] = {
                                ...( responsive?.[ path ] ?? {} ),
                                [ activeBreakpoint ]: value,
                            }
                            continue
                        }

                        Object.assign(
                            baseUpdates,
                            setPath( baseUpdates, path, value ),
                        )
                    }

                    const finalUpdates: Record<string, unknown> = { ...baseUpdates }

                    if ( responsivePatch ) {
                        finalUpdates.responsive = {
                            ...( responsive ?? {} ),
                            ...responsivePatch,
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
