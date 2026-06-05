/**
 * Unit tests for the theme-settings extraction helpers (#512).
 *
 * The hook itself (`useThemedEditorSettings`) depends on React hooks
 * and async fetch stubs. The pure extraction functions are tested here
 * to validate the shape → normalized-array mapping logic without
 * needing a React render harness.
 */

import { describe, expect, it } from 'vitest';

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
