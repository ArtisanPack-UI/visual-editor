/**
 * Preset slug segment — the editor's twin of `PresetRegistry::presetSlug()`.
 *
 * One rule shared by every preset declaration and `var:preset|…`
 * reference: split lower→upper camelCase boundaries, lowercase, then every
 * character outside `[a-z0-9-]` becomes `-` (`big_gap` → `big-gap`). Runs
 * of `-` are kept and nothing is trimmed.
 *
 * PHP runs this byte-wise (`strtolower()` and a regex without `/u`), so
 * only ASCII is lowercased and a non-ASCII character becomes one `-` per
 * UTF-8 byte (`İnk` → `--nk`). Matching that keeps the editor canvas and
 * saved state CSS pointing at the custom property the server declares.
 * The React and Vue renderers carry the same rule in
 * `support/presetReference.ts`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

/**
 * Normalise one preset slug segment into its custom-property form.
 *
 * @since 1.15.0
 */
export function presetSlug(segment: string): string {
    return segment
        .replace(/([a-z])([A-Z])/g, '$1-$2')
        .replace(/[A-Z]/g, (char) => char.toLowerCase())
        .replace(/[^a-z0-9-]/gu, (char) => '-'.repeat(utf8ByteLength(char)));
}

/** The number of bytes a single code point takes in UTF-8. */
function utf8ByteLength(char: string): number {
    const codePoint = char.codePointAt(0) ?? 0;

    if (codePoint < 0x80) {
        return 1;
    }

    if (codePoint < 0x800) {
        return 2;
    }

    return codePoint < 0x10000 ? 3 : 4;
}
