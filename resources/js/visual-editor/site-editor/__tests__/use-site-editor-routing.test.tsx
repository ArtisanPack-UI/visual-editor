import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import {
    buildSiteEditorPath,
    parseSiteEditorPath,
    useSiteEditorRouting,
} from '../use-site-editor-routing';

const ROUTE_BASE = '/visual-editor/site';

function setPath(pathname: string): void {
    window.history.replaceState(null, '', pathname);
}

describe('parseSiteEditorPath', () => {
    it('returns the default section when the URL is the bare prefix', () => {
        expect(parseSiteEditorPath(ROUTE_BASE, ROUTE_BASE)).toEqual({
            section: 'templates',
            entityId: null,
        });

        expect(parseSiteEditorPath(`${ROUTE_BASE}/`, ROUTE_BASE)).toEqual({
            section: 'templates',
            entityId: null,
        });
    });

    it('resolves the five known section slugs', () => {
        for (const slug of [
            'templates',
            'template-parts',
            'patterns',
            'styles',
            'navigation',
        ] as const) {
            expect(parseSiteEditorPath(`${ROUTE_BASE}/${slug}`, ROUTE_BASE)).toEqual({
                section: slug,
                entityId: null,
            });
        }
    });

    it('parses an entity id from the trailing path segment', () => {
        expect(
            parseSiteEditorPath(`${ROUTE_BASE}/templates/42`, ROUTE_BASE)
        ).toEqual({
            section: 'templates',
            entityId: '42',
        });

        expect(
            parseSiteEditorPath(`${ROUTE_BASE}/patterns/a/b`, ROUTE_BASE)
        ).toEqual({
            section: 'patterns',
            entityId: 'a/b',
        });
    });

    it('decodes percent-encoded entity ids (round-trips reserved chars)', () => {
        expect(
            parseSiteEditorPath(
                `${ROUTE_BASE}/templates/${encodeURIComponent('hero / banner')}`,
                ROUTE_BASE
            )
        ).toEqual({
            section: 'templates',
            entityId: 'hero / banner',
        });
    });

    it('falls back to the raw segment when the entity id is malformed', () => {
        // A lone `%` is not a valid escape sequence — `decodeURIComponent`
        // throws on it, and the router should not bring the SPA down on
        // a hand-typed URL.
        expect(
            parseSiteEditorPath(`${ROUTE_BASE}/templates/100%`, ROUTE_BASE)
        ).toEqual({
            section: 'templates',
            entityId: '100%',
        });
    });

    it('falls back to the default section for unknown slugs', () => {
        expect(
            parseSiteEditorPath(`${ROUTE_BASE}/not-a-section`, ROUTE_BASE)
        ).toEqual({ section: 'templates', entityId: null });
    });

    it('returns the default when the pathname is outside the route base', () => {
        expect(parseSiteEditorPath('/somewhere/else', ROUTE_BASE)).toEqual({
            section: 'templates',
            entityId: null,
        });
    });
});

describe('buildSiteEditorPath', () => {
    it('joins the section and entity id under the route base', () => {
        expect(buildSiteEditorPath(ROUTE_BASE, 'templates')).toBe(
            '/visual-editor/site/templates'
        );

        expect(buildSiteEditorPath(ROUTE_BASE, 'patterns', '7')).toBe(
            '/visual-editor/site/patterns/7'
        );
    });

    it('strips a trailing slash from the route base', () => {
        expect(buildSiteEditorPath('/visual-editor/site/', 'styles')).toBe(
            '/visual-editor/site/styles'
        );
    });

    it('encodes reserved characters in the entity id', () => {
        expect(
            buildSiteEditorPath(ROUTE_BASE, 'templates', 'hero / banner')
        ).toBe('/visual-editor/site/templates/hero%20%2F%20banner');
    });

    it('round-trips the entity id through parse + build', () => {
        const original = 'hero / banner';
        const built = buildSiteEditorPath(ROUTE_BASE, 'patterns', original);
        const parsed = parseSiteEditorPath(built, ROUTE_BASE);

        expect(parsed.entityId).toBe(original);
    });
});

describe('useSiteEditorRouting', () => {
    beforeEach(() => {
        setPath(ROUTE_BASE);
    });

    afterEach(() => {
        setPath('/');
    });

    it('initializes from the current pathname', () => {
        setPath(`${ROUTE_BASE}/styles`);

        const { result } = renderHook(() =>
            useSiteEditorRouting({ routeBase: ROUTE_BASE })
        );

        expect(result.current.section).toBe('styles');
        expect(result.current.entityId).toBeNull();
    });

    it('navigate() pushes a new history entry and updates state', () => {
        const { result } = renderHook(() =>
            useSiteEditorRouting({ routeBase: ROUTE_BASE })
        );

        expect(result.current.section).toBe('templates');

        act(() => {
            result.current.navigate('navigation');
        });

        expect(result.current.section).toBe('navigation');
        expect(window.location.pathname).toBe(`${ROUTE_BASE}/navigation`);
    });

    it('responds to popstate (browser back/forward)', () => {
        const { result } = renderHook(() =>
            useSiteEditorRouting({ routeBase: ROUTE_BASE })
        );

        act(() => {
            result.current.navigate('patterns');
        });
        act(() => {
            result.current.navigate('styles');
        });

        // Simulate the back button: rewind history and dispatch popstate.
        act(() => {
            window.history.replaceState(
                { section: 'patterns', entityId: null },
                '',
                `${ROUTE_BASE}/patterns`
            );
            window.dispatchEvent(new PopStateEvent('popstate'));
        });

        expect(result.current.section).toBe('patterns');
    });

    it('does not push duplicate entries when re-navigating to the active section', () => {
        const { result } = renderHook(() =>
            useSiteEditorRouting({ routeBase: ROUTE_BASE })
        );

        act(() => {
            result.current.navigate('templates');
        });
        const lengthAfterFirst = window.history.length;

        act(() => {
            result.current.navigate('templates');
        });

        expect(window.history.length).toBe(lengthAfterFirst);
    });
});
