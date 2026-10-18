/**
 * Duotone filter support (F-4, F-2, F-8) — byte-for-byte twin of the Blade
 * renderer's `Support\DuotoneSupport` and `Services\DuotoneCssAccumulator`
 * (#873).
 *
 * A block that supports `filter.duotone` stores its pick under
 * `style.color.duotone` as a custom stop list (`["#000", "#fff"]`), a theme
 * preset reference (`var:preset|duotone|{slug}` or
 * `var(--wp--preset--duotone--{slug})`), or the literal `unset`. Each pick
 * compiles to a `wp-duotone-*` scope class for the block wrapper, a rule
 * pointing the block's media at the filter, and an SVG `<filter>`.
 *
 * Stops may be hex, `rgb[a]()`, `hsl[a]()` (comma or space syntax, hue
 * units, `/ alpha`) or a CSS named color. One unparseable stop drops the
 * whole pick, so a hostile value never reaches the markup.
 *
 * `stampDuotoneScopes()` walks a block tree the way the Blade renderer
 * renders it (inner blocks before their parent), stamps the scope class on
 * each duotone-capable block whose media renders (`_veDuotone`, read by the
 * block renderers), and returns the rules and filters `BlockTree` prints as
 * one `<style data-ve-duotone>` plus one hidden `<svg data-ve-duotone>`.
 * Ids are unique per tree; two trees on one page each print their own.
 *
 * Presets come from the `duotonePresets` `BlockTree` prop: the theme's
 * `settings.color.duotone`, either the flat list theme.json authors write
 * or the origin-keyed (`theme` / `custom` / `default`) shape the resolved
 * global styles use.
 *
 * @since 1.15.0
 */

import { attrBoolean, attrRecord, attrString, phpTrim } from './attributes';
import { coverImageUrl } from './coverImage';
import { namedColorHex } from './cssNamedColors';
import { phpNumberFormat } from './phpNumberFormat';
import { safeUrl } from './urlSanitizer';
import { xxh3_64_hex } from './xxh3';
import type { Block } from '../types';

/** The scope class / filter id prefix WordPress core uses. */
export const DUOTONE_PREFIX = 'wp-duotone-';

/**
 * Media each duotone-capable block points the filter at, relative to its
 * wrapper. Twin of `DuotoneSupport::TARGETS`.
 */
export const DUOTONE_TARGETS: Readonly<Record<string, string>> = {
    'core/image': 'img',
    'core/post-featured-image': 'img',
    'core/cover': '> .wp-block-cover__image-background, > .wp-block-cover__video-background',
    'core/avatar': 'img',
    'core/site-logo': 'img',
    'core/post-author': '.wp-block-post-author__avatar img',
};

/** A theme duotone preset (`settings.color.duotone[]`). */
export interface DuotonePreset {
    slug?: unknown;
    colors?: unknown;
    name?: unknown;
}

/** The theme's `settings.color.duotone`, flat or origin-keyed. */
export type DuotonePresets =
    | DuotonePreset[]
    | { theme?: DuotonePreset[]; custom?: DuotonePreset[]; default?: DuotonePreset[] }
    | null
    | undefined;

export interface CompiledDuotone {
    class: string;
    rules: string;
    filterId: string;
    filter: string;
}

type Stop = [number, number, number, number];

const EMPTY: CompiledDuotone = { class: '', rules: '', filterId: '', filter: '' };

/**
 * Whether each duotone-capable block renders the media its filter targets,
 * mirroring the condition its renderer (and Blade partial) uses.
 */
const MEDIA_RENDERED: Readonly<Record<string, (attributes: Record<string, unknown>) => boolean>> = {
    'core/image': (attributes) => safeUrl(attributes.url) !== '',
    'core/post-featured-image': (attributes) => safeUrl(attrString(attributes._resolvedImageUrl)) !== '',
    'core/cover': (attributes) => coverImageUrl(attributes) !== '',
    'core/avatar': (attributes) => safeUrl(attrString(attributes._resolvedAuthorAvatar)) !== '',
    'core/site-logo': (attributes) => safeUrl(attrString(attributes._resolvedLogoUrl)) !== '',
    'core/post-author': (attributes) =>
        attrBoolean(attributes.showAvatar, true) && safeUrl(attrString(attributes._resolvedAuthorAvatar)) !== '',
};

/**
 * The `core/*` name a duotone-capable block renders as (`artisanpack/*`
 * forks share the core partials), or `null`.
 */
function duotoneBlockName(name: unknown): string | null {
    if (typeof name !== 'string') {
        return null;
    }

    const coreName = name.startsWith('artisanpack/') ? `core/${name.slice('artisanpack/'.length)}` : name;

    return Object.prototype.hasOwnProperty.call(DUOTONE_TARGETS, coreName) ? coreName : null;
}

/**
 * Stamp `_veDuotone` on every duotone-capable block whose media renders,
 * and collect the deduped rules and filters, in Blade's render order.
 */
export function stampDuotoneScopes(
    tree: Block[],
    presets?: DuotonePresets
): { tree: Block[]; css: string; filters: string } {
    const rules = new Map<string, string>();
    const filters = new Map<string, string>();

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object') {
                return block;
            }

            const innerBlocks = Array.isArray(block.innerBlocks)
                ? walk(block.innerBlocks as Block[])
                : block.innerBlocks;

            const name = duotoneBlockName(block.name);
            const raw = (block as { attributes?: unknown }).attributes;
            const attributes =
                raw !== null && typeof raw === 'object' && !Array.isArray(raw)
                    ? (raw as Record<string, unknown>)
                    : null;

            if (name === null || attributes === null || !MEDIA_RENDERED[name](attributes)) {
                return { ...block, innerBlocks } as Block;
            }

            const target = DUOTONE_TARGETS[name];
            const compiled = compileDuotone(attributes, target, presets);

            if (compiled.class === '') {
                return { ...block, innerBlocks } as Block;
            }

            const scope = `${compiled.class} ${target}`;

            if (compiled.rules !== '' && !rules.has(scope)) {
                rules.set(scope, compiled.rules);
            }

            if (compiled.filterId !== '' && compiled.filter !== '' && !filters.has(compiled.filterId)) {
                filters.set(compiled.filterId, compiled.filter);
            }

            return {
                ...block,
                attributes: { ...attributes, _veDuotone: compiled.class },
                innerBlocks,
            } as Block;
        });
    }

    const stamped = walk(tree);

    return {
        tree: stamped,
        css: [...rules.values()].join(''),
        filters: [...filters.values()].join(''),
    };
}

/**
 * Compile a block's duotone pick without side effects. Twin of
 * `DuotoneSupport::compile()`.
 */
export function compileDuotone(
    attributes: Record<string, unknown>,
    targetSelector = 'img',
    presets?: DuotonePresets
): CompiledDuotone {
    const value = attrRecord(attrRecord(attributes.style).color).duotone;

    if (value === 'unset') {
        const unsetClass = `${DUOTONE_PREFIX}unset`;

        return {
            class: unsetClass,
            rules: `${scopedSelector(unsetClass, targetSelector)}{filter:none !important}`,
            filterId: '',
            filter: '',
        };
    }

    const [suffix, colors] = resolveDuotone(value, presets);

    if (suffix === null) {
        return EMPTY;
    }

    const stops = parseColors(colors);

    if (stops.length < 2) {
        return EMPTY;
    }

    const id = DUOTONE_PREFIX + suffix;

    return {
        class: id,
        rules: `${scopedSelector(id, targetSelector)}{filter:url(#${id}) !important}`,
        filterId: id,
        filter: filterSvg(id, stops),
    };
}

/** Scope every comma-separated target part to the wrapper class. */
function scopedSelector(className: string, targetSelector: string): string {
    let parts = targetSelector
        .split(',')
        .map((part) => phpTrim(part))
        .filter((part) => part !== '');

    if (parts.length === 0) {
        parts = ['img'];
    }

    return parts.map((part) => `.${className} ${part}`).join(',');
}

/**
 * The id suffix and color stops for a stored value: a content hash for a
 * custom stop list, the slugged preset slug for a preset reference.
 */
function resolveDuotone(value: unknown, presets: DuotonePresets): [string | null, unknown[]] {
    if (value !== null && typeof value === 'object') {
        const colors = Array.isArray(value) ? [...value] : Object.values(value as Record<string, unknown>);

        return [xxh3_64_hex(phpJsonEncodeList(colors)).slice(0, 10), colors];
    }

    if (typeof value !== 'string') {
        return [null, []];
    }

    const match =
        /^var:preset\|duotone\|([A-Za-z0-9_-]+)$/.exec(value) ??
        /^var\(--wp--preset--duotone--([A-Za-z0-9_-]+)\)$/.exec(value);

    if (match === null) {
        return [null, []];
    }

    const colors = presetColors(match[1], presets);

    return colors === null ? [null, []] : [slugify(match[1]), colors];
}

/** Look a preset up: flat list, or theme → custom → default. */
function presetColors(slug: string, presets: DuotonePresets): unknown[] | null {
    if (presets === null || presets === undefined || typeof presets !== 'object') {
        return null;
    }

    const lists: unknown[] = Array.isArray(presets)
        ? [presets]
        : [
              (presets as Record<string, unknown>).theme,
              (presets as Record<string, unknown>).custom,
              (presets as Record<string, unknown>).default,
          ].filter((list) => list !== null && typeof list === 'object');

    for (const list of lists) {
        const entries = Array.isArray(list) ? list : Object.values(list as Record<string, unknown>);

        for (const preset of entries) {
            if (preset === null || typeof preset !== 'object' || (preset as DuotonePreset).slug !== slug) {
                continue;
            }

            const colors = (preset as DuotonePreset).colors;

            return colors !== null && typeof colors === 'object'
                ? Array.isArray(colors)
                    ? [...colors]
                    : Object.values(colors as Record<string, unknown>)
                : null;
        }
    }

    return null;
}

/** `BlockSupports::slugify()` for an ASCII preset slug. */
function slugify(value: string): string {
    return phpTrim(value)
        .replace(/[A-Z]/g, (char) => char.toLowerCase())
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function parseColors(colors: unknown[]): Stop[] {
    const stops: Stop[] = [];

    for (const color of colors) {
        const parsed = typeof color === 'string' ? parseColor(phpTrim(color)) : null;

        if (parsed === null) {
            return [];
        }

        stops.push(parsed);
    }

    return stops;
}

/** One hex / `rgb[a]()` / `hsl[a]()` / named color. */
function parseColor(input: string): Stop | null {
    const color = namedColorHex(input) ?? input;
    const hex = /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.exec(color);

    if (hex !== null) {
        let digits = hex[1];

        if (digits.length <= 4) {
            digits = digits
                .split('')
                .map((char) => char + char)
                .join('');
        }

        const channels = (digits.match(/../g) ?? []).map((pair) => parseInt(pair, 16) / 255);

        return [channels[0], channels[1], channels[2], channels[3] ?? 1];
    }

    const rgb =
        /^rgba?\([ \t\n\x0B\f\r]*(\d{1,3})[ \t\n\x0B\f\r]*,[ \t\n\x0B\f\r]*(\d{1,3})[ \t\n\x0B\f\r]*,[ \t\n\x0B\f\r]*(\d{1,3})[ \t\n\x0B\f\r]*(?:,[ \t\n\x0B\f\r]*(\d*\.?\d+)[ \t\n\x0B\f\r]*)?\)$/i.exec(
            color
        );

    if (rgb !== null) {
        const channel = (value: string): number => Math.min(255, parseInt(value, 10)) / 255;
        const alpha = rgb[4] !== undefined && rgb[4] !== '' ? Math.min(1, Number(rgb[4])) : 1;

        return [channel(rgb[1]), channel(rgb[2]), channel(rgb[3]), alpha];
    }

    return parseHsl(color);
}

const WS = '[ \\t\\n\\x0B\\f\\r]';
const NUMBER = '([+-]?(?:\\d+\\.?\\d*|\\.\\d+))';
const HUE = `${NUMBER}(deg|rad|grad|turn)?`;
const HSL_COMMA = new RegExp(
    `^hsla?\\(${WS}*${HUE}${WS}*,${WS}*${NUMBER}%${WS}*,${WS}*${NUMBER}%${WS}*(?:,${WS}*${NUMBER}(%)?${WS}*)?\\)$`,
    'i'
);
const HSL_SPACE = new RegExp(
    `^hsla?\\(${WS}*${HUE}${WS}+${NUMBER}%${WS}+${NUMBER}%${WS}*(?:\\/${WS}*${NUMBER}(%)?${WS}*)?\\)$`,
    'i'
);

/** Twin of `DuotoneSupport::parseHsl()`. */
function parseHsl(color: string): Stop | null {
    const match = HSL_COMMA.exec(color) ?? HSL_SPACE.exec(color);

    if (match === null) {
        return null;
    }

    let degrees = Number(match[1]);

    switch ((match[2] ?? '').replace(/[A-Z]/g, (char) => char.toLowerCase())) {
        case 'rad':
            degrees = (degrees * 180) / Math.PI;
            break;
        case 'grad':
            degrees = degrees * 0.9;
            break;
        case 'turn':
            degrees = degrees * 360;
            break;
    }

    const hue = (((degrees % 360) + 360) % 360) / 360;
    const saturation = Math.min(100, Math.max(0, Number(match[3]))) / 100;
    const lightness = Math.min(100, Math.max(0, Number(match[4]))) / 100;
    let alpha = 1;

    if (match[5] !== undefined && match[5] !== '') {
        alpha = Number(match[5]);

        if (match[6] === '%') {
            alpha = alpha / 100;
        }

        alpha = Math.min(1, Math.max(0, alpha));
    }

    const amount = saturation * Math.min(lightness, 1 - lightness);
    const channel = (shift: number): number => {
        const k = (shift + hue * 12) % 12;
        const value = lightness - amount * Math.max(-1, Math.min(k - 3, 9 - k, 1));

        return Math.floor(value * 255 + 0.5) / 255;
    };

    return [channel(0), channel(8), channel(4), alpha];
}

/** The `<filter>` element, in WordPress core's structure. */
function filterSvg(id: string, stops: Stop[]): string {
    const table = (channel: number): string => stops.map((stop) => formatNumber(stop[channel])).join(' ');

    return (
        `<filter id="${escapeAttribute(id)}"><feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0"/>` +
        `<feComponentTransfer color-interpolation-filters="sRGB"><feFuncR type="table" tableValues="${table(0)}"/><feFuncG type="table" tableValues="${table(1)}"/><feFuncB type="table" tableValues="${table(2)}"/><feFuncA type="table" tableValues="${table(3)}"/></feComponentTransfer>` +
        '<feComposite in2="SourceGraphic" operator="in"/></filter>'
    );
}

/** `DuotoneSupport::formatNumber()`: up to four decimals, no trailing zeros. */
function formatNumber(value: number): string {
    const formatted = phpNumberFormat(value, 4).replace(/0+$/, '').replace(/\.$/, '');

    return formatted === '' ? '0' : formatted;
}

/** Laravel `e()` for the (already `[a-z0-9_-]`) filter id. */
function escapeAttribute(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/**
 * PHP `json_encode()` of a list, as the hash input. Only all-string lists
 * can produce a filter (any other stop fails to parse), so strings are
 * escaped the way PHP does (`/` and non-ASCII included) and anything else
 * is written as `null`.
 */
function phpJsonEncodeList(values: unknown[]): string {
    return `[${values.map((value) => (typeof value === 'string' ? phpJsonString(value) : 'null')).join(',')}]`;
}

function phpJsonString(value: string): string {
    let out = '"';

    for (let i = 0; i < value.length; i++) {
        const char = value[i];
        const code = value.charCodeAt(i);

        switch (char) {
            case '"':
                out += '\\"';
                break;
            case '\\':
                out += '\\\\';
                break;
            case '/':
                out += '\\/';
                break;
            case '\b':
                out += '\\b';
                break;
            case '\f':
                out += '\\f';
                break;
            case '\n':
                out += '\\n';
                break;
            case '\r':
                out += '\\r';
                break;
            case '\t':
                out += '\\t';
                break;
            default:
                out += code < 0x20 || code >= 0x80 ? `\\u${code.toString(16).padStart(4, '0')}` : char;
        }
    }

    return `${out}"`;
}
