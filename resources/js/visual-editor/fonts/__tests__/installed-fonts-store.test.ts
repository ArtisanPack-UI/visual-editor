/**
 * Installed-fonts store slice — unit tests (#636).
 *
 * Covers preset-slug normalization, option mapping, the load/force/dedupe
 * lifecycle, the snapshot vs. mutation subscriber channels, and the
 * install/uninstall `notifyFontsChanged` behavior that refreshes pickers and
 * re-references the canvas `fonts.css`.
 *
 * @since 1.7.0
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { InstalledFont } from '../api-client';
import {
    ensureInstalledFontsLoaded,
    fontPresetSlug,
    fontPresetValue,
    getInstalledFontsSnapshot,
    installedFontToOption,
    loadInstalledFonts,
    notifyFontsChanged,
    resetInstalledFontsStore,
    setInstalledFonts,
    subscribeFontsChanged,
    subscribeInstalledFonts,
} from '../installed-fonts-store';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function makeFont(overrides: Partial<InstalledFont> = {}): InstalledFont {
    return {
        id: 1,
        provider: 'google',
        family: 'Inter',
        slug: 'inter',
        is_variable: false,
        license: null,
        source_url: null,
        installed_at: null,
        faces: [],
        ...overrides,
    };
}

const fetchMock = vi.fn();

beforeEach(() => {
    resetInstalledFontsStore();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('preset slug + value helpers', () => {
    it('normalizes a slug to the CSS custom-property token', () => {
        expect(fontPresetSlug('Open Sans')).toBe('open-sans');
        expect(fontPresetSlug('inter')).toBe('inter');
        expect(fontPresetSlug('  Fira__Code  ')).toBe('fira-code');
    });

    it('builds the preset var reference', () => {
        expect(fontPresetValue('inter')).toBe(
            'var(--wp--preset--font-family--inter)'
        );
    });

    it('maps an installed font to a font-family option', () => {
        expect(installedFontToOption(makeFont({ family: 'Open Sans', slug: 'open-sans' }))).toEqual({
            slug: 'open-sans',
            label: 'Open Sans',
            value: 'var(--wp--preset--font-family--open-sans)',
        });
    });
});

describe('load lifecycle', () => {
    it('fetches installed fonts and exposes them on the snapshot', async () => {
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ data: [makeFont()], can_manage: true, read_only: false })
        );

        await loadInstalledFonts();

        const snapshot = getInstalledFontsSnapshot();
        expect(snapshot.status).toBe('ready');
        expect(snapshot.fonts).toHaveLength(1);
        expect(snapshot.fonts[0]?.family).toBe('Inter');
    });

    it('does not re-fetch a ready store unless forced', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ data: [makeFont()], can_manage: true, read_only: false })
        );

        await loadInstalledFonts();
        await loadInstalledFonts();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        await loadInstalledFonts({ force: true });
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('only auto-loads once from the idle state', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ data: [], can_manage: true, read_only: false })
        );

        ensureInstalledFontsLoaded();
        await Promise.resolve();
        await Promise.resolve();
        ensureInstalledFontsLoaded();

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('runs a follow-up fetch when a force arrives during an in-flight load', async () => {
        let resolveFirst: (value: Response) => void = () => {};
        const firstResponse = new Promise<Response>((resolve) => {
            resolveFirst = resolve;
        });

        fetchMock
            // Initial (pre-install) load, issued at picker mount — resolves late.
            .mockReturnValueOnce(firstResponse)
            // Forced reload after the install commits — the authoritative list.
            .mockResolvedValueOnce(
                jsonResponse({
                    data: [
                        makeFont({ id: 1 }),
                        makeFont({ id: 2, family: 'Roboto Slab', slug: 'roboto-slab' }),
                    ],
                    can_manage: true,
                    read_only: false,
                })
            );

        const initial = loadInstalledFonts();
        // A font is installed while the initial GET is still in flight.
        notifyFontsChanged();
        resolveFirst(
            jsonResponse({ data: [makeFont({ id: 1 })], can_manage: true, read_only: false })
        );

        await initial;
        // Let the chained forced fetch settle its state update.
        await loadInstalledFonts();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(getInstalledFontsSnapshot().fonts).toHaveLength(2);
    });

    it('degrades to an error status with an empty list on failure', async () => {
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ error: 'boom' }, 500)
        );

        await loadInstalledFonts();

        const snapshot = getInstalledFontsSnapshot();
        expect(snapshot.status).toBe('error');
        expect(snapshot.fonts).toHaveLength(0);
    });
});

describe('subscriber channels', () => {
    it('notifies snapshot subscribers when the list changes', () => {
        const listener = vi.fn();
        const unsubscribe = subscribeInstalledFonts(listener);

        setInstalledFonts([makeFont()]);
        expect(listener).toHaveBeenCalledTimes(1);

        unsubscribe();
        setInstalledFonts([]);
        expect(listener).toHaveBeenCalledTimes(1);
    });

    it('setInstalledFonts hydrates without firing the mutation channel', () => {
        const mutation = vi.fn();
        subscribeFontsChanged(mutation);

        setInstalledFonts([makeFont()]);

        expect(mutation).not.toHaveBeenCalled();
    });

    it('notifyFontsChanged fires the mutation channel and reloads the list', async () => {
        const mutation = vi.fn();
        subscribeFontsChanged(mutation);
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ data: [makeFont()], can_manage: true, read_only: false })
        );

        notifyFontsChanged();

        expect(mutation).toHaveBeenCalledTimes(1);
        await Promise.resolve();
        await Promise.resolve();
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
});
