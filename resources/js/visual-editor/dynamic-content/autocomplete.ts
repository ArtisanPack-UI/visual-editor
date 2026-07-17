/**
 * `{{` autocomplete completer for RichText.
 *
 * Registers a Gutenberg autocompleter that triggers on `{{` inside a
 * RichText field and offers grouped Dynamic Content tokens. Suppressed
 * inside code/preformatted blocks so tokens don't fire inside literal
 * content.
 *
 * @since 1.4.0
 */

import { addFilter } from '@wordpress/hooks';
import { select } from '@wordpress/data';

import { fetchSources, flattenTokens } from './api';

interface AutocompleteOption {
    key: string;
    label: string;
    value: string;
}

interface AutocompleteCompleter {
    name: string;
    triggerPrefix: string;
    options: () => Promise<AutocompleteOption[]>;
    getOptionLabel: (o: AutocompleteOption) => string;
    getOptionKeywords: (o: AutocompleteOption) => string[];
    getOptionCompletion: (o: AutocompleteOption) => string;
    allowContext?: (before: string, after: string) => boolean;
    className?: string;
}

const LITERAL_BLOCKS = new Set([
    'artisanpack/code',
    'core/code',
    'artisanpack/preformatted',
    'core/preformatted',
]);

let cachedOptions: AutocompleteOption[] | null = null;

async function loadOptions(): Promise<AutocompleteOption[]> {
    if (cachedOptions) return cachedOptions;
    try {
        const sources = await fetchSources();
        cachedOptions = flattenTokens(sources).map((row) => ({
            key: row.token,
            label: `${row.sourceLabel} → ${row.fieldLabel}`,
            value: row.token,
        }));
        return cachedOptions;
    } catch {
        return [];
    }
}

function isInsideLiteralBlock(): boolean {
    try {
        const blockEditor = select('core/block-editor') as unknown as {
            getSelectedBlock?: () => { name?: string } | null;
        };
        const selected = blockEditor?.getSelectedBlock?.();
        if (selected?.name && LITERAL_BLOCKS.has(selected.name)) return true;
    } catch {
        // ignore — best-effort suppression only
    }
    return false;
}

const dynamicContentCompleter: AutocompleteCompleter = {
    name: 'artisanpack-dynamic-content-tokens',
    triggerPrefix: '{{',
    className: 've-dc-completer',
    options: loadOptions,
    getOptionLabel: (o) => o.label,
    getOptionKeywords: (o) => [o.key, o.value, o.label],
    getOptionCompletion: (o) => `{{${o.value}}}`,
    allowContext: (_before, _after) => !isInsideLiteralBlock(),
};

let registered = false;

/**
 * Idempotently register the completer on the Gutenberg completers
 * filter. Safe to call more than once.
 *
 * @since 1.4.0
 */
export function registerDynamicContentAutocomplete(): void {
    if (registered) return;
    registered = true;

    addFilter(
        'editor.Autocomplete.completers',
        'artisanpack-ui/visual-editor/dynamic-content-completer',
        (completers: AutocompleteCompleter[] = [], _blockName?: string): AutocompleteCompleter[] => {
            if (Array.isArray(completers) && completers.some((c) => c?.name === dynamicContentCompleter.name)) {
                return completers;
            }
            return [...(completers ?? []), dynamicContentCompleter];
        }
    );
}
