/**
 * Public entry point for the responsive design tools (#487).
 *
 * Host apps consume these via `@artisanpack-ui/visual-editor` once
 * the editor library build re-exports them.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

export {
	getActiveBreakpoint,
	setActiveBreakpoint,
	subscribeActiveBreakpoint,
	resetActiveBreakpoint,
} from './active-breakpoint'

export {
	BreakpointRegistry,
	TAILWIND_V4_DEFAULTS,
	registryFromSnapshot,
} from './registry'

export {
	resolveResponsiveValue,
	isResponsiveAttribute,
	distinctOverrides,
} from './resolver'

export {
	promote,
	demote,
	clearOverride,
} from './migrator'

export {
	useResponsiveValue,
	resolveAt,
} from './useResponsiveValue'

export { ViewportSwitcher } from './ViewportSwitcher'
export type { ViewportSwitcherProps } from './ViewportSwitcher'

export { ResponsiveControl } from './ResponsiveControl'
export type { ResponsiveControlProps } from './ResponsiveControl'

export { InspectorScopeChip } from './InspectorScopeChip'

// `withResponsiveAttributes` + the filter registrars are intentionally
// not re-exported here. They pull in `@wordpress/blocks`, which trips
// JSON-import-attribute requirements when host bundlers (or vitest)
// walk this barrel transitively. The editor bootstrap imports them
// directly from `./with-responsive-attributes` and `./register-attribute`.

export { BASE_KEY } from './types'
export type { Breakpoint, BreakpointKey, BreakpointRegistrySnapshot, ResponsiveAttribute } from './types'
