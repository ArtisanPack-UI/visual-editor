/**
 * Spacing preset custom properties for the editor canvas (#814).
 *
 * The spacing controls (padding, margin, Block spacing) store a picked
 * preset as `var:preset|spacing|{slug}`, which Gutenberg renders as
 * `var(--wp--preset--spacing--{slug})`. Those custom properties were only
 * ever declared by the active theme's compiled `theme.json` CSS, so on a
 * theme that ships no `spacingSizes` — where the pickers fall back to the
 * package defaults (`DEFAULT_SPACING_SIZES`) — every preset resolved to an
 * undefined variable and the declaration was dropped.
 *
 * This module declares the variables from the spacing sizes the editor
 * actually has in effect (`__experimentalFeatures.spacing.spacingSizes`,
 * i.e. theme or package defaults, plus host presets), so whatever the
 * picker offers always resolves. The Blade renderer mirrors the same list
 * on the front end via `ThemeJsonTokensCompiler::compileSpacingPresets()`.
 *
 * Scoped to `.editor-styles-wrapper` to match the other canvas sheets —
 * inside the iframe that is the body, inline it is the canvas surface.
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useMemo, type ReactElement } from 'react';

interface SpacingSizeLike {
    slug?: unknown;
    size?: unknown;
}

/** Origins in cascade order — later origins win for a shared slug. */
const ORIGINS = ['default', 'theme', 'custom'] as const;

/**
 * Characters / digraphs that could close the declaration, rule, or
 * `<style>` element, open or close a comment that swallows the following
 * rules, smuggle a CSS escape, open a string, or break the line. Preset
 * values are author/host configured, but they land inside a `<style>`
 * tag, so any value carrying one is skipped.
 */
// eslint-disable-next-line no-control-regex
const UNSAFE_VALUE = /[;{}<>\\"'\u0000-\u001f\u007f]|\/\*|\*\//;

/**
 * Whether a preset value is safe to write inside a `<style>` element:
 * none of the {@link UNSAFE_VALUE} characters, and balanced parentheses
 * (`calc(1rem` would leave a function open and swallow every later
 * rule). Mirrors `PresetRegistry::isSafeCssValue()`.
 */
export function isSafeSpacingValue(value: string): boolean {
    if (value === '' || UNSAFE_VALUE.test(value)) {
        return false;
    }

    let depth = 0;

    for (const char of value) {
        if (char === '(') {
            depth++;
        } else if (char === ')') {
            depth--;

            if (depth < 0) {
                return false;
            }
        }
    }

    return depth === 0;
}

/**
 * Normalise a spacing preset slug into its custom-property segment — the
 * one rule every declaration and reference site shares: split lower→upper
 * camelCase boundaries, lowercase, then every character outside
 * `[a-z0-9-]` becomes `-` (`big_gap` → `big-gap`). Runs of `-` are kept
 * and nothing is trimmed. Mirrors `PresetRegistry::presetSlug()`, the
 * Blade `BlockSupports` / `ElementsSupport` reference expanders, and the
 * React / Vue renderers' `presetSlug()`.
 */
export function spacingPresetSlug(value: string): string {
    return value
        .replace(/([a-z])([A-Z])/g, '$1-$2')
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '-');
}

/**
 * Split rules `@wordpress/style-engine`'s `getCSSValueFromRawStyle()`
 * hands to change-case's `paramCase` when it expands a
 * `var:preset|spacing|{slug}` padding / margin value on the canvas.
 */
const STYLE_ENGINE_SPLIT = [
    /([a-z0-9])([A-Z])/g,
    /([0-9])([a-z])/g,
    /([A-Za-z])([0-9])/g,
    /([A-Z])([A-Z][a-z])/g,
];

/**
 * The slug segment Gutenberg's style engine references for a preset: it
 * also splits digit/letter boundaries (`2xl` → `2-xl`, `spacing10` →
 * `spacing-10`), collapses non-alphanumeric runs and trims dashes. That
 * differs from the name every declaration uses, so the canvas declares
 * this form as well whenever it differs — otherwise canvas padding /
 * margin picked from such a preset referenced an undeclared property.
 * Verified against the real style engine in the unit tests.
 */
export function styleEngineSpacingSlug(value: string): string {
    const split = STYLE_ENGINE_SPLIT.reduce((out, pattern) => out.replace(pattern, '$1\0$2'), value);

    return split
        .replace(/[^A-Za-z0-9]+/g, '\0')
        .split('\0')
        .filter((word) => word !== '')
        .map((word) => word.toLowerCase())
        .join('-');
}

/**
 * Build the `.editor-styles-wrapper { --wp--preset--spacing--*: … }` rule
 * for a list of spacing sizes. Returns `''` when no entry is usable.
 */
export function buildSpacingPresetCss(sizes: ReadonlyArray<SpacingSizeLike>): string {
    const declarations = new Map<string, string>();

    for (const entry of sizes) {
        if (typeof entry?.slug !== 'string' || typeof entry.size !== 'string') {
            continue;
        }

        const slug = spacingPresetSlug(entry.slug);
        const size = entry.size.trim();

        if (slug === '' || !isSafeSpacingValue(size)) {
            continue;
        }

        declarations.set(slug, size);

        const engineSlug = styleEngineSpacingSlug(entry.slug);

        if (engineSlug !== '' && engineSlug !== slug) {
            declarations.set(engineSlug, size);
        }
    }

    if (declarations.size === 0) {
        return '';
    }

    const body = Array.from(declarations, ([slug, size]) => `--wp--preset--spacing--${slug}: ${size};`);

    return `.editor-styles-wrapper { ${body.join(' ')} }`;
}

/**
 * Flatten the editor's per-origin `spacingSizes` record (or a bare list)
 * into one list, default → theme → custom.
 */
export function flattenSpacingSizes(spacingSizes: unknown): SpacingSizeLike[] {
    if (Array.isArray(spacingSizes)) {
        return spacingSizes as SpacingSizeLike[];
    }

    if (spacingSizes === null || typeof spacingSizes !== 'object') {
        return [];
    }

    const byOrigin = spacingSizes as Record<string, unknown>;

    return ORIGINS.flatMap((origin) => {
        const list = byOrigin[origin];

        return Array.isArray(list) ? (list as SpacingSizeLike[]) : [];
    });
}

/**
 * Read the in-effect spacing sizes from the block-editor settings and
 * return the canvas rule for them.
 */
export function useSpacingPresetCss(): string {
    const spacingSizes = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (select: any) =>
            select(blockEditorStore).getSettings()?.__experimentalFeatures?.spacing?.spacingSizes,
        []
    );

    return useMemo(() => buildSpacingPresetCss(flattenSpacingSizes(spacingSizes)), [spacingSizes]);
}

/**
 * Inline `<style>` for canvases that render in the parent document (the
 * site editor's entity and pattern canvases). The iframe-backed post
 * editor passes {@see useSpacingPresetCss} through `BlockCanvas` instead.
 */
export function SpacingPresetStyles(): ReactElement | null {
    const css = useSpacingPresetCss();

    if (css === '') {
        return null;
    }

    return <style data-testid="ap-canvas-spacing-presets">{css}</style>;
}
