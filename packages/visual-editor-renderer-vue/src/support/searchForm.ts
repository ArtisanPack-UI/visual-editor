/**
 * Form action and prefill helpers for the search blocks (#961).
 *
 * Mirrors the Blade renderer's `SearchForm` support class, minus the parts
 * that need the live request: these renderers stay declarative, so request
 * values arrive as `_resolved*` stamps.
 *
 * Where `artisanpack/search` and `artisanpack/search-filters` submit:
 *
 *  - a host-stamped `_resolvedFormAction` wins;
 *  - `action: ''` (the default) submits to the site search URL, `/search`
 *    (the Blade renderer reads `visual-editor.search.url` instead; hosts
 *    with a different URL stamp `_resolvedFormAction`);
 *  - `action: 'current'` omits the attribute, so the browser submits to
 *    the current document with its query string replaced by the form's;
 *  - any other value is a custom path, used only when it is a same-site
 *    relative path, else the site search URL.
 */

import { attrString, phpTrim } from './attributes';
import { safeUrl } from './urlSanitizer';

export const SEARCH_ACTION_CURRENT = 'current';

export const DEFAULT_SEARCH_URL = '/search';

/**
 * Return `path` when it is a same-site relative path, otherwise `''`.
 * Mirrors PHP `SearchForm::sameSitePath()`: it has to start with a single
 * `/`, and protocol-relative URLs, backslashes, whitespace and control
 * characters are rejected.
 */
export function sameSitePath(path: string): string {
    const trimmed = phpTrim(path);

    if (!trimmed.startsWith('/') || trimmed.startsWith('//')) {
        return '';
    }

    // eslint-disable-next-line no-control-regex
    if (trimmed.includes('\\') || /[\x00-\x20\x7F]/.test(trimmed)) {
        return '';
    }

    return trimmed;
}

/**
 * The URL a search form submits to, or `undefined` to omit the `action`
 * attribute and submit to the current document.
 */
export function searchFormAction(attributes: Record<string, unknown>): string | undefined {
    const stamped = attributes._resolvedFormAction;

    if (typeof stamped === 'string') {
        const safe = safeUrl(stamped);

        if (safe !== '') {
            return safe;
        }
    }

    const action = phpTrim(attrString(attributes.action));

    if (action === SEARCH_ACTION_CURRENT) {
        return undefined;
    }

    if (action !== '') {
        const path = sameSitePath(action);

        if (path !== '') {
            return path;
        }
    }

    return DEFAULT_SEARCH_URL;
}

/**
 * `buttonPosition` values the Search block accepts. The first is the default.
 */
export const SEARCH_BUTTON_POSITIONS = ['button-outside', 'button-inside', 'no-button', 'button-only'] as const;

export type SearchButtonPosition = (typeof SEARCH_BUTTON_POSITIONS)[number];

/**
 * The Search block's button position, defaulting to `button-outside`.
 * Mirrors PHP `SearchForm::buttonPosition()`.
 */
export function searchButtonPosition(attributes: Record<string, unknown>): SearchButtonPosition {
    const position = attributes.buttonPosition;

    return (SEARCH_BUTTON_POSITIONS as ReadonlyArray<unknown>).includes(position)
        ? (position as SearchButtonPosition)
        : SEARCH_BUTTON_POSITIONS[0];
}

/**
 * The Search form's wrapper classes for its button position, following
 * WordPress's `render_block_core_search()`. WordPress also adds
 * `wp-block-search__searchfield-hidden` to `button-only` and expands the
 * field with its interactivity script; no such script ships here, so the
 * field stays visible and the form works without JavaScript.
 */
export function searchButtonClasses(attributes: Record<string, unknown>, useIcon: boolean): string[] {
    const position = searchButtonPosition(attributes);
    const classes = [`wp-block-search__${position}`];

    if (position !== 'no-button') {
        classes.push(useIcon ? 'wp-block-search__icon-button' : 'wp-block-search__text-button');
    }

    return classes;
}
