/**
 * Heading anchor autogeneration (M-13): the editor saves the same anchor
 * `TocResolver` and the React / Vue renderers print as the heading `id`.
 */

import { afterEach, describe, expect, it } from 'vitest';

import { generateAnchor, getSlug, setAnchor } from '../autogenerate-anchors';

const clientIds: string[] = [];

function claim(clientId: string, content: string): string | null {
    const anchor = generateAnchor(clientId, content);

    setAnchor(clientId, anchor);
    clientIds.push(clientId);

    return anchor;
}

describe('autogenerate-anchors', () => {
    afterEach(() => {
        clientIds.splice(0).forEach((clientId) => setAnchor(clientId, null));
    });

    it('slugs Latin text like the upstream port', () => {
        expect(getSlug('Café <em>Menü</em>')).toBe('cafe-menu');
        expect(getSlug('Getting Started!')).toBe('getting-started');
    });

    it('reduces non-Latin text to the id the renderers print', () => {
        expect(getSlug('Crème ½ x')).toBe('creme-x');
        expect(getSlug('Résumé 日本')).toBe('resume');
        expect(getSlug('日本語')).toBe('heading');
        expect(getSlug('Привет мир')).toBe('heading');
    });

    it('gives no anchor to text with no letters or numbers', () => {
        expect(getSlug('???')).toBe('');
        expect(generateAnchor('punctuation', '— ? —')).toBeNull();
    });

    it('suffixes duplicates after sanitizing, so ids stay unique', () => {
        expect(claim('a', 'Crème ½ x')).toBe('creme-x');
        expect(claim('b', 'Crème ¼ x')).toBe('creme-x-1');
        expect(claim('c', '日本語')).toBe('heading');
        expect(claim('d', 'Résumé 日本')).toBe('resume');
        expect(claim('e', 'Résumé 中文')).toBe('resume-1');
        expect(claim('f', '中文')).toBe('heading-1');
    });
});
