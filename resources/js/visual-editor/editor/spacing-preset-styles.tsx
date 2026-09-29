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
 * `<style>` element, open a comment that swallows the following rules,
 * or smuggle a CSS escape. Preset values are author/host configured, but
 * they land inside a `<style>` tag, so any value carrying one is skipped.
 * Mirrors `PresetRegistry::spacingPresetsCss()`.
 */
const UNSAFE_VALUE = /[;{}<>\\]|\/\*|\*\//;

/**
 * Normalise a spacing preset slug into its custom-property segment.
 * Mirrors `ThemeJsonTokensCompiler::slug()` on the Blade side and the
 * kebab-casing Gutenberg's style engine applies to padding / margin
 * presets (`big_gap` → `big-gap`), so every surface names the property
 * the same way.
 */
export function spacingPresetSlug(value: string): string {
    return value.toLowerCase().replace(/[^a-z0-9-]/g, '-');
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

        if (slug === '' || size === '' || UNSAFE_VALUE.test(size)) {
            continue;
        }

        declarations.set(slug, size);
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
