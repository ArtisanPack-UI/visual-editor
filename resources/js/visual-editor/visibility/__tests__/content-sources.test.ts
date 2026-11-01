/**
 * Tests for the Content rule's source list and subtree detection (#1030).
 */

import { afterEach, describe, expect, it } from 'vitest';

import {
    collectSubtreeBlockNames,
    getVisibilityContentSources,
    refreshVisibilityContentSources,
} from '../content-sources';

const SERVER_SOURCES = [
    {
        key: 'products',
        label: 'Products',
        blocks: ['acme/products'],
        conditions: [{ value: 'inStock', label: 'In stock' }],
    },
];

function mount(attribute: string, payload: string): void {
    const element = document.createElement('div');

    element.setAttribute(attribute, '');
    element.dataset.visibilityContentSources = payload;
    document.body.appendChild(element);
    refreshVisibilityContentSources();
}

afterEach(() => {
    document.body.innerHTML = '';
    refreshVisibilityContentSources();
});

describe('getVisibilityContentSources', () => {
    it('falls back to the built-ins without a mount element', () => {
        expect(getVisibilityContentSources().map((source) => source.key))
            .toEqual(['query', 'relatedPosts', 'postNavigation', 'pagination']);
    });

    it.each(['data-ap-visual-editor', 'data-ap-site-editor'])('reads the server list from %s', (attribute) => {
        mount(attribute, JSON.stringify(SERVER_SOURCES));

        expect(getVisibilityContentSources()).toEqual(SERVER_SOURCES);
    });

    it.each(['not json', '{}', '[]', '[{"key":"nope"}]'])('falls back on the unusable payload %s', (payload) => {
        mount('data-ap-visual-editor', payload);

        expect(getVisibilityContentSources()[0].key).toBe('query');
    });
});

describe('collectSubtreeBlockNames', () => {
    const blocks: Record<string, { name: string; order: string[] }> = {
        group: { name: 'artisanpack/group', order: ['heading', 'part'] },
        heading: { name: 'artisanpack/heading', order: [] },
        part: { name: 'artisanpack/template-part', order: ['related'] },
        related: { name: 'artisanpack/related-posts', order: [] },
    };

    const store = {
        getBlockName: (clientId: string) => blocks[clientId]?.name ?? null,
        getBlockOrder: (clientId: string) => blocks[clientId]?.order ?? [],
    };

    it('walks the block and its descendants in document order', () => {
        expect(collectSubtreeBlockNames(store, 'group')).toEqual([
            'artisanpack/group',
            'artisanpack/heading',
            'artisanpack/template-part',
            'artisanpack/related-posts',
        ]);
    });

    it('returns nothing for an unknown block', () => {
        expect(collectSubtreeBlockNames(store, 'missing')).toEqual([]);
    });
});
