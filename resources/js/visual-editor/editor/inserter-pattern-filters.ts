/**
 * Which patterns the block inserter offers, and how its search matches
 * them.
 *
 * Mirrors Gutenberg: a pattern registered with `inserter: false` (a
 * theme file's `Inserter: no` header) stays registered — templates and
 * code can still reference it, and the site editor's pattern library
 * still lists it — but the block inserter and the page-starter modal
 * leave it out. Search matches every term against the pattern's title,
 * slug, categories and `keywords` (a theme file's `Keywords:` header),
 * case- and accent-insensitively, as Gutenberg's inserter search does.
 *
 * @since 1.15.0
 */

import type { PatternRecord } from '../site-editor/patterns/api-client';

/**
 * Whether the block inserter offers this pattern. Only an explicit
 * `inserter: false` hides it; a missing flag (an older backend) keeps it.
 *
 * @param pattern Pattern as returned by `listPatterns`.
 */
export function isInserterPattern(pattern: PatternRecord): boolean {
    return pattern.inserter !== false;
}

/**
 * Lower-case a string and strip its diacritics so `Café` matches `cafe`.
 *
 * @param value Text to normalise.
 */
function normalizeSearchText(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

/**
 * Whether a pattern matches an inserter search. An empty query matches
 * every pattern; otherwise each whitespace-separated term must appear
 * in the title, slug, a category or a keyword.
 *
 * @param pattern Pattern as returned by `listPatterns`.
 * @param query   The search field's value.
 */
export function patternMatchesSearch(pattern: PatternRecord, query: string): boolean {
    const terms = normalizeSearchText(query).split(/\s+/).filter((term) => term !== '');

    if (terms.length === 0) {
        return true;
    }

    const haystack = [
        pattern.title?.raw ?? '',
        pattern.title?.rendered ?? '',
        pattern.slug,
        ...(pattern.categories ?? []),
        ...(pattern.keywords ?? []),
    ]
        .filter((value): value is string => typeof value === 'string')
        .map(normalizeSearchText);

    return terms.every((term) => haystack.some((value) => value.includes(term)));
}
