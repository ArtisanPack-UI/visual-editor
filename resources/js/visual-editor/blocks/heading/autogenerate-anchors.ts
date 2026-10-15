/**
 * Heading — anchor slug generator.
 *
 * Ported from `@wordpress/block-library/src/heading/autogenerate-anchors.js`
 * (v9.43.0). Maintains the per-client anchor registry so duplicate slugs
 * across multiple heading blocks get a `-1`, `-2`, … suffix.
 *
 * Extended (1.15.0): the slug is reduced to the characters every renderer
 * keeps in a rendered `id` (`[A-Za-z0-9_:.-]`) before the uniqueness
 * check, and text whose letters all drop out (`日本語`) falls back to
 * `heading`. This matches `TocResolver::slug()` and the React / Vue
 * `headingSlug()`, so the anchor the editor saves is the id the front end
 * prints and the TOC links to.
 */

import removeAccents from 'remove-accents';

const anchors: Record<string, string | null> = {};

/**
 * The auto anchor for a heading whose slug keeps no id-safe characters.
 * Mirrors `TocResolver::FALLBACK_SLUG`.
 *
 * @since 1.15.0
 */
export const FALLBACK_SLUG = 'heading';

function getTextWithoutMarkup(text: string): string {
    const dummyElement = document.createElement('div');
    dummyElement.innerHTML = text;
    // Upstream reads `innerText`; on a detached element that is the same
    // as `textContent`, which DOM shims without layout also implement.
    return dummyElement.textContent ?? '';
}

/**
 * Slug heading content into the anchor the renderers print as its `id`.
 *
 * @since 1.15.0 Reduced to `[A-Za-z0-9_:.-]`, with a `heading` fallback.
 */
export function getSlug(content: string): string {
    const slug = removeAccents(getTextWithoutMarkup(content))
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .toLowerCase()
        .replace(/(^-+)|(-+$)/g, '');

    if (slug === '') {
        return '';
    }

    const safe = slug
        .replace(/[^A-Za-z0-9_:.-]/g, '')
        .replace(/-{2,}/g, '-')
        .replace(/(^-+)|(-+$)/g, '');

    return safe === '' ? FALLBACK_SLUG : safe;
}

export function generateAnchor(clientId: string, content: string): string | null {
    const slug = getSlug(content);
    if (slug === '') {
        return null;
    }

    delete anchors[clientId];

    let anchor = slug;
    let i = 0;

    while (Object.values(anchors).includes(anchor)) {
        i += 1;
        anchor = `${slug}-${i}`;
    }

    return anchor;
}

export function setAnchor(clientId: string, anchor: string | null): void {
    anchors[clientId] = anchor;
}
