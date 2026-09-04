/**
 * Host preset registry — reads the palette, font-size, font-family, and
 * spacing-size presets a host application registered via
 * `config('artisanpack.visual-editor.presets')`, stamped onto the editor
 * mount as the `data-presets` JSON attribute by
 * {@see ArtisanPackUI\VisualEditor\Resources\PresetRegistry}.
 *
 * Consumed by `editor-settings.ts` at module-load time, so the merged
 * defaults reach both `editorSettings.colors` / `.fontSizes` (legacy
 * top-level keys) and every relevant slot inside
 * `__experimentalFeatures` (color.palette.theme, typography.fontSizes,
 * typography.fontFamilies, spacing.spacingSizes). The theme.json layer
 * (`useThemedEditorSettings`) still wins when a theme is installed —
 * this seam is aimed at hosts that don't ship one.
 *
 * The list is resolved at module-import time. Subsequent calls return
 * the cached snapshot — {@see refreshHostPresets} clears it for tests.
 *
 * @since 1.9.0
 */

export type PresetMode = 'append' | 'replace';

export interface PaletteEntry {
    readonly slug: string;
    readonly name: string;
    readonly color: string;
}

export interface FontSizeEntry {
    readonly slug: string;
    readonly name: string;
    readonly size: string;
}

export interface FontFamilyEntry {
    readonly slug: string;
    readonly name: string;
    readonly fontFamily: string;
}

export interface SpacingSizeEntry {
    readonly slug: string;
    readonly name: string;
    readonly size: string;
}

export interface HostPresetList<T> {
    readonly mode: PresetMode;
    readonly entries: ReadonlyArray<T>;
}

export interface HostPresets {
    readonly palette: HostPresetList<PaletteEntry>;
    readonly fontSizes: HostPresetList<FontSizeEntry>;
    readonly fontFamilies: HostPresetList<FontFamilyEntry>;
    readonly spacingSizes: HostPresetList<SpacingSizeEntry>;
}

// Mirrors the PHP registry's safe-slug pattern. A slug lands in
// generated CSS class names and saved block attributes, so anything
// outside this set is dropped defensively rather than trusting the
// server side to have already filtered it.
const SAFE_SLUG_PATTERN = /^[a-z0-9_-]+$/;

const EMPTY_LIST = <T>(): HostPresetList<T> => ({
    mode: 'append',
    entries: Object.freeze([]) as ReadonlyArray<T>,
});

const EMPTY_PRESETS: HostPresets = Object.freeze({
    palette: EMPTY_LIST<PaletteEntry>(),
    fontSizes: EMPTY_LIST<FontSizeEntry>(),
    fontFamilies: EMPTY_LIST<FontFamilyEntry>(),
    spacingSizes: EMPTY_LIST<SpacingSizeEntry>(),
});

let cached: HostPresets | null = null;

function resolveMode(value: unknown): PresetMode {
    return value === 'replace' ? 'replace' : 'append';
}

function normaliseSlug(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }
    const slug = value.trim().toLowerCase();
    if (slug === '' || !SAFE_SLUG_PATTERN.test(slug)) {
        return null;
    }
    return slug;
}

function normaliseName(value: unknown, slug: string): string {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }
    return slug;
}

function normaliseString(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
}

type EntryNormaliser<T> = (raw: Record<string, unknown>, slug: string) => T | null;

function normaliseList<T extends { slug: string }>(
    raw: unknown,
    normaliseEntry: EntryNormaliser<T>,
): HostPresetList<T> {
    if (raw === null || raw === undefined || typeof raw !== 'object') {
        return EMPTY_LIST<T>();
    }

    const record = raw as { mode?: unknown; entries?: unknown };
    const rawEntries = Array.isArray(record.entries)
        ? record.entries
        : Array.isArray(raw)
          ? (raw as unknown[])
          : [];

    const mode = resolveMode(record.mode);

    const entries: T[] = [];
    const seen = new Set<string>();

    for (const entry of rawEntries) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }
        const record = entry as Record<string, unknown>;
        const slug = normaliseSlug(record.slug);
        if (slug === null || seen.has(slug)) {
            continue;
        }
        const normalised = normaliseEntry(record, slug);
        if (normalised === null) {
            continue;
        }
        seen.add(slug);
        entries.push(normalised);
    }

    return {
        mode,
        entries: Object.freeze(entries),
    };
}

function parsePresets(raw: string): HostPresets {
    let parsed: unknown;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return EMPTY_PRESETS;
    }

    if (parsed === null || typeof parsed !== 'object') {
        return EMPTY_PRESETS;
    }

    const record = parsed as Record<string, unknown>;

    return {
        palette: normaliseList<PaletteEntry>(record.palette, (entry, slug) => {
            const color = normaliseString(entry.color);
            if (color === null) {
                return null;
            }
            return { slug, name: normaliseName(entry.name, slug), color };
        }),
        fontSizes: normaliseList<FontSizeEntry>(record.fontSizes, (entry, slug) => {
            const size = normaliseString(entry.size);
            if (size === null) {
                return null;
            }
            return { slug, name: normaliseName(entry.name, slug), size };
        }),
        fontFamilies: normaliseList<FontFamilyEntry>(record.fontFamilies, (entry, slug) => {
            const fontFamily = normaliseString(entry.fontFamily);
            if (fontFamily === null) {
                return null;
            }
            return { slug, name: normaliseName(entry.name, slug), fontFamily };
        }),
        spacingSizes: normaliseList<SpacingSizeEntry>(record.spacingSizes, (entry, slug) => {
            const size = normaliseString(entry.size);
            if (size === null) {
                return null;
            }
            return { slug, name: normaliseName(entry.name, slug), size };
        }),
    };
}

function parseFromElement(element: Element | null): HostPresets | null {
    if (element === null || !(element instanceof HTMLElement)) {
        return null;
    }
    const raw = element.dataset.presets?.trim();
    if (!raw) {
        return null;
    }
    return parsePresets(raw);
}

/**
 * Returns the host-provided presets stamped onto the editor mount.
 * Reads the post-editor mount first, then the site-editor mount, then
 * falls back to an empty snapshot (which the merge helpers below treat
 * as a no-op).
 *
 * @since 1.9.0
 */
export function getHostPresets(): HostPresets {
    if (cached !== null) {
        return cached;
    }

    if (typeof document === 'undefined') {
        cached = EMPTY_PRESETS;
        return cached;
    }

    const parsed =
        parseFromElement(document.querySelector('[data-ap-visual-editor]')) ??
        parseFromElement(document.querySelector('[data-ap-site-editor]'));

    cached = parsed ?? EMPTY_PRESETS;
    return cached;
}

/**
 * Merge one host preset list into the package defaults per its mode:
 *   - `append`: package defaults first, host entries appended. A host
 *     slug that collides with a default slug replaces that default in
 *     place (typical CSS-cascade behaviour).
 *   - `replace`: host entries only. Empty host entries under `replace`
 *     still yield the package defaults so a mis-configured empty
 *     replacement can't blank the picker.
 *
 * @since 1.9.0
 */
export function mergePresetList<T extends { slug: string }>(
    defaults: ReadonlyArray<T>,
    hostList: HostPresetList<T>,
): ReadonlyArray<T> {
    if (hostList.entries.length === 0) {
        return defaults;
    }

    if (hostList.mode === 'replace') {
        return hostList.entries;
    }

    // Append with slug-collision override: a host entry whose slug
    // matches a default's slug replaces that default in its original
    // position, so brand overrides stay predictable regardless of
    // insertion order.
    const hostBySlug = new Map<string, T>();
    for (const entry of hostList.entries) {
        hostBySlug.set(entry.slug, entry);
    }

    const merged: T[] = [];
    const overridden = new Set<string>();

    for (const entry of defaults) {
        const override = hostBySlug.get(entry.slug);
        if (override !== undefined) {
            merged.push(override);
            overridden.add(entry.slug);
        } else {
            merged.push(entry);
        }
    }

    for (const entry of hostList.entries) {
        if (!overridden.has(entry.slug)) {
            merged.push(entry);
        }
    }

    return merged;
}

/**
 * Test-only: forget the cached snapshot so the next call re-reads the
 * DOM. Used by Vitest suites that swap the mount markup between cases.
 *
 * @internal
 */
export function refreshHostPresets(): void {
    cached = null;
}
