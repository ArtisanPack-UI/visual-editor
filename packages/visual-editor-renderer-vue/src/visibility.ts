/**
 * Vue-side mirror of the React renderer's visibility helpers.
 *
 * See {@link ../../visual-editor-renderer-react/src/visibility.ts} for
 * the full contract — the two files must stay behavior-identical
 * because the render parity tests exercise both from the same
 * fixtures. Duplicating rather than re-exporting keeps the packages'
 * dependency graphs independent (parity plan #647).
 */

import type { Block } from './types';

export interface BreakpointDefinition {
    key: string;
    minWidthPx: number;
}

/**
 * A desktop-first device breakpoint (#820): it applies at
 * `(max-width:{maxWidthPx}px)`, and the editor previews it at
 * `previewWidthPx`, which also picks the legacy keys it inherits.
 *
 * @since 1.15.0
 */
export interface DeviceBreakpointDefinition {
    key: string;
    maxWidthPx: number;
    previewWidthPx: number;
}

/**
 * One breakpoint as `setBreakpoints()` accepts it: an entry of the
 * server's `BreakpointRegistry::toArray()` (with `key`), or the value of
 * a `config('artisanpack.visual-editor.breakpoints')`-style map entry
 * (without). Widths are integers or `Npx` strings. An entry declares
 * `minWidthPx` (legacy, mobile-first) or `maxWidthPx` (device,
 * desktop-first), not both.
 *
 * @since 1.15.0
 */
export interface BreakpointInput {
    key?: string;
    minWidthPx?: number | string | null;
    maxWidthPx?: number | string | null;
    previewWidthPx?: number | string | null;
    label?: string | null;
}

/**
 * A breakpoint map keyed by breakpoint key, layered over the defaults
 * like `BreakpointRegistry::fromLayers()` layers the host config: a
 * number or `Npx` string keeps the family of the entry it overrides
 * (`mobile: 600` narrows the Mobile max-width), an object naming one
 * width switches family, and `null` removes the entry.
 *
 * @since 1.15.0
 */
export type BreakpointMap = Record<string, BreakpointInput | number | string | null | undefined>;

const DEFAULT_BREAKPOINTS: BreakpointDefinition[] = [
    { key: 'sm', minWidthPx: 640 },
    { key: 'md', minWidthPx: 768 },
    { key: 'lg', minWidthPx: 1024 },
    { key: 'xl', minWidthPx: 1280 },
    { key: '2xl', minWidthPx: 1536 },
];

/** Mirrors the device entries of `BreakpointRegistry::DEFAULTS`. */
const DEFAULT_DEVICE_BREAKPOINTS: DeviceBreakpointDefinition[] = [
    { key: 'tablet', maxWidthPx: 1023, previewWidthPx: 768 },
    { key: 'mobile', maxWidthPx: 767, previewWidthPx: 375 },
];

let breakpoints: BreakpointDefinition[] = [...DEFAULT_BREAKPOINTS];

let deviceBreakpoints: DeviceBreakpointDefinition[] = [...DEFAULT_DEVICE_BREAKPOINTS];

/**
 * Replace the breakpoints the renderer emits media queries for. Pass the
 * server's `BreakpointRegistry::toArray()` so the JS output matches
 * Blade byte for byte, including customised Tablet / Mobile widths.
 *
 * - An **array** replaces the legacy (min-width) list with its
 *   `minWidthPx` entries. Its `maxWidthPx` entries replace the device
 *   list; an array without any keeps the default devices.
 * - A **map** (`{ mobile: { maxWidthPx: 599 } }`) is layered over the
 *   defaults like the host config is on the server.
 *
 * Invalid entries (bad key, the reserved `base` key, non-positive or
 * duplicate widths, both or neither width) are dropped, and a device
 * preview width wider than its max-width is clamped to it.
 *
 * @since 1.0.0
 * @since 1.15.0 Accepts device entries (`maxWidthPx`, `previewWidthPx`) and the map form.
 */
export function setBreakpoints(next: ReadonlyArray<BreakpointInput> | BreakpointMap): void {
    if (Array.isArray(next)) {
        const entries = (next as ReadonlyArray<BreakpointInput>).flatMap((entry) =>
            entry !== null && typeof entry === 'object' && typeof entry.key === 'string'
                ? [[entry.key, entry] as [string, BreakpointInput]]
                : []
        );
        const resolved = normalizeBreakpoints(entries);

        breakpoints = resolved.legacy;
        deviceBreakpoints = entries.some(([, entry]) => isSet(entry.maxWidthPx))
            ? resolved.devices
            : [...DEFAULT_DEVICE_BREAKPOINTS];

        return;
    }

    const resolved = normalizeBreakpoints(Object.entries(mergeBreakpointLayer(next as BreakpointMap)));

    breakpoints = resolved.legacy;
    deviceBreakpoints = resolved.devices;
}

/** The legacy (mobile-first, min-width) breakpoints. */
export function getBreakpoints(): BreakpointDefinition[] {
    return [...breakpoints];
}

/**
 * The device (desktop-first, max-width) breakpoints, largest first —
 * the order a narrower override must be emitted in to win.
 *
 * @since 1.15.0
 */
export function getDeviceBreakpoints(): DeviceBreakpointDefinition[] {
    return [...deviceBreakpoints];
}

/**
 * Layer a breakpoint map over the defaults. Mirrors
 * `BreakpointRegistry::mergeByKey()`.
 */
function mergeBreakpointLayer(layer: BreakpointMap): Record<string, BreakpointInput> {
    const merged: Record<string, BreakpointInput> = {};

    for (const bp of DEFAULT_BREAKPOINTS) {
        merged[bp.key] = { minWidthPx: bp.minWidthPx, previewWidthPx: bp.minWidthPx };
    }

    for (const bp of DEFAULT_DEVICE_BREAKPOINTS) {
        merged[bp.key] = { maxWidthPx: bp.maxWidthPx, previewWidthPx: bp.previewWidthPx };
    }

    for (const [key, raw] of Object.entries(layer)) {
        if (raw === null || raw === undefined || raw === '') {
            delete merged[key];

            continue;
        }

        const prior = Object.prototype.hasOwnProperty.call(merged, key) ? merged[key] : null;
        const value: BreakpointInput =
            typeof raw === 'object'
                ? { ...raw }
                : prior !== null && isSet(prior.maxWidthPx)
                  ? { maxWidthPx: raw }
                  : { minWidthPx: raw };
        const base: BreakpointInput = prior === null ? {} : { ...prior };

        if (isSet(value.minWidthPx)) {
            delete base.maxWidthPx;
        }

        if (isSet(value.maxWidthPx)) {
            delete base.minWidthPx;
        }

        const entry = { ...base, ...value };

        // A layer that narrows a device without naming a preview width
        // inherits the old one; clamp it into the new range.
        if (prior !== null && isSet(value.maxWidthPx) && !isSet(value.previewWidthPx)) {
            const max = pixels(entry.maxWidthPx);
            const preview = pixels(entry.previewWidthPx);

            if (max !== null && preview !== null && preview > max) {
                entry.previewWidthPx = max;
            }
        }

        merged[key] = entry;
    }

    return merged;
}

/**
 * Validate breakpoint entries and split them into the legacy list
 * (ascending min-width) and the device list (descending max-width).
 * Mirrors `BreakpointRegistry::validate()` and its constructor, dropping
 * what the server would reject.
 */
function normalizeBreakpoints(entries: ReadonlyArray<[string, BreakpointInput]>): {
    legacy: BreakpointDefinition[];
    devices: DeviceBreakpointDefinition[];
} {
    const legacy: BreakpointDefinition[] = [];
    const devices: DeviceBreakpointDefinition[] = [];

    for (const [key, entry] of entries) {
        if (key === 'base' || !/^[a-z0-9][a-z0-9_-]*$/i.test(key) || entry === null || typeof entry !== 'object') {
            continue;
        }

        const hasMin = isSet(entry.minWidthPx);
        const hasMax = isSet(entry.maxWidthPx);

        if (hasMin === hasMax) {
            continue;
        }

        if (hasMin) {
            const minWidthPx = pixels(entry.minWidthPx);

            if (minWidthPx !== null && !legacy.some((bp) => bp.minWidthPx === minWidthPx) && !legacy.some((bp) => bp.key === key)) {
                legacy.push({ key, minWidthPx });
            }

            continue;
        }

        const maxWidthPx = pixels(entry.maxWidthPx);

        if (maxWidthPx === null || devices.some((bp) => bp.maxWidthPx === maxWidthPx || bp.key === key)) {
            continue;
        }

        const preview = isSet(entry.previewWidthPx) ? pixels(entry.previewWidthPx) : maxWidthPx;

        if (preview === null) {
            continue;
        }

        devices.push({ key, maxWidthPx, previewWidthPx: Math.min(preview, maxWidthPx) });
    }

    return {
        legacy: legacy.sort((a, b) => a.minWidthPx - b.minWidthPx),
        devices: devices.sort((a, b) => b.maxWidthPx - a.maxWidthPx),
    };
}

/** A positive pixel count from a number or an `Npx` string, else `null`. */
function pixels(value: unknown): number | null {
    let parsed: number | null = null;

    if (typeof value === 'number') {
        parsed = value;
    } else if (typeof value === 'string') {
        const match = /^\s*(\d+)(px)?\s*$/i.exec(value);
        parsed = match === null ? null : Number.parseInt(match[1], 10);
    }

    return parsed !== null && Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function isSet(value: unknown): boolean {
    return value !== null && value !== undefined;
}

export function filterVisibleBlocks(tree: Block[]): Block[] {
    const out: Block[] = [];

    for (const block of tree) {
        if (block === null || typeof block !== 'object') {
            continue;
        }

        const attrs = attributesOf(block);
        if (attrs !== null && attrs._veHidden === true) {
            continue;
        }

        const innerBlocks = Array.isArray(block.innerBlocks) ? filterVisibleBlocks(block.innerBlocks as Block[]) : block.innerBlocks;

        out.push({
            ...block,
            innerBlocks,
        } as Block);
    }

    return out;
}

export function stampVisibilityScopes(tree: Block[]): { tree: Block[]; css: string } {
    let counter = 0;
    const css: string[] = [];

    const ordered = [...breakpoints]
        .filter((bp) => Number.isFinite(bp.minWidthPx) && bp.minWidthPx > 0)
        .sort((a, b) => a.minWidthPx - b.minWidthPx);

    const indexByKey = new Map(ordered.map((bp, i) => [bp.key, i]));

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object') {
                return block;
            }

            const attrs = attributesOf(block);
            const bpKeys = extractHiddenBreakpoints(attrs);

            const innerBlocks = Array.isArray(block.innerBlocks)
                ? walk(block.innerBlocks as Block[])
                : block.innerBlocks;

            if (bpKeys.length === 0) {
                return { ...block, innerBlocks } as Block;
            }

            counter += 1;
            const scopeClass = `ve-vis-${counter}`;
            const rules: string[] = [];

            for (const key of bpKeys) {
                const position = indexByKey.get(key);
                if (position === undefined) {
                    continue;
                }
                const min = ordered[position].minWidthPx;
                const nextMin = ordered[position + 1]?.minWidthPx;

                if (typeof nextMin === 'number' && nextMin > min) {
                    rules.push(`@media (min-width:${min}px) and (max-width:${nextMin - 1}px){.${scopeClass}{display:none !important;}}`);
                } else {
                    rules.push(`@media (min-width:${min}px){.${scopeClass}{display:none !important;}}`);
                }
            }

            if (rules.length > 0) {
                css.push(rules.join(''));
            }

            return {
                ...block,
                attributes: {
                    ...(attrs ?? {}),
                    _veVisScope: scopeClass,
                },
                innerBlocks,
            } as Block;
        });
    }

    return { tree: walk(tree), css: css.join('') };
}

/**
 * Blocks whose renderer emits a single `<li>` root. They render directly
 * inside a `<ul>` / `<ol>`, where the `<div>` scope wrapper is
 * non-conforming HTML (#806), so `BlockTree` merges the `_veVisScope`
 * class into the block's own `className` instead of wrapping it.
 */
export const LIST_ITEM_BLOCKS: ReadonlySet<string> = new Set([
    'core/navigation-link',
    'core/navigation-submenu',
    'core/list-item',
    'artisanpack/list-item',
]);

function attributesOf(block: Block): Record<string, unknown> | null {
    if (block === null || typeof block !== 'object') {
        return null;
    }

    const attrs = (block as { attributes?: unknown }).attributes;

    if (attrs === null || typeof attrs !== 'object' || Array.isArray(attrs)) {
        return null;
    }

    return attrs as Record<string, unknown>;
}

function extractHiddenBreakpoints(attrs: Record<string, unknown> | null): string[] {
    if (attrs === null) {
        return [];
    }

    const raw = attrs._veHiddenBreakpoints;

    if (!Array.isArray(raw)) {
        return [];
    }

    return raw.filter((k): k is string => typeof k === 'string' && k !== '');
}
