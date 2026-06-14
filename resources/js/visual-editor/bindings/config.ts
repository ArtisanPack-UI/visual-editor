/**
 * Module-level configuration for the bindings inspector (#504).
 *
 * `registerBindingsPanel(apiBase)` calls into this from the editor
 * bootstrap so the HOC and its hooks can read the API base without each
 * block having to thread props through the WordPress filter chain.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import type { BindingsApiConfig } from './types';

const DEFAULT_API_BASE = '/visual-editor/api';

let config: BindingsApiConfig = { apiBase: DEFAULT_API_BASE };

export function setBindingsApiConfig( next: Partial<BindingsApiConfig> ): void {
	config = {
		...config,
		...next,
		apiBase: next.apiBase?.replace( /\/$/, '' ) ?? config.apiBase,
	};
}

export function getBindingsApiConfig(): BindingsApiConfig {
	return config;
}

export function resetBindingsApiConfig(): void {
	config = { apiBase: DEFAULT_API_BASE };
}
