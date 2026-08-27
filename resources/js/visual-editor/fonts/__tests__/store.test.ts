/**
 * Font Library reducer — unit tests (#635).
 *
 * Exercises the pure state logic the modal depends on: catalog paging and
 * de-duplicated appends, the bulk-uninstall selection set, install progress,
 * installed-list mutation, and the read-only gate.
 *
 * @since 1.7.0
 */

import { describe, expect, it } from 'vitest';

import type { CatalogFamily, InstalledFont } from '../api-client';
import {
    fontLibraryReducer,
    initialState,
    INSTALLED_TAB,
    isInstalled,
    type FontLibraryState,
} from '../store';

function family(slug: string): CatalogFamily {
    return { slug, family: slug, category: null, variants: ['400', '700'], is_variable: false };
}

function installed(id: number, slug = `slug-${id}`, provider = 'google'): InstalledFont {
    return {
        id,
        provider,
        family: `Font ${id}`,
        slug,
        is_variable: false,
        license: null,
        source_url: null,
        installed_at: null,
        faces: [{ id: id * 10, weight: 400, style: 'normal', format: 'woff2', axes: null, url: '/f.woff2' }],
    };
}

function manageableState(overrides: Partial<FontLibraryState> = {}): FontLibraryState {
    return { ...initialState, readOnly: false, ...overrides };
}

describe('fontLibraryReducer', () => {
    it('sets the active tab', () => {
        const next = fontLibraryReducer(initialState, { type: 'SET_ACTIVE_TAB', tab: 'google' });
        expect(next.activeTab).toBe('google');
    });

    it('loads the installed list and adopts its read-only signal', () => {
        const next = fontLibraryReducer(initialState, {
            type: 'INSTALLED_LOADED',
            fonts: [installed(1)],
            readOnly: false,
        });

        expect(next.installedStatus).toBe('ready');
        expect(next.installed).toHaveLength(1);
        expect(next.readOnly).toBe(false);
    });

    it('clears a pending selection when the installed load reports read-only', () => {
        const state = manageableState({ selectedForRemoval: [1, 2] });

        const next = fontLibraryReducer(state, {
            type: 'INSTALLED_LOADED',
            fonts: [installed(1)],
            readOnly: true,
        });

        expect(next.readOnly).toBe(true);
        expect(next.selectedForRemoval).toEqual([]);
    });

    it('clears a pending selection when the sources load reports read-only', () => {
        const state = manageableState({ selectedForRemoval: [1, 2] });

        const next = fontLibraryReducer(state, {
            type: 'SOURCES_LOADED',
            sources: [],
            readOnly: true,
        });

        expect(next.readOnly).toBe(true);
        expect(next.selectedForRemoval).toEqual([]);
    });

    it('resets paging state on a fresh (page 1) catalog request', () => {
        const seeded = fontLibraryReducer(manageableState(), {
            type: 'CATALOG_SUCCESS',
            requestId: 0,
            provider: 'google',
            page: 3,
            families: [family('inter')],
            hasMore: true,
        });

        // A fresh browse/search must drop the prior `hasMore`, so a failed fresh
        // request can't keep "Load more" showing and skip page 1 of the new query.
        const fresh = fontLibraryReducer(seeded, {
            type: 'CATALOG_REQUEST',
            requestId: 1,
            provider: 'google',
            query: 'ro',
            page: 1,
        });

        expect(fresh.catalogs.google.page).toBe(1);
        expect(fresh.catalogs.google.hasMore).toBe(false);
        expect(fresh.catalogs.google.families).toEqual([]);
    });

    it('records an installed-list error', () => {
        const next = fontLibraryReducer(initialState, {
            type: 'INSTALLED_ERROR',
            message: 'boom',
        });

        expect(next.installedStatus).toBe('error');
        expect(next.installedError).toBe('boom');
    });

    it('clears the family list on a fresh (page 1) catalog request', () => {
        const seeded = fontLibraryReducer(manageableState(), {
            type: 'CATALOG_SUCCESS',
            requestId: 0,
            provider: 'google',
            page: 1,
            families: [family('inter')],
            hasMore: true,
        });

        const next = fontLibraryReducer(seeded, {
            type: 'CATALOG_REQUEST',
            requestId: 0,
            provider: 'google',
            query: 'ro',
            page: 1,
        });

        expect(next.catalogs.google.status).toBe('loading');
        expect(next.catalogs.google.query).toBe('ro');
        expect(next.catalogs.google.families).toEqual([]);
    });

    it('keeps existing families while loading a later page', () => {
        const seeded = fontLibraryReducer(manageableState(), {
            type: 'CATALOG_SUCCESS',
            requestId: 0,
            provider: 'google',
            page: 1,
            families: [family('inter')],
            hasMore: true,
        });

        const next = fontLibraryReducer(seeded, {
            type: 'CATALOG_REQUEST',
            requestId: 0,
            provider: 'google',
            query: '',
            page: 2,
        });

        expect(next.catalogs.google.families).toHaveLength(1);
        expect(next.catalogs.google.status).toBe('loading');
    });

    it('appends a later page while de-duplicating repeated slugs', () => {
        const page1 = fontLibraryReducer(manageableState(), {
            type: 'CATALOG_SUCCESS',
            requestId: 0,
            provider: 'google',
            page: 1,
            families: [family('inter'), family('roboto')],
            hasMore: true,
        });

        const page2 = fontLibraryReducer(page1, {
            type: 'CATALOG_SUCCESS',
            requestId: 0,
            provider: 'google',
            page: 2,
            families: [family('roboto'), family('lato')],
            hasMore: false,
        });

        expect(page2.catalogs.google.families.map((f) => f.slug)).toEqual([
            'inter',
            'roboto',
            'lato',
        ]);
        expect(page2.catalogs.google.hasMore).toBe(false);
    });

    it('ignores a catalog response from a superseded request', () => {
        // Two requests race; the newer one (id 2) is issued last, so the
        // older response (id 1) that lands afterward must be dropped.
        let state = fontLibraryReducer(manageableState(), {
            type: 'CATALOG_REQUEST',
            provider: 'google',
            query: 'a',
            page: 1,
            requestId: 1,
        });
        state = fontLibraryReducer(state, {
            type: 'CATALOG_REQUEST',
            provider: 'google',
            query: 'ab',
            page: 1,
            requestId: 2,
        });

        // Stale response for id 1 arrives — must be ignored.
        const stale = fontLibraryReducer(state, {
            type: 'CATALOG_SUCCESS',
            provider: 'google',
            page: 1,
            families: [family('stale')],
            hasMore: false,
            requestId: 1,
        });
        expect(stale.catalogs.google.families).toEqual([]);
        expect(stale.catalogs.google.status).toBe('loading');

        // The current response for id 2 is applied.
        const fresh = fontLibraryReducer(stale, {
            type: 'CATALOG_SUCCESS',
            provider: 'google',
            page: 1,
            families: [family('fresh')],
            hasMore: false,
            requestId: 2,
        });
        expect(fresh.catalogs.google.families.map((f) => f.slug)).toEqual(['fresh']);
        expect(fresh.catalogs.google.status).toBe('ready');
    });

    it('records a catalog failure without dropping prior families', () => {
        const page1 = fontLibraryReducer(manageableState(), {
            type: 'CATALOG_SUCCESS',
            requestId: 0,
            provider: 'google',
            page: 1,
            families: [family('inter')],
            hasMore: false,
        });

        const next = fontLibraryReducer(page1, {
            type: 'CATALOG_FAILURE',
            requestId: 0,
            provider: 'google',
            message: 'gateway down',
        });

        expect(next.catalogs.google.status).toBe('error');
        expect(next.catalogs.google.error).toBe('gateway down');
        expect(next.catalogs.google.families).toHaveLength(1);
    });

    it('does not advance the page counter when a load-more request fails', () => {
        const page1 = fontLibraryReducer(manageableState(), {
            type: 'CATALOG_SUCCESS',
            requestId: 0,
            provider: 'google',
            page: 1,
            families: [family('inter')],
            hasMore: true,
        });

        // A "load more" request for page 2 is issued, then fails.
        const requested = fontLibraryReducer(page1, {
            type: 'CATALOG_REQUEST',
            requestId: 1,
            provider: 'google',
            query: '',
            page: 2,
        });
        const failed = fontLibraryReducer(requested, {
            type: 'CATALOG_FAILURE',
            requestId: 1,
            provider: 'google',
            message: 'gateway down',
        });

        // page stays at the last successfully-loaded page so the retry (page + 1)
        // requests page 2 again rather than skipping it.
        expect(failed.catalogs.google.page).toBe(1);
    });

    it('toggles a font in and out of the removal selection', () => {
        const state = manageableState({ installed: [installed(1), installed(2)] });

        const selected = fontLibraryReducer(state, { type: 'TOGGLE_SELECT', id: 1 });
        expect(selected.selectedForRemoval).toEqual([1]);

        const deselected = fontLibraryReducer(selected, { type: 'TOGGLE_SELECT', id: 1 });
        expect(deselected.selectedForRemoval).toEqual([]);
    });

    it('ignores selection changes while read-only', () => {
        const state = { ...initialState, readOnly: true, installed: [installed(1)] };

        const next = fontLibraryReducer(state, { type: 'TOGGLE_SELECT', id: 1 });
        expect(next.selectedForRemoval).toEqual([]);

        const all = fontLibraryReducer(state, { type: 'SELECT_ALL', ids: [1] });
        expect(all.selectedForRemoval).toEqual([]);
    });

    it('selects all and clears the selection', () => {
        const state = manageableState({ installed: [installed(1), installed(2)] });

        const all = fontLibraryReducer(state, { type: 'SELECT_ALL', ids: [1, 2] });
        expect(all.selectedForRemoval).toEqual([1, 2]);

        const cleared = fontLibraryReducer(all, { type: 'CLEAR_SELECTION' });
        expect(cleared.selectedForRemoval).toEqual([]);
    });

    it('tracks install progress from start to success', () => {
        const started = fontLibraryReducer(manageableState(), {
            type: 'INSTALL_START',
            slug: 'inter',
            family: 'Inter',
        });
        expect(started.install.status).toBe('installing');

        const done = fontLibraryReducer(started, {
            type: 'INSTALL_SUCCESS',
            font: installed(5, 'inter'),
        });
        expect(done.install.status).toBe('success');
        expect(done.installed.map((f) => f.slug)).toContain('inter');
    });

    it('replaces an existing font on re-install rather than duplicating it', () => {
        const state = manageableState({ installed: [installed(5, 'inter')] });

        const next = fontLibraryReducer(state, {
            type: 'INSTALL_SUCCESS',
            font: installed(5, 'inter'),
        });

        expect(next.installed.filter((f) => f.id === 5)).toHaveLength(1);
    });

    it('keeps the installed list sorted by family after an install', () => {
        const state = manageableState({ installed: [installed(1)] });

        const zebra: InstalledFont = { ...installed(2), family: 'Aaa' };
        const next = fontLibraryReducer(state, { type: 'INSTALL_SUCCESS', font: zebra });

        expect(next.installed[0].family).toBe('Aaa');
    });

    it('records an install error and resets cleanly', () => {
        const started = fontLibraryReducer(manageableState(), {
            type: 'INSTALL_START',
            slug: 'inter',
            family: 'Inter',
        });

        const errored = fontLibraryReducer(started, {
            type: 'INSTALL_ERROR',
            message: 'nope',
        });
        expect(errored.install.status).toBe('error');
        expect(errored.install.error).toBe('nope');

        const reset = fontLibraryReducer(errored, { type: 'INSTALL_RESET' });
        expect(reset.install.status).toBe('idle');
    });

    it('removes fonts and prunes them from the selection', () => {
        const state = manageableState({
            installed: [installed(1), installed(2), installed(3)],
            selectedForRemoval: [1, 2],
        });

        const next = fontLibraryReducer(state, { type: 'FONTS_REMOVED', ids: [1, 2] });

        expect(next.installed.map((f) => f.id)).toEqual([3]);
        expect(next.selectedForRemoval).toEqual([]);
    });

    it('flips to read-only and drops the selection', () => {
        const state = manageableState({ selectedForRemoval: [1] });

        const next = fontLibraryReducer(state, { type: 'SET_READ_ONLY', readOnly: true });

        expect(next.readOnly).toBe(true);
        expect(next.selectedForRemoval).toEqual([]);
    });

    it('starts on the installed tab, read-only, until proven otherwise', () => {
        expect(initialState.activeTab).toBe(INSTALLED_TAB);
        expect(initialState.readOnly).toBe(true);
    });
});

describe('isInstalled', () => {
    it('matches on provider and slug', () => {
        const state = { ...initialState, installed: [installed(1, 'inter', 'google')] };

        expect(isInstalled(state, 'google', 'inter')).toBe(true);
        expect(isInstalled(state, 'bunny', 'inter')).toBe(false);
        expect(isInstalled(state, 'google', 'roboto')).toBe(false);
    });
});
