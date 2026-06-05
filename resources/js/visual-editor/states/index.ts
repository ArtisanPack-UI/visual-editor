/**
 * Public entry point for the state design tools (#488).
 *
 * Host apps consume these via `@artisanpack-ui/visual-editor` once
 * the editor library build re-exports them.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

export {
	getActiveState,
	setActiveState,
	subscribeActiveState,
	getPreviewState,
	setPreviewState,
	subscribePreviewState,
	setActiveBlock,
	getActiveBlock,
	resetStateStores,
} from './active-state'

export {
	StateRegistry,
	DEFAULT_STATES,
	registryFromSnapshot,
} from './registry'

export {
	resolveStateValue,
	isStatefulAttribute,
	distinctStateOverrides,
} from './resolver'

export {
	promote,
	demote,
	clearOverride,
} from './migrator'

export {
	useStateValue,
	resolveAt,
} from './useStateValue'

export { StateSwitcher } from './StateSwitcher'
export type { StateSwitcherProps } from './StateSwitcher'

export { StateControl } from './StateControl'
export type { StateControlProps } from './StateControl'

export { PreviewStateToggle } from './PreviewStateToggle'
export type { PreviewStateToggleProps } from './PreviewStateToggle'

export { flushBeforeSave } from './StateInspectorSync'

export { BASE_KEY } from './types'
export type { StateDefinition, StateKey, StateRegistrySnapshot, StatefulAttribute } from './types'

export { emitStateCss, DEFAULT_TRANSITION } from './css-emitter'
export type { StatesByPath } from './css-emitter'
