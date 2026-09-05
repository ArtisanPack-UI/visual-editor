/**
 * Host preset registry (#773).
 *
 * Verifies the `data-presets` mount attribute is parsed into the
 * palette / font-size / font-family / spacing-size descriptor record
 * that `editor-settings.ts` merges into `editorSettings`, and that the
 * merge helper honours the per-list mode.
 */

import { afterEach, describe, expect, it } from 'vitest';

import {
    getHostPresets,
    mergePresetList,
    refreshHostPresets,
    type PaletteEntry,
} from '../preset-registry';

function mount(
    selector: 'data-ap-visual-editor' | 'data-ap-site-editor',
    presets: unknown,
): void {
    const el = document.createElement('div');
    el.setAttribute(selector, '');
    if (presets !== undefined) {
        el.setAttribute(
            'data-presets',
            typeof presets === 'string' ? presets : JSON.stringify(presets),
        );
    }
    document.body.appendChild(el);
}

afterEach(() => {
    document.body.innerHTML = '';
    refreshHostPresets();
});

describe('host preset registry', () => {
    it('returns empty append-mode lists when the mount is absent', () => {
        const presets = getHostPresets();

        expect(presets.palette).toEqual({ mode: 'append', entries: [] });
        expect(presets.fontSizes).toEqual({ mode: 'append', entries: [] });
        expect(presets.fontFamilies).toEqual({ mode: 'append', entries: [] });
        expect(presets.spacingSizes).toEqual({ mode: 'append', entries: [] });
    });

    it('parses palette entries stamped on the post-editor mount', () => {
        mount('data-ap-visual-editor', {
            palette: [
                { slug: 'brand-navy', name: 'Brand Navy', color: '#0a2540' },
            ],
        });

        const presets = getHostPresets();

        expect(presets.palette).toEqual({
            mode: 'append',
            entries: [
                { slug: 'brand-navy', name: 'Brand Navy', color: '#0a2540' },
            ],
        });
    });

    it('reads the site-editor mount when no post-editor mount is present', () => {
        mount('data-ap-site-editor', {
            palette: [{ slug: 'ink', name: 'Ink', color: '#111' }],
        });

        expect(getHostPresets().palette.entries).toEqual([
            { slug: 'ink', name: 'Ink', color: '#111' },
        ]);
    });

    it('honours the explicit replace mode wrapper', () => {
        mount('data-ap-visual-editor', {
            palette: {
                mode: 'replace',
                entries: [{ slug: 'x', name: 'X', color: '#000' }],
            },
        });

        expect(getHostPresets().palette).toEqual({
            mode: 'replace',
            entries: [{ slug: 'x', name: 'X', color: '#000' }],
        });
    });

    it('falls back to append when the mode value is unknown', () => {
        mount('data-ap-visual-editor', {
            palette: {
                mode: 'merge',
                entries: [{ slug: 'x', color: '#000' }],
            },
        });

        expect(getHostPresets().palette.mode).toBe('append');
    });

    it('drops entries with invalid slug or missing value', () => {
        mount('data-ap-visual-editor', {
            palette: [
                { slug: 'bad slug', color: '#000' },
                { slug: 'no-color' },
                { slug: 'good', color: '#111' },
            ],
        });

        expect(getHostPresets().palette.entries).toEqual([
            { slug: 'good', name: 'good', color: '#111' },
        ]);
    });

    it('dedupes entries whose slug collapses to the same value', () => {
        mount('data-ap-visual-editor', {
            palette: [
                { slug: 'Brand', color: '#111' },
                { slug: ' brand ', color: '#222' },
                { slug: 'other', color: '#333' },
            ],
        });

        expect(
            getHostPresets().palette.entries.map((entry) => entry.slug),
        ).toEqual(['brand', 'other']);
    });

    it('parses font-size, font-family, and spacing-size entries', () => {
        mount('data-ap-visual-editor', {
            fontSizes: [{ slug: 'display', name: 'Display', size: '48px' }],
            fontFamilies: [
                { slug: 'brand', name: 'Brand', fontFamily: 'Inter, sans-serif' },
            ],
            spacingSizes: [{ slug: 'gutter', name: 'Gutter', size: '2rem' }],
        });

        const presets = getHostPresets();

        expect(presets.fontSizes.entries).toEqual([
            { slug: 'display', name: 'Display', size: '48px' },
        ]);
        expect(presets.fontFamilies.entries).toEqual([
            { slug: 'brand', name: 'Brand', fontFamily: 'Inter, sans-serif' },
        ]);
        expect(presets.spacingSizes.entries).toEqual([
            { slug: 'gutter', name: 'Gutter', size: '2rem' },
        ]);
    });

    it('degrades to empty presets when the attribute is not valid JSON', () => {
        mount('data-ap-visual-editor', '{not json');

        const presets = getHostPresets();

        expect(presets.palette.entries).toEqual([]);
        expect(presets.fontSizes.entries).toEqual([]);
    });
});

describe('mergePresetList', () => {
    const defaults: ReadonlyArray<PaletteEntry> = Object.freeze([
        { slug: 'primary', name: 'Primary', color: '#2563eb' },
        { slug: 'accent', name: 'Accent', color: '#9333ea' },
    ]);

    it('returns the defaults unchanged when the host list is empty', () => {
        const merged = mergePresetList(defaults, { mode: 'append', entries: [] });

        expect(merged).toBe(defaults);
    });

    it('appends new entries after the defaults', () => {
        const merged = mergePresetList(defaults, {
            mode: 'append',
            entries: [{ slug: 'brand', name: 'Brand', color: '#000' }],
        });

        expect(merged).toEqual([
            { slug: 'primary', name: 'Primary', color: '#2563eb' },
            { slug: 'accent', name: 'Accent', color: '#9333ea' },
            { slug: 'brand', name: 'Brand', color: '#000' },
        ]);
    });

    it('overrides a default in place when a host slug collides', () => {
        const merged = mergePresetList(defaults, {
            mode: 'append',
            entries: [{ slug: 'primary', name: 'Custom Primary', color: '#111' }],
        });

        expect(merged).toEqual([
            { slug: 'primary', name: 'Custom Primary', color: '#111' },
            { slug: 'accent', name: 'Accent', color: '#9333ea' },
        ]);
    });

    it('replaces the defaults entirely under replace mode', () => {
        const merged = mergePresetList(defaults, {
            mode: 'replace',
            entries: [{ slug: 'only', name: 'Only', color: '#000' }],
        });

        expect(merged).toEqual([{ slug: 'only', name: 'Only', color: '#000' }]);
    });

    it('overrides a canonicalised theme slug in place (#773 CR)', () => {
        // Simulates the merge boundary in `useThemedEditorSettings`
        // after `extractThemePalette` canonicalises theme slugs to
        // lowercase. A host `primary` should replace the theme's
        // `primary` (formerly `Primary`) in situ.
        const themeAfterCanonicalise: ReadonlyArray<PaletteEntry> = [
            { slug: 'primary', name: 'Theme Primary', color: '#3b82f6' },
            { slug: 'foreground', name: 'Theme Foreground', color: '#000' },
        ];

        const merged = mergePresetList(themeAfterCanonicalise, {
            mode: 'append',
            entries: [{ slug: 'primary', name: 'Host Primary', color: '#ff0000' }],
        });

        expect(merged).toEqual([
            { slug: 'primary', name: 'Host Primary', color: '#ff0000' },
            { slug: 'foreground', name: 'Theme Foreground', color: '#000' },
        ]);
    });

    it('clears the defaults when replace mode ships an empty entry list', () => {
        // Explicit "no presets for this list" — mirrors the "host wins
        // outright" contract in `config/visual-editor.php`. An empty
        // list under `append` is still a no-op (see the empty-host-list
        // test above) so hosts can only clear via `mode: replace`.
        const merged = mergePresetList(defaults, { mode: 'replace', entries: [] });

        expect(merged).toEqual([]);
    });
});
