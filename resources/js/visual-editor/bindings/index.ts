/**
 * Block bindings public surface (#504).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

export { registerBindingsAttribute } from './register-attribute';
export {
	registerBindingsPanel,
	setBindingsResourceContext,
	withBindingsPanel,
} from './with-bindings-panel';
export type { RegisterBindingsPanelOptions } from './with-bindings-panel';
export { BindingsPanel } from './BindingsPanel';
export type {
	BindableAttribute,
	BindingsPanelProps,
} from './BindingsPanel';
export {
	getBindingsApiConfig,
	resetBindingsApiConfig,
	setBindingsApiConfig,
} from './config';
export {
	fetchBindingFields,
	fetchBindingSources,
	resolveBindings,
} from './api';
export { useResolvedBindings } from './use-resolved-bindings';
export {
	resetBindingSourcesCache,
	useBindingFields,
	useBindingSources,
} from './use-binding-sources';
export type {
	BindingDefinition,
	BindingFieldDefinition,
	BindingSourceSummary,
	BindingsApiConfig,
	BindingsMap,
	EmptyValuePolicy,
} from './types';
