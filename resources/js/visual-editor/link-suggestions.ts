/**
 * Link selector content search — #1042.
 *
 * Gutenberg's `LinkControl` and `URLInput` get their results from the
 * `__experimentalFetchLinkSuggestions` editor setting, which WordPress
 * fills from `@wordpress/core-data`. This package ships a core-data shim,
 * so the function lives here and is added to the shared `editorSettings`
 * that both editors use.
 *
 * @since 1.20.0
 */

import { getBindingsApiConfig } from './bindings/config';

/**
 * How many results to show before the author has typed, matching core.
 */
const INITIAL_SUGGESTION_COUNT = 3;

export interface LinkSuggestion {
    readonly id: number | string;
    readonly title: string;
    readonly url: string;
    readonly type: string;
    readonly kind: 'post-type';
}

export interface LinkSuggestionOptions {
    readonly isInitialSuggestions?: boolean;
}

interface LinkSearchRow {
    id: number | string;
    title: string;
    url: string;
    type: string;
}

function isLinkSearchRow(row: unknown): row is LinkSearchRow {
    if (row === null || typeof row !== 'object') {
        return false;
    }

    const { id, title, url, type } = row as Record<string, unknown>;

    return (
        (typeof id === 'number' || typeof id === 'string') &&
        typeof title === 'string' &&
        typeof type === 'string' &&
        typeof url === 'string' &&
        url !== ''
    );
}

/**
 * Maps the endpoint's rows to the suggestion shape `LinkControl` expects.
 * Rows that can't be used as links are dropped.
 *
 * @since 1.20.0
 */
export function mapLinkSuggestions(rows: unknown): LinkSuggestion[] {
    if (!Array.isArray(rows)) {
        return [];
    }

    return rows.filter(isLinkSearchRow).map(({ id, title, url, type }) => ({
        id,
        title,
        url,
        type,
        kind: 'post-type',
    }));
}

/**
 * Searches site content for the link selector. Resolves to an empty list
 * on failure so a search error never gets in the way of typing a URL.
 *
 * @since 1.20.0
 *
 * @param search  What the author typed; empty for initial suggestions.
 * @param options The options `LinkControl` passes through.
 */
export async function fetchLinkSuggestions(
    search: string,
    options: LinkSuggestionOptions = {}
): Promise<LinkSuggestion[]> {
    const params = new URLSearchParams({ q: search });

    try {
        // Under the API base the host configured, read per call.
        const searchUrl = `${getBindingsApiConfig().apiBase}/search/links`;
        const response = await fetch(`${searchUrl}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return [];
        }

        const body = (await response.json()) as { data?: unknown } | null;
        const suggestions = mapLinkSuggestions(body?.data);

        return options.isInitialSuggestions
            ? suggestions.slice(0, INITIAL_SUGGESTION_COUNT)
            : suggestions;
    } catch {
        return [];
    }
}
