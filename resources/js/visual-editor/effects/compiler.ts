/**
 * Block effects + Custom CSS compiler — editor canvas (#881).
 *
 * Mirrors `EffectsCompiler` / `CustomCssSanitizer` (PHP) and the React /
 * Vue renderers' `support/effects.ts`, so the canvas previews exactly the
 * CSS the front end prints. Only the scope hash differs: the canvas class
 * never reaches saved markup, so a cheap FNV-1a hash stands in for SHA-1.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

export const EFFECTS_ATTRIBUTE = 'artisanpackEffects';

export const EFFECTS_SCOPE_PREFIX = 've-fx-';

export const CUSTOM_CSS_MAX_LENGTH = 10000;

const ALLOWED_AT_RULES = ['media', 'supports', 'container'];

const SCRIPT_VECTORS = ['expression(', 'javascript:', '-moz-binding', 'behavior:'];

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
 */
export function sanitizeCustomCss(css: unknown): string | null {
    if (typeof css !== 'string' || css.trim() === '') {
        return '';
    }

    if ([...css].length > CUSTOM_CSS_MAX_LENGTH) {
        return null;
    }

    // eslint-disable-next-line no-control-regex
    if (/[<\\\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(css)) {
        return null;
    }

    const lower = css.toLowerCase();

    if (SCRIPT_VECTORS.some((vector) => lower.includes(vector))) {
        return null;
    }

    let out = '';
    let quote: string | null = null;
    let depth = 0;
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

        if (char === '"' || char === "'") {
            quote = char;
        } else if (char === '{') {
            depth++;
        } else if (char === '}') {
            if (--depth < 0) {
                return null;
            }
        } else if (char === '@') {
            const name = (/^[a-zA-Z-]*/.exec(css.slice(i + 1)) ?? [''])[0].toLowerCase();

            if (!ALLOWED_AT_RULES.includes(name)) {
                return null;
            }
        }

        out += char;
    }

    if (quote !== null || depth !== 0) {
        return null;
    }

    return out;
}

/**
 * The CSS declarations for an effects payload, in a fixed order. Mirrors
 * `EffectsCompiler::declarations()`.
 */
export function effectsDeclarations(effects: Record<string, unknown>): string[] {
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

    if (effects.textClip === true) {
        declarations.push('-webkit-background-clip:text');
        declarations.push('background-clip:text');
        declarations.push('-webkit-text-fill-color:transparent');
    }

    return declarations;
}

/**
 * Compile a block's effects into a scope class and its CSS, or `null`
 * when it sets none. Mirrors `EffectsCompiler::compile()`.
 */
export function compileEffects(
    attributes: Record<string, unknown> | null | undefined
): { className: string; css: string } | null {
    const effects = attributes?.[EFFECTS_ATTRIBUTE];

    if (effects === null || typeof effects !== 'object' || Array.isArray(effects)) {
        return null;
    }

    const record = effects as Record<string, unknown>;
    const declarations = effectsDeclarations(record);
    const customCss = sanitizeCustomCss(record.customCss) ?? '';

    if (declarations.length === 0 && customCss === '') {
        return null;
    }

    const body = declarations.join(';');
    const className = EFFECTS_SCOPE_PREFIX + fnv1a(`${body}\n${customCss}`);
    const inner = [body, customCss].filter((part) => part !== '').join(';');
    let css = `.${className}{${inner}}`;

    if (integer(record.zIndex, -99999, 99999) !== null) {
        css += `:where(.${className}){position:relative}`;
    }

    return { className, css };
}

function integer(value: unknown, min: number, max: number): number | null {
    let numeric = value;

    if (typeof numeric === 'string' && /^\s*-?\d+(\.\d+)?\s*$/.test(numeric)) {
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

/**
 * 32-bit FNV-1a, as 8 hex characters.
 */
function fnv1a(input: string): string {
    let hash = 0x811c9dc5;

    for (let i = 0; i < input.length; i++) {
        hash ^= input.charCodeAt(i);
        hash = Math.imul(hash, 0x01000193);
    }

    return (hash >>> 0).toString(16).padStart(8, '0');
}

function ratio(value: number): string {
    return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}
