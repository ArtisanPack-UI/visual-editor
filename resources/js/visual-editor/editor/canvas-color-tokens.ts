/**
 * Canvas surface color bridge — #695.
 *
 * `canvas-theme-tokens.css` paints the iframe body through two
 * package-owned custom properties:
 *
 *     body.editor-styles-wrapper {
 *         background-color: var(--ap-editor-canvas-bg, #ffffff);
 *         color: var(--ap-editor-canvas-fg, #1f2937);
 *     }
 *
 * Nothing ever assigned those variables, so both resolved to the light
 * fallbacks. Because that selector is an element+class compound
 * (specificity 0,1,1) it also beats the theme's bare
 * `.editor-styles-wrapper` rule (0,1,0) regardless of source order — so
 * a dark theme.json painted a white canvas with dark body text no theme
 * sheet could correct.
 *
 * `DEFAULT_CANVAS_STYLES` has the same shape of problem one level down:
 * its `.editor-styles-wrapper h1..h6` rule (also 0,1,1) hardcodes
 * `#111827`, out-specifying a theme's bare `h1`..`h6` selectors. Fixing
 * only the ground would therefore have *moved* the bug — a dark canvas
 * with headings pinned to near-black against it.
 *
 * This module closes both gaps by deriving three variables from the
 * resolved theme.json `styles` node and emitting them as one `:root`
 * rule appended to the canvas stylesheet list:
 *
 *   - `--ap-editor-canvas-bg`         ← `styles.color.background`
 *   - `--ap-editor-canvas-fg`         ← `styles.color.text`
 *   - `--ap-editor-canvas-heading-fg` ← `styles.elements.heading` /
 *                                       `h1`..`h6` (see below)
 *
 * The painting rules themselves are unchanged, so:
 *
 *   - a theme declaring no colors leaves every variable unset and keeps
 *     the existing `#ffffff` / `#1f2937` / `#111827` defaults;
 *   - a theme that styles headings without a color leaves the heading
 *     variable unset, so headings inherit the canvas foreground rather
 *     than the light default;
 *   - a host rule on `body` / `.editor-styles-wrapper` still out-specifies
 *     this `:root` declaration and keeps its override.
 *
 * @package @artisanpack-ui/visual-editor
 * @since   1.6.0
 */

import type { CanvasStyle } from './canvas-styles';

/**
 * Shape a theme.json color value has to match to be interpolated into
 * the emitted rule.
 *
 * This is a robustness guard, NOT a security boundary — theme.json is
 * authored by the site's own theme, and the compiled theme stylesheet
 * appended right after this entry in `editor-canvas.tsx` is arbitrary
 * CSS from that same trust boundary. What this buys is a predictable
 * failure mode: a typo'd value drops out and leaves its variable unset
 * (so the `var()` fallbacks paint a readable canvas) rather than
 * silently nullifying the whole `:root` rule.
 *
 * An allowlist rather than a denylist of `{};<>`, because two shapes
 * slip past a denylist and take the entire rule down with them: an
 * unterminated comment (`#111 /*`) and an unbalanced paren
 * (`var(--x`) each swallow the rest of the stylesheet entry. The class
 * below admits every shape this module documents — `#111827`,
 * `rebeccapurple`, `var(--wp--preset--color--base)`,
 * `rgb(0 0 0 / 50%)`, `color-mix(in srgb, …)` — and nothing else.
 * Note `*` is absent, so `/*` can never form.
 */
const ALLOWED_VALUE = /^[#\w(),.%/\s-]+$/;

/**
 * Whether every `(` in a value is closed, so an interpolated value
 * can't swallow the declarations that follow it.
 */
function hasBalancedParens(value: string): boolean {
    let depth = 0;

    for (const char of value) {
        if (char === '(') {
            depth += 1;
        } else if (char === ')') {
            depth -= 1;

            if (depth < 0) {
                return false;
            }
        }
    }

    return depth === 0;
}

/**
 * Read a CSS color value out of a theme.json `styles.color` node.
 * Returns `null` for anything that isn't a usable, safe string so the
 * caller can leave the corresponding variable unset.
 */
function readColorValue(color: Record<string, unknown>, key: string): string | null {
    const raw = color[key];

    if (typeof raw !== 'string') {
        return null;
    }

    const value = raw.trim();

    if (value === '' || !ALLOWED_VALUE.test(value) || !hasBalancedParens(value)) {
        return null;
    }

    return value;
}

/** Heading element keys a theme.json may carry a color on. */
const HEADING_KEYS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as const;

/**
 * Read `elements.<key>.color.text` out of a theme.json `styles` node.
 */
function readElementTextColor(
    elements: Record<string, unknown>,
    key: string
): string | null {
    const element = elements[key];

    if (element === null || typeof element !== 'object' || Array.isArray(element)) {
        return null;
    }

    const color = (element as { color?: unknown }).color;

    if (color === null || typeof color !== 'object' || Array.isArray(color)) {
        return null;
    }

    return readColorValue(color as Record<string, unknown>, 'text');
}

/**
 * Resolve the heading color for the canvas baseline (#695).
 *
 * The package's `DEFAULT_CANVAS_STYLES` heading rule out-specifies a
 * theme's bare `h1`..`h6` selectors, so headings have to arrive through
 * a variable like the body colors do. Prefer the `heading` element
 * group; fall back to `h1`..`h6` only when every level a theme declares
 * agrees, since one variable cannot express divergent per-level colors
 * — in that case the caller leaves it unset and headings inherit the
 * canvas foreground.
 */
function readHeadingColor(themeStyles: Record<string, unknown>): string | null {
    const elements = themeStyles.elements;

    if (elements === null || typeof elements !== 'object' || Array.isArray(elements)) {
        return null;
    }

    const node = elements as Record<string, unknown>;
    const group = readElementTextColor(node, 'heading');

    if (group !== null) {
        return group;
    }

    const declared = HEADING_KEYS.map((key) => readElementTextColor(node, key)).filter(
        (value): value is string => value !== null
    );

    if (declared.length === 0) {
        return null;
    }

    return declared.every((value) => value === declared[0]) ? declared[0] : null;
}

/**
 * Build the `:root` rule supplying the canvas color tokens from the
 * resolved theme.json `styles` node (the `styles` half of the
 * `/global-styles/base` payload).
 *
 * Returns `null` when the theme declares none of them, and omits any
 * individual declaration it can't resolve — an unresolved variable must
 * stay *unset* rather than be assigned an empty string, so the `var()`
 * fallbacks in `canvas-theme-tokens.css` and `DEFAULT_CANVAS_STYLES`
 * still apply.
 *
 * Values are passed through verbatim: theme.json commonly stores
 * `var(--wp--preset--color--base)` references, and cms-framework's
 * `GlobalStylesEmitter` defines those presets on `:root` inside the same
 * document, so they resolve without further work.
 */
export function buildCanvasColorTokenCss(
    themeStyles: Record<string, unknown> | undefined
): string | null {
    if (themeStyles === undefined) {
        return null;
    }

    const node = themeStyles.color;
    // A theme may declare heading colors without declaring canvas ones,
    // so an absent / malformed `color` node isn't an early exit.
    const color: Record<string, unknown> =
        node !== null && typeof node === 'object' && !Array.isArray(node)
            ? (node as Record<string, unknown>)
            : {};

    const background = readColorValue(color, 'background');
    const text = readColorValue(color, 'text');
    const heading = readHeadingColor(themeStyles);

    if (background === null && text === null && heading === null) {
        return null;
    }

    const declarations: string[] = [];

    if (background !== null) {
        declarations.push(`    --ap-editor-canvas-bg: ${background};`);
    }

    if (text !== null) {
        declarations.push(`    --ap-editor-canvas-fg: ${text};`);
    }

    if (heading !== null) {
        declarations.push(`    --ap-editor-canvas-heading-fg: ${heading};`);
    }

    return `:root {\n${declarations.join('\n')}\n}\n`;
}

/**
 * {@link buildCanvasColorTokenCss} wrapped in the `{ css }` entry shape
 * `BlockCanvas`'s `styles` prop expects. `null` when the theme supplies
 * no canvas colors, so callers can skip appending an entry entirely.
 *
 * @see canvasStyles — the base list this entry is appended to.
 */
export function canvasColorTokenStyle(
    themeStyles: Record<string, unknown> | undefined
): CanvasStyle | null {
    const css = buildCanvasColorTokenCss(themeStyles);

    return css === null ? null : { css };
}
