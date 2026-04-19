/**
 * i18n bootstrap for the visual editor.
 *
 * Initializes `@wordpress/i18n` with an empty default locale (English) for
 * the `artisanpack-visual-editor` text domain. All `__('…', 'artisanpack-visual-editor')`
 * calls in the editor fall through to the original English strings until real
 * translations are loaded.
 *
 * This scaffolds the i18n story for future milestones — a Laravel-side
 * extraction command (see `composer visual-editor:pot` in the package
 * composer.json) will produce a `.pot` file, and loaded translations will be
 * installed here via `setLocaleData()` on editor boot.
 *
 * Tracked by issue #312 (M2 of the Gutenberg adoption, umbrella #309).
 */

import { setLocaleData } from '@wordpress/i18n';

export const TEXT_DOMAIN = 'artisanpack-visual-editor';

/**
 * Minimum Jed locale payload. Calling `setLocaleData` with an empty messages
 * map initializes the domain so later `__()` calls succeed and are routed
 * through the domain's Tannin instance instead of the global one.
 */
const DEFAULT_LOCALE_DATA = {
    '': {
        domain: TEXT_DOMAIN,
        lang: 'en',
    },
};

let initialized = false;

/**
 * Boot the editor's i18n domain. Safe to call multiple times; subsequent
 * calls are no-ops so hot-module reloads don't wipe loaded translations.
 */
export function bootI18n(): void {
    if (initialized) {
        return;
    }

    setLocaleData(DEFAULT_LOCALE_DATA, TEXT_DOMAIN);
    initialized = true;
}

/**
 * Test-only reset hook. Not exported from the package entry point.
 */
export function __resetI18nForTests(): void {
    initialized = false;
}
