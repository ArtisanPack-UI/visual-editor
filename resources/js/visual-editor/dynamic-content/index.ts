/**
 * Dynamic Content editor integration entrypoint.
 *
 * Public export surface — call `registerDynamicContent()` once from
 * the editor bootstrap to wire up autocomplete, Token Inserter, and
 * the binding panels. Editor live-preview substitution shows
 * resolved values in the canvas so authors don't have to save to
 * verify.
 *
 * @since 1.4.0
 */

import { registerDynamicContentAutocomplete } from './autocomplete';
import { registerDynamicContentToolbarButton } from './toolbar-button';
import { registerImageBindingPanel } from './image-binding-panel';
import { registerButtonBindingPanel } from './button-binding-panel';
import { injectDynamicContentStyles } from './inject-styles';
import { installEditorLivePreview } from './editor-live-preview';

export {
    DC_API_BASE,
    fetchSources,
    flattenTokens,
    resolveTokens,
    invalidateTokenCache,
    type DynamicContentSource,
    type DynamicContentField,
} from './api';
export { default as TokenInserterModal } from './token-inserter-modal';
export {
    registerDynamicContentAutocomplete,
    registerDynamicContentToolbarButton,
    registerImageBindingPanel,
    registerButtonBindingPanel,
};

let registered = false;

/**
 * Idempotently register every Dynamic Content editor integration.
 *
 * @since 1.4.0
 */
export function registerDynamicContent(): void {
    if (registered) return;
    registered = true;

    injectDynamicContentStyles();
    registerDynamicContentAutocomplete();
    registerDynamicContentToolbarButton();
    registerImageBindingPanel();
    registerButtonBindingPanel();
    installEditorLivePreview();
}
