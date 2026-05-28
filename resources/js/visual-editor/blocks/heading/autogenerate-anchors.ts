/**
 * Heading — anchor slug generator.
 *
 * Ported from `@wordpress/block-library/src/heading/autogenerate-anchors.js`
 * (v9.43.0). Maintains the per-client anchor registry so duplicate slugs
 * across multiple heading blocks get a `-1`, `-2`, … suffix.
 */

import removeAccents from 'remove-accents';

const anchors: Record<string, string | null> = {};

function getTextWithoutMarkup(text: string): string {
    const dummyElement = document.createElement('div');
    dummyElement.innerHTML = text;
    return dummyElement.innerText;
}

function getSlug(content: string): string {
    return removeAccents(getTextWithoutMarkup(content))
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .toLowerCase()
        .replace(/(^-+)|(-+$)/g, '');
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
