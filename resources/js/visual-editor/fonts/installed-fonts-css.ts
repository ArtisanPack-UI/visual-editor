/**
 * Client-side installed-fonts CSS builder (#636).
 *
 * Produces the same `@font-face` rules and `--wp--preset--font-family--{slug}`
 * custom properties as the server-side
 * {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator}, built
 * straight from the installed-fonts store so a main-document preview — the
 * Styles style-book, which renders inline rather than in the block-editor
 * iframe — can resolve installed-font preset values live. The block-editor
 * canvas iframe still gets the authoritative bundle through the
 * `/global-styles/css` endpoint; this is only for the surfaces that live
 * outside that iframe.
 *
 * The output mirrors the PHP generator (family quoting, `truetype` format
 * normalization, the `", sans-serif"` preset fallback) so a preview resolves a
 * face identically to the published bundle.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.7.0
 */

import type { InstalledFont } from './api-client';
import { fontPresetSlug } from './installed-fonts-store';

/**
 * Whether a code point is a C0/C1 control character, matching the range the
 * PHP generator strips (`\x00-\x1F`, `\x7F-\x9F`).
 *
 * @since 1.7.0
 */
function isControlCodePoint(code: number): boolean {
    return code <= 0x1f || (code >= 0x7f && code <= 0x9f);
}

/**
 * Quote and neutralize a family name for a CSS string context, matching
 * `FontsCssGenerator::quoteFamily()`: strip control characters and angle
 * brackets (kept out of the character set so no literal control byte lives in
 * this source), keep spaces so multi-word families stay intact, then escape the
 * backslash and quote that stay meaningful in the string.
 *
 * @since 1.7.0
 */
function quoteFamily(family: string): string {
    let cleaned = '';

    for (const character of family) {
        const code = character.codePointAt(0) ?? 0;

        if (isControlCodePoint(code) || character === '<' || character === '>') {
            continue;
        }

        cleaned += character;
    }

    const escaped = cleaned.replace(/\\/g, '\\\\').replace(/"/g, '\\"');

    return `"${escaped}"`;
}

/**
 * Neutralize a face URL for the `url("…")` string context. Every self-hosted
 * face path is `Str::slug`-sanitized server-side, so a legitimate URL never
 * contains these characters; escaping here is defense-in-depth so the injected
 * `<style>` can't be broken out of if a future disk driver, signed-URL query,
 * or provider path ever emits one. Strip control characters and angle brackets
 * (a literal `</style>` would otherwise close the element, since this string is
 * rendered as `<style>` text), then escape the backslash and quote that stay
 * meaningful in the CSS string.
 *
 * @since 1.7.0
 */
function escapeUrl(url: string): string {
    let cleaned = '';

    for (const character of url) {
        const code = character.codePointAt(0) ?? 0;

        if (isControlCodePoint(code) || character === '<' || character === '>') {
            continue;
        }

        cleaned += character;
    }

    return cleaned.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

/**
 * Web-font container extensions mapped to their CSS `format()` token, matching
 * `FontsCssGenerator::cssFormat()` and the `font-preview` renderer. A stored
 * format outside this set yields no token so an unexpected value can never be
 * interpolated into the injected `<style>`.
 *
 * @since 1.7.0
 */
const FORMAT_BY_EXTENSION: Record<string, string> = {
    woff2: 'woff2',
    woff: 'woff',
    ttf: 'truetype',
    otf: 'opentype',
};

/**
 * Normalize a stored file format into a whitelisted CSS `format()` token, or
 * `null` when it is not a recognized web-font container.
 *
 * @since 1.7.0
 */
function cssFormat(format: string): string | null {
    return FORMAT_BY_EXTENSION[format.toLowerCase()] ?? null;
}

/**
 * Constrain a face weight to the CSS weight range (1–1000), matching the
 * install path, rather than interpolating whatever the JSON carried.
 *
 * @since 1.7.0
 */
function cssWeight(weight: number): number {
    if (!Number.isFinite(weight)) {
        return 400;
    }

    return Math.min(1000, Math.max(1, Math.trunc(weight)));
}

/**
 * Build the `@font-face` rules and `:root` preset custom properties for the
 * installed fonts. Faces without a resolvable URL are skipped — the browser
 * cannot load them — and a font contributes its preset property regardless.
 * Returns an empty string when nothing is installed.
 *
 * @since 1.7.0
 */
export function buildInstalledFontFacesCss(
    fonts: readonly InstalledFont[]
): string {
    const faceRules: string[] = [];
    const presets: string[] = [];

    for (const font of fonts) {
        for (const face of font.faces) {
            if (face.url === null) {
                continue;
            }

            const safeUrl = escapeUrl(face.url);
            const format = face.format !== null ? cssFormat(face.format) : null;
            const src =
                format !== null
                    ? `src: url("${safeUrl}") format("${format}");`
                    : `src: url("${safeUrl}");`;

            faceRules.push(
                `@font-face {\n` +
                    `\tfont-family: ${quoteFamily(font.family)};\n` +
                    `\tfont-style: ${face.style === 'italic' ? 'italic' : 'normal'};\n` +
                    `\tfont-weight: ${cssWeight(face.weight)};\n` +
                    `\tfont-display: swap;\n` +
                    `\t${src}\n` +
                    `}`
            );
        }

        presets.push(
            `\t--wp--preset--font-family--${fontPresetSlug(font.slug)}: ${quoteFamily(font.family)}, sans-serif;`
        );
    }

    const blocks: string[] = [];

    if (faceRules.length > 0) {
        blocks.push(faceRules.join('\n\n'));
    }

    if (presets.length > 0) {
        blocks.push(`:root {\n${presets.join('\n')}\n}`);
    }

    return blocks.join('\n\n');
}
