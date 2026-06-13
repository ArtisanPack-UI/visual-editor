/**
 * Public entry point for the block-animations system (#489).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

export {
	AnimationRegistry,
	DEFAULT_ANIMATIONS,
	registryFromSnapshot,
} from './registry';

export {
	ANIMATION_FAMILIES,
	FAMILY_CONTINUOUS,
	FAMILY_ENTRANCE,
	FAMILY_HOVER,
} from './types';

export type {
	AnimationDefinition,
	AnimationFamily,
	AnimationRegistrySnapshot,
	AnimationsAttribute,
	ContinuousAttribute,
	CustomKeyframe,
	CustomKeyframeStop,
	EntranceAttribute,
	HoverAttribute,
	ReducedMotionPolicy,
} from './types';

export { AnimationPanel } from './AnimationPanel';
export type { AnimationPanelProps } from './AnimationPanel';

export { CustomKeyframeEditor } from './CustomKeyframeEditor';
export type { CustomKeyframeEditorProps } from './CustomKeyframeEditor';

export { bootstrap as bootstrapAnimationsRuntime } from './runtime';
