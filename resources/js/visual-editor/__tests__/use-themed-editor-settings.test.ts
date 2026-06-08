/**
 * Unit tests for the theme-settings extraction helpers (#512).
 *
 * The hook itself (`useThemedEditorSettings`) depends on React hooks
 * and async fetch stubs. The pure extraction functions are tested here
 * to validate the shape → normalized-array mapping logic without
 * needing a React render harness.
 */

import { describe, expect, it } from 'vitest';

import { editorSettings } from '../editor-settings';
import {
    extractThemeFontFamilies,
    extractThemeFontSizes,
    extractThemeGradients,
    extractThemePalette,
    extractThemeSpacingSizes,
} from '../use-themed-editor-settings';

describe('extractThemePalette', () => {
    it('returns null when settings has no color key', () => {
        expect(extractThemePalette({})).toBeNull();
    });

    it('returns null when palette is an empty array', () => {
        expect(extractThemePalette({ color: { palette: [] } })).toBeNull();
    });

    it('extracts valid palette entries and skips malformed ones', () => {
        const settings = {
            color: {
                palette: [
                    { slug: 'primary', name: 'Primary', color: '#3b82f6' },
                    { slug: '', color: '#bad' },
                    null,
                    { slug: 'accent', color: '#10b981' },
                    { slug: 'missing-color' },
                ],
            },
        };

        const result = extractThemePalette(settings);
        expect(result).toEqual([
            { slug: 'primary', name: 'Primary', color: '#3b82f6' },
            { slug: 'accent', name: 'accent', color: '#10b981' },
        ]);
    });

    it('falls back to slug as name when name is missing', () => {
        const settings = {
            color: { palette: [{ slug: 'brand', color: '#ff0000' }] },
        };

        const result = extractThemePalette(settings);
        expect(result).toEqual([{ slug: 'brand', name: 'brand', color: '#ff0000' }]);
    });

    it('dedupes by slug, keeping the first occurrence (#547)', () => {
        const settings = {
            color: {
                palette: [
                    { slug: 'primary', name: 'Primary', color: '#3b82f6' },
                    { slug: 'primary', name: 'Primary Dupe', color: '#000000' },
                ],
            },
        };

        expect(extractThemePalette(settings)).toEqual([
            { slug: 'primary', name: 'Primary', color: '#3b82f6' },
        ]);
    });
});

describe('extractThemeFontSizes', () => {
    it('returns null when settings has no typography key', () => {
        expect(extractThemeFontSizes({})).toBeNull();
    });

    it('returns null when fontSizes is an empty array', () => {
        expect(extractThemeFontSizes({ typography: { fontSizes: [] } })).toBeNull();
    });

    it('extracts valid font-size entries', () => {
        const settings = {
            typography: {
                fontSizes: [
                    { slug: 'small', name: 'Small', size: '0.875rem' },
                    { slug: 'large', size: '1.5rem' },
                ],
            },
        };

        const result = extractThemeFontSizes(settings);
        expect(result).toEqual([
            { slug: 'small', name: 'Small', size: '0.875rem' },
            { slug: 'large', name: 'large', size: '1.5rem' },
        ]);
    });

    it('dedupes by slug to prevent React duplicate-key warnings in FontSizePicker (#547)', () => {
        const settings = {
            typography: {
                fontSizes: [
                    { slug: 'small', name: 'Small', size: '0.875rem' },
                    { slug: 'large', name: 'Large', size: '1.25rem' },
                    { slug: 'small', name: 'Small Dupe', size: '0.75rem' },
                    { slug: 'large', name: 'Large Dupe', size: '1.5rem' },
                ],
            },
        };

        expect(extractThemeFontSizes(settings)).toEqual([
            { slug: 'small', name: 'Small', size: '0.875rem' },
            { slug: 'large', name: 'Large', size: '1.25rem' },
        ]);
    });
});

describe('extractThemeFontFamilies', () => {
    it('returns null when settings has no typography key', () => {
        expect(extractThemeFontFamilies({})).toBeNull();
    });

    it('returns null when fontFamilies is an empty array', () => {
        expect(extractThemeFontFamilies({ typography: { fontFamilies: [] } })).toBeNull();
    });

    it('extracts valid font-family entries', () => {
        const settings = {
            typography: {
                fontFamilies: [
                    { slug: 'sans', name: 'Sans', fontFamily: 'Inter, sans-serif' },
                    { slug: 'serif', fontFamily: 'Georgia, serif' },
                ],
            },
        };

        const result = extractThemeFontFamilies(settings);
        expect(result).toEqual([
            { slug: 'sans', name: 'Sans', fontFamily: 'Inter, sans-serif' },
            { slug: 'serif', name: 'serif', fontFamily: 'Georgia, serif' },
        ]);
    });

    it('skips entries with missing fontFamily', () => {
        const settings = {
            typography: {
                fontFamilies: [
                    { slug: 'mono', name: 'Mono' },
                    { slug: 'sans', name: 'Sans', fontFamily: 'Inter' },
                ],
            },
        };

        const result = extractThemeFontFamilies(settings);
        expect(result).toEqual([
            { slug: 'sans', name: 'Sans', fontFamily: 'Inter' },
        ]);
    });
});

describe('extractThemeSpacingSizes', () => {
    it('returns null when settings has no spacing key', () => {
        expect(extractThemeSpacingSizes({})).toBeNull();
    });

    it('returns null when spacingSizes is an empty array', () => {
        expect(extractThemeSpacingSizes({ spacing: { spacingSizes: [] } })).toBeNull();
    });

    it('extracts valid spacing-size entries', () => {
        const settings = {
            spacing: {
                spacingSizes: [
                    { slug: '20', name: 'Tight', size: '0.5rem' },
                    { slug: '40', size: '1.5rem' },
                ],
            },
        };

        const result = extractThemeSpacingSizes(settings);
        expect(result).toEqual([
            { slug: '20', name: 'Tight', size: '0.5rem' },
            { slug: '40', name: '40', size: '1.5rem' },
        ]);
    });
});

describe('editorSettings typography origins (#547)', () => {
    /*
     * Regression guard: Gutenberg's `TypographyPanel.getMergedFontSizes`
     * concatenates `[...custom, ...theme, ...default]`. If our defaults
     * sit under `custom` while the themed-settings hook adds a host
     * theme under `theme`, slug overlaps produce duplicate React keys
     * in `FontSizePickerSelect`. Defaults must ship under `theme`
     * (with `custom: []`) so the hook's `theme:` override replaces
     * them cleanly instead of stacking.
     */
    const features = editorSettings.__experimentalFeatures as {
        typography?: {
            fontSizes?: { theme?: unknown; custom?: unknown };
            fontFamilies?: { theme?: unknown; custom?: unknown };
        };
    };

    it('ships fontSizes under the theme origin with an empty custom array', () => {
        const fontSizes = features.typography?.fontSizes;
        expect(Array.isArray(fontSizes?.theme)).toBe(true);
        expect((fontSizes?.theme as unknown[]).length).toBeGreaterThan(0);
        expect(fontSizes?.custom).toEqual([]);
    });

    it('ships fontFamilies under the theme origin with an empty custom array', () => {
        const fontFamilies = features.typography?.fontFamilies;
        expect(Array.isArray(fontFamilies?.theme)).toBe(true);
        expect((fontFamilies?.theme as unknown[]).length).toBeGreaterThan(0);
        expect(fontFamilies?.custom).toEqual([]);
    });
});

describe('extractThemeGradients', () => {
    it('returns null when settings has no color key', () => {
        expect(extractThemeGradients({})).toBeNull();
    });

    it('returns null when gradients is an empty array', () => {
        expect(extractThemeGradients({ color: { gradients: [] } })).toBeNull();
    });

    it('extracts valid gradient entries', () => {
        const settings = {
            color: {
                gradients: [
                    {
                        slug: 'sunset',
                        name: 'Sunset',
                        gradient: 'linear-gradient(135deg, #f97316, #ef4444)',
                    },
                    {
                        slug: 'ocean',
                        gradient: 'linear-gradient(135deg, #3b82f6, #06b6d4)',
                    },
                ],
            },
        };

        const result = extractThemeGradients(settings);
        expect(result).toEqual([
            {
                slug: 'sunset',
                name: 'Sunset',
                gradient: 'linear-gradient(135deg, #f97316, #ef4444)',
            },
            {
                slug: 'ocean',
                name: 'ocean',
                gradient: 'linear-gradient(135deg, #3b82f6, #06b6d4)',
            },
        ]);
    });
});
