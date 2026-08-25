/**
 * FontFamilyPicker — unit tests (#636).
 *
 * Verifies that the picker merges theme presets with installed fonts from the
 * store slice, applies the `var(--wp--preset--font-family--{slug})` preset value
 * on selection, de-duplicates a preset that collides with an installed font, and
 * falls back to the free-text custom escape hatch for an out-of-list value.
 *
 * @since 1.7.0
 */

import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { FontFamilyOption } from '../../../../fonts/installed-fonts-store';
import {
    resetInstalledFontsStore,
    setInstalledFonts,
} from '../../../../fonts/installed-fonts-store';
import { FontFamilyPicker } from '../font-family-picker';

const PRESET_OPTIONS: readonly FontFamilyOption[] = [
    { slug: 'sans', label: 'Sans', value: 'var(--wp--preset--font-family--sans)' },
];

beforeEach(() => {
    resetInstalledFontsStore();
});

afterEach(() => {
    vi.restoreAllMocks();
});

function seedInstalled(): void {
    setInstalledFonts([
        {
            id: 1,
            provider: 'google',
            family: 'Inter',
            slug: 'inter',
            is_variable: false,
            license: null,
            source_url: null,
            installed_at: null,
            faces: [],
        },
    ]);
}

function selectTestId(): string {
    return 'ap-site-editor-style-field-select-font-family';
}

describe('FontFamilyPicker', () => {
    it('lists theme presets, installed fonts, and a custom option', () => {
        seedInstalled();

        render(
            <FontFamilyPicker
                label="Font family"
                value=""
                presetOptions={PRESET_OPTIONS}
                onChange={vi.fn()}
                testId="font-family"
            />
        );

        const select = screen.getByTestId(selectTestId()) as HTMLSelectElement;
        const labels = Array.from(select.options).map((option) => option.textContent);

        expect(labels).toContain('Sans');
        expect(labels).toContain('Inter');
        expect(labels).toContain('Custom…');
    });

    it('applies the preset var when an installed font is picked', () => {
        seedInstalled();
        const onChange = vi.fn();

        render(
            <FontFamilyPicker
                label="Font family"
                value=""
                presetOptions={PRESET_OPTIONS}
                onChange={onChange}
                testId="font-family"
            />
        );

        fireEvent.change(screen.getByTestId(selectTestId()), {
            target: { value: 'var(--wp--preset--font-family--inter)' },
        });

        expect(onChange).toHaveBeenCalledWith(
            'var(--wp--preset--font-family--inter)'
        );
    });

    it('de-duplicates a preset that matches an installed font', () => {
        setInstalledFonts([
            {
                id: 2,
                provider: 'google',
                family: 'Sans',
                slug: 'sans',
                is_variable: false,
                license: null,
                source_url: null,
                installed_at: null,
                faces: [],
            },
        ]);

        render(
            <FontFamilyPicker
                label="Font family"
                value=""
                presetOptions={PRESET_OPTIONS}
                onChange={vi.fn()}
                testId="font-family"
            />
        );

        const select = screen.getByTestId(selectTestId()) as HTMLSelectElement;
        const sansCount = Array.from(select.options).filter(
            (option) => option.value === 'var(--wp--preset--font-family--sans)'
        ).length;

        expect(sansCount).toBe(1);
    });

    it('shows the custom text input for an out-of-list value', () => {
        const onChange = vi.fn();

        render(
            <FontFamilyPicker
                label="Font family"
                value="system-ui, sans-serif"
                presetOptions={PRESET_OPTIONS}
                onChange={onChange}
                testId="font-family"
            />
        );

        const select = screen.getByTestId(selectTestId()) as HTMLSelectElement;
        expect(select.value).toBe('__custom__');

        const custom = screen.getByTestId(
            'ap-site-editor-style-field-custom-font-family'
        );
        expect(custom).toBeInTheDocument();

        fireEvent.change(custom, { target: { value: 'Georgia, serif' } });
        expect(onChange).toHaveBeenCalledWith('Georgia, serif');
    });

    it('clears the value when the custom option is chosen', () => {
        const onChange = vi.fn();

        render(
            <FontFamilyPicker
                label="Font family"
                value="var(--wp--preset--font-family--sans)"
                presetOptions={PRESET_OPTIONS}
                onChange={onChange}
                testId="font-family"
            />
        );

        fireEvent.change(screen.getByTestId(selectTestId()), {
            target: { value: '__custom__' },
        });

        expect(onChange).toHaveBeenCalledWith('');
    });
});
