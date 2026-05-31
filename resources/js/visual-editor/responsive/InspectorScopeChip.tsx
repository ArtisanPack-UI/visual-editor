/**
 * Inspector scope chip (#487).
 *
 * Small banner rendered at the top of the inspector's Block tab. When
 * the editor's active breakpoint is not `base`, it tells the editor
 * which viewport their next style edit will apply to — otherwise the
 * inspector controls would look like they were silently changing the
 * default value when they're actually scoped.
 *
 * Hidden entirely at `base` so the inspector stays uncluttered
 * during normal editing.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import { getActiveBreakpoint, setActiveBreakpoint, subscribeActiveBreakpoint } from './active-breakpoint'
import { BASE_KEY } from './types'

import './inspector-scope-chip.css'

// See ViewportSwitcher.tsx for why these are size-keyed rather than
// device-named.
const LABELS: Record<string, string> = {
    [ BASE_KEY ]: 'All sizes',
    sm:          'sm and up',
    md:          'md and up',
    lg:          'lg and up',
    xl:          'xl and up',
    '2xl':       '2xl and up',
}

export function InspectorScopeChip(): JSX.Element | null {
    const active = useSyncExternalStore(
        subscribeActiveBreakpoint,
        getActiveBreakpoint,
        getActiveBreakpoint,
    )

    if ( BASE_KEY === active ) {
        return null
    }

    const label = LABELS[ active ] ?? active

    return (
        <div
            className="ap-visual-editor-inspector-scope-chip"
            role="status"
            aria-live="polite"
        >
            <span className="ap-visual-editor-inspector-scope-chip__label">
                Editing at <strong>{ label }</strong>
                <span className="ap-visual-editor-inspector-scope-chip__key">
                    ({ active })
                </span>
            </span>
            <button
                type="button"
                className="ap-visual-editor-inspector-scope-chip__reset"
                onClick={ () => setActiveBreakpoint( BASE_KEY ) }
            >
                Switch to All sizes
            </button>
        </div>
    )
}
