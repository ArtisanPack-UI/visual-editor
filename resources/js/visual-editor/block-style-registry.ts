/**
 * Theme block style variations (#876) — reads the variations the server
 * resolved (theme.json `styles.blocks.*.variations`, `styles/*.json`
 * partials with `blockTypes`, and `VisualEditor::registerBlockStyle()`)
 * from the editor mount's `data-block-styles` attribute and registers
 * each one into the native Styles picker with `registerBlockStyle`.
 *
 * Only the picker entry is registered here. The `.is-style-*` CSS
 * reaches the canvas through the global-styles stylesheet
 * (`GET /global-styles/css`), the same rules the front end gets.
 *
 * A theme names blocks the WordPress way (`core/button`) while the
 * editor registers first-party forks (`artisanpack/button`), so every
 * variation is registered under both names. A slug the block already
 * offers (e.g. a block.json `styles` entry) is left alone.
 *
 * @since 1.15.0
 */

import { registerBlockStyle, store as blocksStore } from '@wordpress/blocks';
import { select } from '@wordpress/data';

export interface ThemeBlockStyle {
    readonly name: string;
    readonly slug: string;
    readonly label: string;
    readonly isDefault: boolean;
}

const BLOCK_NAME = /^[a-z0-9-]+\/[a-z0-9-]+$/;
const SLUG = /^[a-z0-9-]+$/;

let cached: readonly ThemeBlockStyle[] | null = null;

/**
 * Parse the `data-block-styles` JSON, dropping malformed entries.
 */
export function parseBlockStyles(raw: string | undefined | null): ThemeBlockStyle[] {
    if (!raw || !raw.trim()) {
        return [];
    }

    let decoded: unknown;
    try {
        decoded = JSON.parse(raw);
    } catch {
        return [];
    }

    if (!Array.isArray(decoded)) {
        return [];
    }

    const styles: ThemeBlockStyle[] = [];

    for (const entry of decoded) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }
        const { name, slug, label, isDefault } = entry as Record<string, unknown>;
        if (typeof name !== 'string' || !BLOCK_NAME.test(name)) {
            continue;
        }
        if (typeof slug !== 'string' || !SLUG.test(slug)) {
            continue;
        }
        styles.push({
            name,
            slug,
            label: typeof label === 'string' && label.trim() !== '' ? label : slug,
            isDefault: isDefault === true,
        });
    }

    return styles;
}

/**
 * The block names a variation is registered under: the given name plus
 * its `core/*` ↔ `artisanpack/*` counterpart.
 */
export function blockNamesFor(name: string): string[] {
    const [namespace, block] = name.split('/');
    if (namespace === 'core') {
        return [name, `artisanpack/${block}`];
    }
    if (namespace === 'artisanpack') {
        return [name, `core/${block}`];
    }
    return [name];
}

function parseFromElement(element: Element | null): ThemeBlockStyle[] | null {
    if (element === null || !(element instanceof HTMLElement)) {
        return null;
    }
    const raw = element.dataset.blockStyles;
    return raw === undefined ? null : parseBlockStyles(raw);
}

/**
 * The variations stamped onto the post-editor or site-editor mount.
 */
export function getThemeBlockStyles(): readonly ThemeBlockStyle[] {
    if (cached !== null) {
        return cached;
    }

    if (typeof document === 'undefined') {
        cached = [];
        return cached;
    }

    cached =
        parseFromElement(document.querySelector('[data-ap-visual-editor]')) ??
        parseFromElement(document.querySelector('[data-ap-site-editor]')) ??
        [];
    return cached;
}

/**
 * Register every theme variation into the Styles picker. Call after the
 * editor's blocks are registered; safe to call more than once.
 */
export function registerThemeBlockStyles(styles: readonly ThemeBlockStyle[] = getThemeBlockStyles()): void {
    for (const style of styles) {
        for (const blockName of blockNamesFor(style.name)) {
            const existing = (select(blocksStore) as {
                getBlockStyles: (name: string) => Array<{ name: string }> | undefined;
            }).getBlockStyles(blockName);

            if (existing?.some((entry) => entry.name === style.slug)) {
                continue;
            }

            registerBlockStyle(blockName, {
                name: style.slug,
                label: style.label,
                isDefault: style.isDefault,
            });
        }
    }
}

/**
 * Clear the cached snapshot. Tests only.
 */
export function refreshThemeBlockStyles(): void {
    cached = null;
}
