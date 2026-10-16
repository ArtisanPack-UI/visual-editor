/**
 * i18n bootstrap for the visual editor.
 *
 * Initializes `@wordpress/i18n` with an empty default locale (English) for
 * the `artisanpack-visual-editor` text domain. All `__('…', 'artisanpack-visual-editor')`
 * calls in the editor fall through to the original English strings until real
 * translations are loaded.
 *
 * Translations (#896) arrive as Jed locale data on the mount — the
 * `data-locale-data` attribute the Blade mounts stamp from
 * `lang/vendor/visual-editor/{locale}.json`, or `MountConfig.localeData` —
 * and {@link installLocaleData} applies them before the lazily loaded editor
 * chunk is imported, so the `__()` calls that run at import time (block
 * variations, editor-settings presets) are translated too.
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
 * Jed locale data for one domain: the `""` header plus msgid → translation
 * list entries, as `setLocaleData()` takes it.
 */
export type EditorLocaleData = Record<string, unknown>;

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Read a Jed payload as the bare locale data `setLocaleData()` takes: a
 * `wp i18n make-json` file (`{ locale_data: { messages: … } }`) or the
 * locale data itself (`{ "": { … }, "Save": [ "…" ] }`). Returns `null` for
 * anything else, or when no translation survives. Mirrors PHP
 * `EditorLocaleData::normalise()`.
 */
export function normalizeLocaleData(payload: unknown): EditorLocaleData | null {
    let data = payload;

    if (isRecord(data) && isRecord(data.locale_data)) {
        const domains = data.locale_data;
        data = domains[TEXT_DOMAIN] ?? domains.messages ?? Object.values(domains)[0];
    }

    if (!isRecord(data)) {
        return null;
    }

    const messages: EditorLocaleData = {};
    let count = 0;

    for (const [key, value] of Object.entries(data)) {
        if (key === '') {
            if (isRecord(value)) {
                messages[''] = value;
            }
            continue;
        }

        if (!Array.isArray(value)) {
            continue;
        }

        const translations = value.filter((entry): entry is string => typeof entry === 'string');

        if (translations.length > 0) {
            messages[key] = translations;
            count += 1;
        }
    }

    return count === 0 ? null : messages;
}

/**
 * Apply translations to the editor's text domain (#896). Boots the domain
 * first so a later {@link bootI18n} can't reset the header (and with it
 * the plural forms). Returns whether anything was installed.
 */
export function installLocaleData(payload: unknown): boolean {
    const data = normalizeLocaleData(payload);

    if (data === null) {
        return false;
    }

    bootI18n();

    const header = isRecord(data['']) ? data[''] : {};

    setLocaleData({ ...data, '': { ...header, domain: TEXT_DOMAIN } }, TEXT_DOMAIN);

    return true;
}

/**
 * Parse a `data-locale-data` attribute and apply it. A missing, empty or
 * malformed attribute is ignored (the editor stays in English).
 */
export function installLocaleDataFromDataset(raw: string | undefined): boolean {
    if (raw === undefined || raw.trim() === '') {
        return false;
    }

    try {
        return installLocaleData(JSON.parse(raw));
    } catch (error) {
        console.warn('visual-editor: could not parse data-locale-data as JSON.', error);
        return false;
    }
}

/**
 * Test-only reset hook. Not exported from the package entry point.
 */
export function __resetI18nForTests(): void {
    initialized = false;
}
