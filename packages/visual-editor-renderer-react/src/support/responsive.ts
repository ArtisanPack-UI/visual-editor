/**
 * Generic responsive overrides — React renderer (#886).
 *
 * Mirrors `BlockSupports::compileResponsive()` in the Blade renderer: a
 * block's path-keyed `attributes.responsive` payload
 * (`{ "style.spacing.padding": { mobile: "1rem" } }`, the shape the
 * editor's `withResponsiveAttributes` HOC writes) becomes a hashed
 * `ve-r-<xxh3>` scope class plus `@media` rules. The scope is hashed from
 * the same PHP-`json_encode()`d payload with {@link xxh3_64_hex}, so the
 * class matches Blade byte-for-byte (kept honest by the #704
 * markup-parity check). Identical payloads share one rule set.
 *
 * The mirror lives at the matching path in the Vue renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-react
 * @since 1.15.0
 */

import { getBreakpoints, getDeviceBreakpoints } from '../visibility';
import type { Block } from '../types';
import { phpTrim } from './attributes';
import { expandPresetReference } from './presetReference';
import { xxh3_64_hex } from './xxh3';

const BASE_KEY = 'base';

const SPACER_BLOCK_NAME = 'core/spacer';

export const RESPONSIVE_SCOPE_PREFIX = 've-r-';

/**
 * Path → CSS property map for the generic emitter. Mirrors
 * `BlockSupports::RESPONSIVE_CSS_PROPERTY_MAP`; `width` is absent because
 * the column block maps it to `flex-basis` (see `columnWidth.ts`) and the
 * spacer emits it through {@link SPACER_WIDTH_PROPERTY_MAP}.
 */
export const RESPONSIVE_CSS_PROPERTY_MAP: Readonly<Record<string, string>> = {
    'style.spacing.padding': 'padding',
    'style.spacing.margin': 'margin',
    'style.spacing.blockGap': 'gap',
    'style.border.radius': 'border-radius',
    'style.border.width': 'border-width',
    'style.border.style': 'border-style',
    'style.border.color': 'border-color',
    'style.typography.fontSize': 'font-size',
    fontSize: 'font-size',
    'style.color.text': 'color',
    textColor: 'color',
    'style.color.background': 'background-color',
    backgroundColor: 'background-color',
    'style.dimensions.minHeight': 'min-height',
    height: 'height',
    minHeight: 'min-height',
};

/** The spacer-only map its partial passes for per-breakpoint widths. */
export const SPACER_WIDTH_PROPERTY_MAP: Readonly<Record<string, string>> = { width: 'width' };

/**
 * Paths whose value is a preset slug, mapped to its preset category.
 * Mirrors `BlockSupports::RESPONSIVE_PRESET_PATHS`.
 */
const RESPONSIVE_PRESET_PATHS: Readonly<Record<string, string>> = {
    fontSize: 'font-size',
    textColor: 'color',
    backgroundColor: 'color',
};

/**
 * Paths whose numeric value takes its unit from a sibling attribute, with
 * the fallback unit. Mirrors `BlockSupports::RESPONSIVE_UNIT_PATHS`.
 */
const RESPONSIVE_UNIT_PATHS: Readonly<Record<string, readonly [string | null, string]>> = {
    minHeight: ['minHeightUnit', 'px'],
    height: [null, 'px'],
};

const RESPONSIVE_ALLOWED_UNITS = ['px', 'em', 'rem', 'vh', 'vw', '%'];

/** Same grammar as `BlockSupports::CSS_VALUE_DISALLOWED`, global to strip. */
const CSS_VALUE_DISALLOWED = /[^a-zA-Z0-9_+\-*/.,()%#\t\n\r\f ]/g;

const SIDES = ['top', 'right', 'bottom', 'left'];

export interface ResponsiveScope {
    /** The `ve-r-<hash>` class stamped onto the block wrapper. */
    className: string;
    /** The rules for every surviving breakpoint. */
    css: string;
}

interface Registry {
    /** `base`, then legacy keys ascending, then device keys largest first. */
    keysWithBase: string[];
    mediaQuery: (key: string) => string | null;
    cascade: (key: string) => string[];
}

/**
 * Compile a block's responsive overrides into a scope class and its
 * rules, or `null` when no override survives. Mirrors
 * `BlockSupports::compileResponsive()`.
 */
export function compileResponsive(
    attributes: Record<string, unknown>,
    propertyMap: Readonly<Record<string, string>> = RESPONSIVE_CSS_PROPERTY_MAP
): ResponsiveScope | null {
    const stored = attributes.responsive;

    if (!isPhpArray(stored) || Object.keys(stored).length === 0) {
        return null;
    }

    let json: string;
    try {
        json = phpJsonEncode(responsiveHashInput(stored, attributes, propertyMap));
    } catch {
        return null;
    }

    const registry = resolveRegistry();
    const className = RESPONSIVE_SCOPE_PREFIX + xxh3_64_hex(json).slice(0, 10);
    // Tripled scope class — see the matching comment in `BlockSupports`.
    const selector = `.${className}.${className}.${className}`;
    const responsive: Record<string, unknown> = { ...stored };

    // A unit-only override still needs its value path walked (#877).
    for (const [path, [unitPath]] of Object.entries(RESPONSIVE_UNIT_PATHS)) {
        if (unitPath !== null && hasOwn(propertyMap, path) && isSet(responsive[unitPath]) && !isSet(responsive[path])) {
            responsive[path] = [];
        }
    }

    // Group by CSS property (#877) so a preset path and its custom twin
    // keep one value per breakpoint and the narrower override wins.
    const byProperty = new Map<string, Map<string, [string, unknown]>>();

    for (const [path, raw] of Object.entries(responsive)) {
        if (!isPhpArray(raw) || !hasOwn(propertyMap, path)) {
            continue;
        }

        const overrides = withUnitOnlyOverrides(path, raw, attributes, registry);

        for (const [breakpoint, value] of Object.entries(overrides)) {
            // `false` is the editor's tombstone (#887): it only blocks the
            // cascade in the canvas and must not displace a real value.
            if (value === null || value === undefined || value === '' || value === false) {
                continue;
            }

            const property = propertyMap[path];
            const bucket = byProperty.get(property) ?? new Map<string, [string, unknown]>();

            bucket.set(breakpoint, [path, value]);
            byProperty.set(property, bucket);
        }
    }

    const rules: string[] = [];

    for (const [property, overrides] of byProperty) {
        for (const breakpoint of registry.keysWithBase) {
            const entry = overrides.get(breakpoint);

            if (entry === undefined) {
                continue;
            }

            const value = normaliseResponsiveValue(entry[0], entry[1], breakpoint, attributes, registry);

            if (value === null) {
                continue;
            }

            const declarations = responsiveDeclarations(property, value);

            if (declarations === '') {
                continue;
            }

            if (breakpoint === BASE_KEY) {
                rules.push(`${selector}{${declarations}}`);
                continue;
            }

            const query = registry.mediaQuery(breakpoint);

            if (query === null) {
                continue;
            }

            rules.push(`@media ${query}{${selector}{${declarations}}}`);
        }
    }

    if (rules.length === 0) {
        return null;
    }

    return { className, css: rules.join('') };
}

/**
 * The spacer's per-breakpoint `width` scope: the spacer partial runs the
 * same emitter over just `responsive.width` with a spacer-only map.
 */
export function compileSpacerWidth(attributes: Record<string, unknown>): ResponsiveScope | null {
    const responsive = attributes.responsive;
    const width = isPhpArray(responsive) && hasOwn(responsive, 'width') ? responsive.width : undefined;

    if (width === undefined) {
        return null;
    }

    return compileResponsive({ responsive: { width } }, SPACER_WIDTH_PROPERTY_MAP);
}

/**
 * Walk a tree and append each block's responsive scope class to its
 * `className` (every block renderer prints `className` on its wrapper,
 * the way Blade's `BlockSupports::compile()` adds it), collecting the
 * rules for one `<style data-ve-responsive>` block. A spacer's width
 * scope lands on the renderer-owned `_veSpacerWidthScope` attribute,
 * because Blade appends it after every compiled class.
 */
export function stampResponsiveScopes(tree: Block[]): { tree: Block[]; css: string } {
    const seen = new Set<string>();
    const css: string[] = [];

    function collect(scope: ResponsiveScope): void {
        if (!seen.has(scope.className)) {
            seen.add(scope.className);
            css.push(scope.css);
        }
    }

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object') {
                return block;
            }

            const innerBlocks = Array.isArray(block.innerBlocks)
                ? walk(block.innerBlocks as Block[])
                : block.innerBlocks;

            const raw = (block as { attributes?: unknown }).attributes;

            if (
                raw === null ||
                typeof raw !== 'object' ||
                Array.isArray(raw) ||
                !('responsive' in raw || '_veSpacerWidthScope' in raw)
            ) {
                return { ...block, innerBlocks } as Block;
            }

            // Strip any side-channel value that arrived on the input tree
            // so an author-crafted block can't inject class tokens.
            const attrs: Record<string, unknown> = { ...(raw as Record<string, unknown>) };
            delete attrs._veSpacerWidthScope;

            const scope = compileResponsive(attrs);

            if (scope !== null) {
                collect(scope);

                const existing = typeof attrs.className === 'string' ? attrs.className.trim() : '';
                attrs.className = existing === '' ? scope.className : `${existing} ${scope.className}`;
            }

            const name = typeof block.name === 'string' ? block.name.trim() : '';
            const widthScope = name === SPACER_BLOCK_NAME ? compileSpacerWidth(attrs) : null;

            if (widthScope !== null) {
                collect(widthScope);
                attrs._veSpacerWidthScope = widthScope.className;
            }

            return { ...block, attributes: attrs, innerBlocks } as Block;
        });
    }

    return { tree: walk(tree), css: css.join('') };
}

/**
 * The payload the scope class is hashed from: the responsive map, plus
 * the base value and unit when a unit path is overridden. Mirrors
 * `BlockSupports::responsiveHashInput()`.
 */
function responsiveHashInput(
    responsive: Record<string, unknown>,
    attributes: Record<string, unknown>,
    propertyMap: Readonly<Record<string, string>>
): unknown {
    const base: Record<string, unknown> = {};

    for (const [path, [unitPath]] of Object.entries(RESPONSIVE_UNIT_PATHS)) {
        if (unitPath === null || !hasOwn(propertyMap, path)) {
            continue;
        }

        if (!isSet(responsive[path]) && !isSet(responsive[unitPath])) {
            continue;
        }

        base[path] = attributes[path] ?? null;
        base[unitPath] = attributes[unitPath] ?? null;
    }

    return Object.keys(base).length === 0 ? responsive : { responsive, base };
}

/**
 * Fill in a value override at every breakpoint that overrides only the
 * unit. Mirrors `BlockSupports::withUnitOnlyOverrides()`.
 */
function withUnitOnlyOverrides(
    path: string,
    overrides: Record<string, unknown>,
    attributes: Record<string, unknown>,
    registry: Registry
): Record<string, unknown> {
    const unitPath = RESPONSIVE_UNIT_PATHS[path]?.[0] ?? null;

    if (unitPath === null) {
        return overrides;
    }

    const responsive = attributes.responsive;
    const unitOverrides = isPhpArray(responsive) ? responsive[unitPath] : undefined;

    if (!isPhpArray(unitOverrides)) {
        return overrides;
    }

    const filled: Record<string, unknown> = { ...overrides };

    for (const [breakpoint, unit] of Object.entries(unitOverrides)) {
        if (unit === null || unit === undefined || unit === '' || isSet(filled[breakpoint])) {
            continue;
        }

        const value = cascadeResponsiveValue(attributes, path, breakpoint, registry);

        if (value !== null && value !== undefined && value !== '') {
            filled[breakpoint] = value;
        }
    }

    return filled;
}

/**
 * The value of a top-level attribute that applies at a breakpoint: the
 * first override along the cascade, then the base attribute. Mirrors
 * `BlockSupports::cascadeResponsiveValue()`.
 */
function cascadeResponsiveValue(
    attributes: Record<string, unknown>,
    path: string,
    breakpoint: string,
    registry: Registry
): unknown {
    const responsive = attributes.responsive;
    const overrides = isPhpArray(responsive) ? responsive[path] : undefined;

    if (isPhpArray(overrides)) {
        for (const key of registry.cascade(breakpoint)) {
            if (key === BASE_KEY) {
                break;
            }

            if (isSet(overrides[key]) && overrides[key] !== '') {
                return overrides[key];
            }
        }

        if (isSet(overrides[BASE_KEY]) && overrides[BASE_KEY] !== '') {
            return overrides[BASE_KEY];
        }
    }

    return attributes[path] ?? null;
}

/**
 * Turn a stored override into the value the emitter writes: preset paths
 * expand their slug, unit paths append the unit that applies at the
 * breakpoint. Returns `null` to drop it. Mirrors
 * `BlockSupports::normaliseResponsiveValue()`.
 */
function normaliseResponsiveValue(
    path: string,
    value: unknown,
    breakpoint: string,
    attributes: Record<string, unknown>,
    registry: Registry
): unknown {
    if (hasOwn(RESPONSIVE_PRESET_PATHS, path)) {
        if (typeof value !== 'string' || phpTrim(value) === '') {
            return null;
        }

        return `var:preset|${RESPONSIVE_PRESET_PATHS[path]}|${phpTrim(value)}`;
    }

    if (!hasOwn(RESPONSIVE_UNIT_PATHS, path)) {
        return value;
    }

    if (typeof value === 'string' && !isNumeric(value)) {
        return value;
    }

    if (!isNumeric(value)) {
        return null;
    }

    const [unitPath, fallbackUnit] = RESPONSIVE_UNIT_PATHS[path];
    let unit = unitPath === null ? fallbackUnit : cascadeResponsiveValue(attributes, unitPath, breakpoint, registry);

    if (typeof unit !== 'string' || !RESPONSIVE_ALLOWED_UNITS.includes(unit)) {
        unit = fallbackUnit;
    }

    return phpNumberString(Math.max(0, Number(typeof value === 'string' ? value.trim() : value))) + unit;
}

/**
 * Turn one override into its declarations: a scalar becomes
 * `property:value!important`, a per-side object one declaration per side.
 * Values are stripped to the CSS-value whitelist and dropped when they
 * carry a comment delimiter or unbalanced parentheses. Mirrors
 * `BlockSupports::responsiveDeclarations()`.
 */
function responsiveDeclarations(property: string, value: unknown): string {
    if (isScalar(value)) {
        const resolved = sanitizeCssValue(expandPresetReference(phpString(value)));

        if (resolved === '' || !isBalanced(resolved)) {
            return '';
        }

        return `${property}:${resolved}!important`;
    }

    if (!isPhpArray(value)) {
        return '';
    }

    const pieces: string[] = [];

    for (const side of SIDES) {
        const sideValue = value[side];

        if (!isScalar(sideValue) || sideValue === '') {
            continue;
        }

        const resolved = sanitizeCssValue(expandPresetReference(phpString(sideValue)));

        if (resolved === '' || !isBalanced(resolved)) {
            continue;
        }

        pieces.push(`${property}-${side}:${resolved}!important`);
    }

    return pieces.join(';');
}

/**
 * The breakpoint registry the Blade renderer reads from the container:
 * the legacy min-width keys ascending, then the desktop-first device
 * keys largest first (#820). Both come from `setBreakpoints()`, so a
 * host that passes the server's `BreakpointRegistry::toArray()` gets the
 * same media queries and cascade as Blade.
 */
function resolveRegistry(): Registry {
    const legacy = getBreakpoints().sort((a, b) => a.minWidthPx - b.minWidthPx);
    const devices = getDeviceBreakpoints().sort((a, b) => b.maxWidthPx - a.maxWidthPx);
    const queries = new Map<string, string>([
        ...legacy.map((bp): [string, string] => [bp.key, `(min-width:${bp.minWidthPx}px)`]),
        ...devices.map((bp): [string, string] => [bp.key, `(max-width:${bp.maxWidthPx}px)`]),
    ]);

    return {
        keysWithBase: [BASE_KEY, ...queries.keys()],
        mediaQuery: (key) => queries.get(key) ?? null,
        // Mirrors `BreakpointRegistry::cascade()`.
        cascade: (key) => {
            const legacyActive = legacy.find((bp) => bp.key === key);

            if (legacyActive !== undefined) {
                return [
                    ...legacy
                        .filter((bp) => bp.minWidthPx <= legacyActive.minWidthPx)
                        .map((bp) => bp.key)
                        .reverse(),
                    BASE_KEY,
                ];
            }

            const device = devices.find((bp) => bp.key === key);

            if (device === undefined) {
                return [BASE_KEY];
            }

            return [
                ...devices.filter((bp) => bp.maxWidthPx >= device.maxWidthPx)
                    .map((bp) => bp.key)
                    .reverse(),
                ...legacy
                    .filter((bp) => bp.minWidthPx <= device.previewWidthPx)
                    .map((bp) => bp.key)
                    .reverse(),
                BASE_KEY,
            ];
        },
    };
}

function sanitizeCssValue(value: string): string {
    return value.replace(CSS_VALUE_DISALLOWED, '');
}

/** Mirrors `CssValue::isBalanced()`. */
function isBalanced(value: string): boolean {
    if (value.includes('/*') || value.includes('*/')) {
        return false;
    }

    let depth = 0;

    for (const char of value) {
        if (char === '(') {
            depth++;
        } else if (char === ')' && --depth < 0) {
            return false;
        }
    }

    return depth === 0;
}

/** A value PHP decodes into an array: any non-null object. */
function isPhpArray(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object';
}

/** PHP `isset()` on a decoded value. */
function isSet(value: unknown): boolean {
    return value !== null && value !== undefined;
}

function hasOwn(record: object, key: string): boolean {
    return Object.prototype.hasOwnProperty.call(record, key);
}

function isScalar(value: unknown): value is string | number | boolean {
    return typeof value === 'string' || typeof value === 'boolean' || (typeof value === 'number' && Number.isFinite(value));
}

/** PHP `(string)` cast of a scalar. */
function phpString(value: string | number | boolean): string {
    if (typeof value === 'boolean') {
        return value ? '1' : '';
    }

    return typeof value === 'number' ? phpNumberString(value) : value;
}

/**
 * PHP's float-to-string conversion (`precision` = 14 significant digits),
 * which also matches its integer output for whole numbers.
 */
function phpNumberString(value: number): string {
    return String(Number(value.toPrecision(14)));
}

/**
 * PHP `is_numeric()` for decoded values: finite numbers, and strings in
 * PHP's decimal / scientific grammar (surrounding whitespace allowed).
 */
function isNumeric(value: unknown): boolean {
    if (typeof value === 'number') {
        return Number.isFinite(value);
    }

    if (typeof value !== 'string') {
        return false;
    }

    const trimmed = value.trim();

    return /^[+-]?(\d+(\.\d+)?|\.\d+|\d+\.)([eE][+-]?\d+)?$/.test(trimmed) && Number.isFinite(Number(trimmed));
}

/**
 * Serialize a decoded value the way PHP's `json_encode()` does with
 * default flags, so the bytes fed to the hash match Blade's: a JS array —
 * or an object keyed `0…n-1`, or an empty object — is a PHP list (`[…]`);
 * `/` and every non-ASCII code unit are escaped.
 */
function phpJsonEncode(value: unknown): string {
    if (value === null || value === undefined) {
        return 'null';
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (typeof value === 'number') {
        if (!Number.isFinite(value)) {
            throw new RangeError('phpJsonEncode: non-finite number');
        }

        return String(value);
    }

    if (typeof value === 'string') {
        return phpJsonString(value);
    }

    if (Array.isArray(value)) {
        return `[${value.map(phpJsonEncode).join(',')}]`;
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);

        if (entries.every(([key], index) => key === String(index))) {
            return `[${entries.map(([, item]) => phpJsonEncode(item)).join(',')}]`;
        }

        return `{${entries.map(([key, item]) => `${phpJsonString(key)}:${phpJsonEncode(item)}`).join(',')}}`;
    }

    return 'null';
}

function phpJsonString(value: string): string {
    let out = '"';

    for (let i = 0; i < value.length; i++) {
        const code = value.charCodeAt(i);
        const char = value[i];

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
