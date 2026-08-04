/**
 * `deep-link.ts` is the pure half of #625 — the query-string contract
 * the composed view's **Edit template ↗** CTA writes and the SPA reads.
 * Parsing is asserted here; the navigation it drives lives in
 * `use-deep-link.test.tsx`.
 */

import { describe, expect, it } from 'vitest';

import { buildTemplateDeepLink, parseDeepLink } from '../deep-link';

describe('parseDeepLink', () => {
    it('parses a template deep link', () => {
        expect(parseDeepLink('?entity=template&slug=single')).toEqual({
            entity: 'template',
            slug: 'single',
            entityId: null,
        });
    });

    it('accepts a search string without the leading question mark', () => {
        expect(parseDeepLink('entity=template&slug=single')).toEqual({
            entity: 'template',
            slug: 'single',
            entityId: null,
        });
    });

    it('decodes percent-encoded slugs', () => {
        expect(parseDeepLink('?entity=template&slug=single%2Fpost')).toEqual({
            entity: 'template',
            slug: 'single/post',
            entityId: null,
        });
    });

    it('carries entity_id through when present', () => {
        expect(
            parseDeepLink('?entity=template&slug=single&entity_id=42')
        ).toEqual({
            entity: 'template',
            slug: 'single',
            entityId: '42',
        });
    });

    it.each([
        ['empty search', ''],
        ['no leading params', '?'],
        ['unrelated params only', '?foo=bar'],
        ['no slug', '?entity=template'],
        ['blank slug', '?entity=template&slug='],
        ['whitespace-only slug', '?entity=template&slug=%20%20'],
        ['unknown entity', '?entity=navigation&slug=primary'],
        ['slug without entity', '?slug=single'],
    ])('returns null for %s', (_label, search) => {
        expect(parseDeepLink(search)).toBeNull();
    });
});

describe('buildTemplateDeepLink', () => {
    it('builds against the package route base by default', () => {
        expect(buildTemplateDeepLink('single')).toBe(
            '/visual-editor/site?entity=template&slug=single'
        );
    });

    it('encodes reserved characters in the slug', () => {
        expect(buildTemplateDeepLink('single/post&more')).toBe(
            '/visual-editor/site?entity=template&slug=single%2Fpost%26more'
        );
    });

    it('strips trailing slashes from a host-supplied route base', () => {
        expect(buildTemplateDeepLink('single', '/admin/site/')).toBe(
            '/admin/site?entity=template&slug=single'
        );
    });

    it('round-trips through parseDeepLink', () => {
        const url = buildTemplateDeepLink('single/post&more');
        const search = url.slice(url.indexOf('?'));

        expect(parseDeepLink(search)).toEqual({
            entity: 'template',
            slug: 'single/post&more',
            entityId: null,
        });
    });
});
