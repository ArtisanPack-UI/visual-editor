/**
 * Block effects + Custom CSS — React renderer (#881).
 *
 * Mirrors `EffectsCompiler` and `CustomCssSanitizer` on the PHP side: a
 * block's `artisanpackEffects` attribute becomes a hashed
 * `ve-fx-<12-char-sha1>` scope class and a rule that carries the effects
 * and the block's Custom CSS (nested inside the rule with native CSS
 * nesting). The scope is hashed with {@link sha1Hex} exactly as Blade
 * hashes it, so the class matches byte-for-byte (kept honest by the #704
 * markup-parity check). Identical blocks share one rule.
 *
 * The mirror lives at the matching path in the Vue renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-react
 * @since 1.15.0
 */

import { sha1Hex } from './sha1';
import type { Block } from '../types';

export const EFFECTS_ATTRIBUTE = 'artisanpackEffects';

export const EFFECTS_SCOPE_PREFIX = 've-fx-';

export const CUSTOM_CSS_MAX_LENGTH = 10000;

const ALLOWED_AT_RULES = ['media', 'supports', 'container'];

const SCRIPT_VECTORS = ['expression(', 'javascript:', '-moz-binding', 'behavior:'];

/**
 * Functions that load a resource, rejected when URLs are disallowed.
 * Mirrors `CustomCssSanitizer::URL_FUNCTIONS`.
 */
export const CUSTOM_CSS_URL_FUNCTIONS = ['url(', 'image-set(', 'image(', 'src('];

/** Closing character for each opening brace, parenthesis and bracket. */
const PAIRS: Record<string, string> = { '{': '}', '(': ')', '[': ']' };

/** Blank as PHP's `trim()` sees it: ASCII whitespace and NUL only. */
// eslint-disable-next-line no-control-regex
const PHP_BLANK = /^[ \t\n\r\0\x0B]*$/;

export const OVERFLOW_VALUES = ['visible', 'hidden', 'clip', 'scroll', 'auto'];

export const BLEND_MODES = [
    'normal',
    'multiply',
    'screen',
    'overlay',
    'darken',
    'lighten',
    'color-dodge',
    'color-burn',
    'hard-light',
    'soft-light',
    'difference',
    'exclusion',
    'hue',
    'saturation',
    'color',
    'luminosity',
];

export const CLIP_SHAPES: Record<string, string> = {
    circle: 'circle(50% at 50% 50%)',
    ellipse: 'ellipse(50% 50% at 50% 50%)',
    triangle: 'polygon(50% 0%, 100% 100%, 0% 100%)',
    diamond: 'polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)',
    hexagon: 'polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)',
    slant: 'polygon(0% 0%, 100% 0%, 100% 85%, 0% 100%)',
};

/**
 * Sanitize a block's Custom CSS. Returns the normalised CSS, `''` for an
 * empty value, or `null` when the value is rejected. Mirrors
 * `CustomCssSanitizer::sanitize()`.
 *
 * @param css       The raw Custom CSS.
 * @param allowUrls Whether the CSS may load resources
 *                  ({@link CUSTOM_CSS_URL_FUNCTIONS}). Quoted `url("…")`
 *                  is required either way.
 */
export function sanitizeCustomCss(css: unknown, allowUrls = true): string | null {
    if (typeof css !== 'string' || PHP_BLANK.test(css)) {
        return '';
    }

    if ([...css].length > CUSTOM_CSS_MAX_LENGTH) {
        return null;
    }

    // eslint-disable-next-line no-control-regex
    if (/[<\\\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(css)) {
        return null;
    }

    const lower = asciiLower(css);

    if (SCRIPT_VECTORS.some((vector) => lower.includes(vector))) {
        return null;
    }

    let out = '';
    let quote: string | null = null;
    const open: string[] = [];
    let pending = false;

    for (let i = 0; i < css.length; i++) {
        const char = css[i];

        if (quote !== null) {
            if (char === '\n' || char === '\r') {
                return null;
            }

            out += char;

            if (char === quote) {
                quote = null;
            }

            continue;
        }

        if (char === '/' && css[i + 1] === '*') {
            const end = css.indexOf('*/', i + 2);

            if (end === -1) {
                return null;
            }

            i = end + 1;
            pending = true;

            continue;
        }

        if (char === ' ' || char === '\t' || char === '\n' || char === '\r') {
            pending = true;

            continue;
        }

        if (pending && out !== '') {
            out += ' ';
        }

        pending = false;

        // Inside an unquoted `url(…)` CSS reads braces, semicolons and
        // quotes as plain text, so only the quoted form is safe.
        const ahead = asciiLower(css.slice(i, i + 10));

        if (ahead.startsWith('url(') && !/^[ \t\n\r]*['"]/.test(css.slice(i + 4))) {
            return null;
        }

        if (!allowUrls && CUSTOM_CSS_URL_FUNCTIONS.some((fn) => ahead.startsWith(fn))) {
            return null;
        }

        if (char === '"' || char === "'") {
            quote = char;
        } else if (Object.prototype.hasOwnProperty.call(PAIRS, char)) {
            open.push(PAIRS[char]);
        } else if (char === '}' || char === ')' || char === ']') {
            if (open.pop() !== char) {
                return null;
            }
        } else if (char === '@') {
            const name = asciiLower((/^[a-zA-Z-]*/.exec(css.slice(i + 1)) ?? [''])[0]);

            if (!ALLOWED_AT_RULES.includes(name)) {
                return null;
            }
        }

        out += char;
    }

    if (quote !== null || open.length !== 0) {
        return null;
    }

    return out;
}

/**
 * Whether a block paints a gradient or a background image — what text
 * clip needs to show through. Reads the `gradient` preset,
 * `style.color.gradient` and `style.background.backgroundImage`.
 * Mirrors `EffectsCompiler::hasBackgroundFill()`.
 */
export function hasBackgroundFill(attributes: Record<string, unknown> | null | undefined): boolean {
    const style = asRecord(attributes?.style);
    const image = asRecord(style.background).backgroundImage;
    const url = typeof image === 'string' ? image : asRecord(image).url;

    return filled(attributes?.gradient) || filled(asRecord(style.color).gradient) || filled(url);
}

/**
 * The CSS declarations for an effects payload, in a fixed order. Mirrors
 * `EffectsCompiler::declarations()`.
 *
 * @param effects The `artisanpackEffects` payload.
 * @param hasFill Whether the block paints a gradient or background image;
 *                text clip is dropped without one.
 */
export function effectsDeclarations(effects: Record<string, unknown>, hasFill = false): string[] {
    const declarations: string[] = [];

    const opacity = integer(effects.opacity, 0, 100);

    if (opacity !== null) {
        declarations.push(`opacity:${ratio(opacity / 100)}`);
    }

    for (const [key, property] of [['overflowX', 'overflow-x'], ['overflowY', 'overflow-y']]) {
        const value = keyword(effects[key], OVERFLOW_VALUES);

        if (value !== null) {
            declarations.push(`${property}:${value}`);
        }
    }

    const filters: string[] = [];
    const blur = integer(effects.backdropBlur, 0, 200);
    const saturate = integer(effects.backdropSaturate, 0, 500);

    if (blur !== null) {
        filters.push(`blur(${blur}px)`);
    }

    if (saturate !== null) {
        filters.push(`saturate(${saturate}%)`);
    }

    if (filters.length > 0) {
        declarations.push(`-webkit-backdrop-filter:${filters.join(' ')}`);
        declarations.push(`backdrop-filter:${filters.join(' ')}`);
    }

    const blend = keyword(effects.mixBlendMode, BLEND_MODES);

    if (blend !== null) {
        declarations.push(`mix-blend-mode:${blend}`);
    }

    if (effects.isolation === true) {
        declarations.push('isolation:isolate');
    }

    const zIndex = integer(effects.zIndex, -99999, 99999);

    if (zIndex !== null) {
        declarations.push(`z-index:${zIndex}`);
    }

    const clip = effects.clipShape;

    if (typeof clip === 'string' && Object.prototype.hasOwnProperty.call(CLIP_SHAPES, clip)) {
        declarations.push(`clip-path:${CLIP_SHAPES[clip]}`);
    }

    // Without a gradient or image behind it, transparent text would
    // simply vanish, so text clip needs the caller to confirm one.
    if (effects.textClip === true && hasFill) {
        declarations.push('-webkit-background-clip:text');
        declarations.push('background-clip:text');
        declarations.push('-webkit-text-fill-color:transparent');
    }

    return declarations;
}

/**
 * Options for {@link compileEffects}.
 */
export interface CompileEffectsOptions {
    /**
     * Whether the block paints a gradient or background image for text
     * clip to show through. Omit to read it from the attributes
     * ({@link hasBackgroundFill}).
     */
    hasBackgroundFill?: boolean;
    /**
     * Whether Custom CSS may load resources (`url(`, `image-set(`, …),
     * as config `effects.custom_css_allow_urls`. Defaults to `true`.
     */
    allowCustomCssUrls?: boolean;
}

/**
 * Compile a block's effects into a scope class and its CSS, or `null`
 * when it sets none. Mirrors `EffectsCompiler::compile()`.
 */
export function compileEffects(
    attributes: Record<string, unknown> | null | undefined,
    options: CompileEffectsOptions = {}
): { className: string; css: string } | null {
    const effects = attributes?.[EFFECTS_ATTRIBUTE];

    if (effects === null || typeof effects !== 'object' || Array.isArray(effects)) {
        return null;
    }

    const record = effects as Record<string, unknown>;
    const hasFill = options.hasBackgroundFill ?? hasBackgroundFill(attributes);
    const declarations = effectsDeclarations(record, hasFill);
    const customCss = sanitizeCustomCss(record.customCss, options.allowCustomCssUrls ?? true) ?? '';

    if (declarations.length === 0 && customCss === '') {
        return null;
    }

    const body = declarations.join(';');
    const className = EFFECTS_SCOPE_PREFIX + sha1Hex(`${body}\n${customCss}`).slice(0, 12);
    const inner = [body, customCss].filter((part) => part !== '').join(';');
    let css = `.${className}{${inner}}`;

    if (integer(record.zIndex, -99999, 99999) !== null) {
        css += `:where(.${className}){position:relative}`;
    }

    // Forced-colors mode drops background images and gradients, so
    // clipped text would be invisible: paint it in the text color.
    if (declarations.includes('background-clip:text')) {
        css +=
            `@media (forced-colors:active){.${className}{-webkit-text-fill-color:currentColor;` +
            '-webkit-background-clip:border-box;background-clip:border-box}}';
    }

    return { className, css };
}

/**
 * Options for {@link stampEffectsScopes}.
 *
 * @since 1.15.0
 */
export interface StampEffectsOptions {
    /**
     * Whether Custom CSS may load resources (`url(`, `image-set(`,
     * `image(`, `src(`). Pass the host's config
     * `effects.custom_css_allow_urls`; defaults to `true`.
     */
    allowCustomCssUrls?: boolean;
}

/**
 * Walk a tree and append each block's effects scope class to its
 * `className` (every block renderer prints `className` on its wrapper,
 * the way Blade's `BlockSupports` adds the class), collecting the rules
 * for one `<style data-ve-effects>` block.
 */
export function stampEffectsScopes(
    tree: Block[],
    options: StampEffectsOptions = {}
): { tree: Block[]; css: string } {
    const seen = new Set<string>();
    const css: string[] = [];

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object') {
                return block;
            }

            const innerBlocks = Array.isArray(block.innerBlocks)
                ? walk(block.innerBlocks as Block[])
                : block.innerBlocks;

            const raw = (block as { attributes?: unknown }).attributes;
            const attrs =
                raw !== null && typeof raw === 'object' && !Array.isArray(raw)
                    ? (raw as Record<string, unknown>)
                    : null;
            const compiled = compileEffects(attrs, { allowCustomCssUrls: options.allowCustomCssUrls });

            if (attrs === null || compiled === null) {
                return { ...block, innerBlocks } as Block;
            }

            if (!seen.has(compiled.className)) {
                seen.add(compiled.className);
                css.push(compiled.css);
            }

            const existing = typeof attrs.className === 'string' ? attrs.className.trim() : '';

            return {
                ...block,
                attributes: {
                    ...attrs,
                    className: existing === '' ? compiled.className : `${existing} ${compiled.className}`,
                },
                innerBlocks,
            } as Block;
        });
    }

    return { tree: walk(tree), css: css.join('') };
}

function asRecord(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}

function filled(value: unknown): boolean {
    return typeof value === 'string' && value !== '';
}

/**
 * ASCII-only lowercase, as PHP's `strtolower()`.
 */
function asciiLower(value: string): string {
    return value.replace(/[A-Z]/g, (letter) => letter.toLowerCase());
}

function integer(value: unknown, min: number, max: number): number | null {
    let numeric = value;

    if (typeof numeric === 'string' && /^[ \t\n\v\f\r]*-?\d+(\.\d+)?[ \t\n\v\f\r]*$/.test(numeric)) {
        numeric = Number(numeric);
    }

    if (typeof numeric !== 'number' || !Number.isFinite(numeric)) {
        return null;
    }

    // Half away from zero, as PHP's `round()` does.
    const rounded = Math.sign(numeric) * Math.round(Math.abs(numeric));

    return Math.max(min, Math.min(max, rounded === 0 ? 0 : rounded));
}

function keyword(value: unknown, allowed: string[]): string | null {
    return typeof value === 'string' && allowed.includes(value) ? value : null;
}

function ratio(value: number): string {
    return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}
