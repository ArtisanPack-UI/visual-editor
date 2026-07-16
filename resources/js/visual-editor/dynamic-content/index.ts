/**
 * Dynamic Content editor integration entrypoint.
 *
 * Public export surface — call `registerDynamicContent()` once from
 * the editor bootstrap to wire up autocomplete, chip decoration,
 * toolbar Token Inserter, and Image DC binding panel.
 *
 * @since 1.4.0
 */

import { registerDynamicContentAutocomplete } from './autocomplete';
import { registerDynamicContentChipFormat } from './token-chip-format';
import { registerDynamicContentToolbarButton } from './toolbar-button';
import { registerImageBindingPanel } from './image-binding-panel';
import { registerButtonBindingPanel } from './button-binding-panel';

import './dynamic-content.css';

export {
    DC_API_BASE,
    fetchSources,
    flattenTokens,
    resolveTokens,
    invalidateTokenCache,
    type DynamicContentSource,
    type DynamicContentField,
} from './api';
export { default as ArtisanPackLinkControl } from './link-control';
export { default as TokenInserterModal } from './token-inserter-modal';
export {
    registerDynamicContentAutocomplete,
    registerDynamicContentChipFormat,
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

    registerDynamicContentChipFormat();
    registerDynamicContentAutocomplete();
    registerDynamicContentToolbarButton();
    registerImageBindingPanel();
    registerButtonBindingPanel();
}
