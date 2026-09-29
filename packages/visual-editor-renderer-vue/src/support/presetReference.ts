/**
 * Preset-reference helpers shared by the support modules (#814).
 *
 * Mirrors `BlockSupports::expandPresetReference()` / `presetSlug()` in
 * the Blade renderer and `PresetRegistry::presetSlug()` on the
 * declaration side, so a `var:preset|spacing|big_gap` reference names
 * exactly the custom property the preset declarations emit.
 *
 * @since 1.12.0
 */

/**
 * Normalise one `var:preset|…` segment into its custom-property form —
 * the one slug rule every declaration and reference site shares: split
 * lower→upper camelCase boundaries, lowercase, then every character
 * outside `[a-z0-9-]` becomes `-` (`big_gap` → `big-gap`). Runs of `-`
 * are kept and nothing is trimmed (`a--b` stays `a--b`).
 */
export function presetSlug(segment: string): string {
    return segment
        .replace(/([a-z])([A-Z])/g, '$1-$2')
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '-');
}

/**
 * Expand Gutenberg's `var:preset|{taxonomy}|{slug}` shorthand into a
 * real CSS `var(--wp--preset--{taxonomy}--{slug})` reference. A
 * non-preset value passes through untouched.
 */
export function expandPresetReference(value: string): string {
    const prefix = 'var:preset|';

    if (!value.startsWith(prefix)) {
        return value;
    }

    const parts = value.slice(prefix.length).split('|').map(presetSlug);

    return `var(--wp--preset--${parts.join('--')})`;
}
