/**
 * Preset data hook.
 *
 * Reads the current palette / font-family / font-size preset lists off
 * the editor draft so each panel can seed its WP-style picker controls
 * without re-implementing the same shape-mapping over and over.
 *
 * Kept as a hook (not a util) so the memoization can key on the editor
 * draft identity — panel renders see stable preset arrays when nothing
 * about the registered presets has changed.
 */

import { useMemo } from 'react';

import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import type { PaletteSwatch } from './color-value-field';
import type { PresetOption } from './preset-select-field';

export interface StylePresets {
    palette: readonly PaletteSwatch[];
    fontFamilies: readonly PresetOption[];
    fontSizes: readonly PresetOption[];
}

function readPaletteEntries(
    editor: UseGlobalStylesEditorResult
): readonly PaletteSwatch[] {
    const raw = editor.readValue(['settings', 'color', 'palette']);

    if (!Array.isArray(raw)) {
        return [];
    }

    const result: PaletteSwatch[] = [];

    for (const entry of raw) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const row = entry as Record<string, unknown>;
        const slug = typeof row.slug === 'string' ? row.slug : null;
        const name =
            typeof row.name === 'string' ? row.name : slug ?? null;
        const color = typeof row.color === 'string' ? row.color : null;

        if (slug === null || name === null || color === null) {
            continue;
        }

        result.push({ slug, name, color });
    }

    return result;
}

function readFontFamilyPresets(
    editor: UseGlobalStylesEditorResult
): readonly PresetOption[] {
    const raw = editor.readValue([
        'settings',
        'typography',
        'fontFamilies',
    ]);

    if (!Array.isArray(raw)) {
        return [];
    }

    const result: PresetOption[] = [];

    for (const entry of raw) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const row = entry as Record<string, unknown>;
        const slug = typeof row.slug === 'string' ? row.slug : null;
        const name =
            typeof row.name === 'string' ? row.name : slug ?? null;

        if (slug === null || name === null) {
            continue;
        }

        result.push({
            slug,
            label: name,
            value: `var(--wp--preset--font-family--${slug})`,
        });
    }

    return result;
}

function readFontSizePresets(
    editor: UseGlobalStylesEditorResult
): readonly PresetOption[] {
    const raw = editor.readValue([
        'settings',
        'typography',
        'fontSizes',
    ]);

    if (!Array.isArray(raw)) {
        return [];
    }

    const result: PresetOption[] = [];

    for (const entry of raw) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const row = entry as Record<string, unknown>;
        const slug = typeof row.slug === 'string' ? row.slug : null;
        const name =
            typeof row.name === 'string' ? row.name : slug ?? null;
        const size =
            typeof row.size === 'string' ? (row.size as string) : null;

        if (slug === null || name === null) {
            continue;
        }

        const labelParts: string[] = [name];

        if (size !== null) {
            labelParts.push(`· ${size}`);
        }

        result.push({
            slug,
            label: labelParts.join(' '),
            value: `var(--wp--preset--font-size--${slug})`,
        });
    }

    return result;
}

export function useStylePresets(
    editor: UseGlobalStylesEditorResult
): StylePresets {
    // Key memos on the editor draft identity — every edit rebuilds the
    // draft reference so panel-side memos see a fresh list, but idle
    // renders reuse the previous arrays.
    return useMemo(
        () => ({
            palette: readPaletteEntries(editor),
            fontFamilies: readFontFamilyPresets(editor),
            fontSizes: readFontSizePresets(editor),
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [editor.draft]
    );
}
