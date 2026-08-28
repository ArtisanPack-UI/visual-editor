/**
 * Pure rich-text helpers for the Dynamic-Content-aware link format (#662).
 *
 * Kept free of `@wordpress/*` imports so they unit-test without mocking
 * the editor runtime.
 *
 * @since 1.7.0
 */

import { LINK_FORMAT_NAME } from './constants';

export interface RichTextFormatObject {
    type: string;
    attributes?: Record<string, string>;
}

export interface RichTextValueLike {
    text: string;
    start?: number;
    end?: number;
    formats: Array<RichTextFormatObject[] | undefined>;
    replacements?: unknown[];
}

export interface LinkFormatInput {
    url?: string;
    opensInNewTab?: boolean;
}

// Mirrors `@wordpress/url`'s `prependHTTP` guard: a value that already
// begins with a scheme, `#`, `?`, `.`, or `/` is a usable href and must
// not be prefixed.
const USABLE_HREF_REGEXP = /^(?:[a-z][a-z0-9+.-]*:|#|\?|\.|\/)/i;
const EMAIL_REGEXP = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Schemes the server's `DynamicContentSource::SAFE_URL_SCHEMES` allowlist
// and Blade `UrlSanitizer` reject. Matched after stripping the ASCII
// whitespace / control characters a browser ignores when resolving the
// scheme, so `java\tscript:` can't slip through.
const DISALLOWED_SCHEME_REGEXP = /^(?:javascript|data|vbscript):/i;

/**
 * Normalize a link URL the way the stock link format does via
 * `@wordpress/url`'s `prependHTTPS` — a bare host like `example.com`
 * becomes `https://example.com` so it isn't stored as a relative href.
 *
 * Dynamic Content tokens are preserved untouched: a bare `{{token}}`,
 * `mailto:{{token}}`, or `tel:{{token}}` is already scheme-correct (built
 * by `buildDynamicContentHref`), and prefixing a bare `{{token}}` would
 * corrupt it.
 *
 * @since 1.7.0
 *
 * @param  string  url  The raw URL from the link popover.
 *
 * @return The normalized URL.
 */
export function normalizeLinkUrl(url: string | undefined): string {
    const trimmed = (url ?? '').trim();
    if (!trimmed) return trimmed;
    // Drop dangerous schemes outright, matching the server. Checked before
    // the Dynamic Content passthrough so `javascript:{{token}}` can't slip
    // by, and against a control-char-stripped copy so obfuscated schemes
    // don't evade the test.
    if (DISALLOWED_SCHEME_REGEXP.test(trimmed.replace(/[\u0000-\u0020]+/g, ''))) {
        return '';
    }
    // Leave Dynamic Content tokens (bare or scheme-prefixed) alone.
    if (trimmed.includes('{{')) return trimmed;
    // Never rewrite an explicit http:// (matches prependHTTPS).
    if (trimmed.startsWith('http://')) return trimmed;
    // Only a bare, TLD-looking host gets a scheme.
    if (!USABLE_HREF_REGEXP.test(trimmed) && !EMAIL_REGEXP.test(trimmed)) {
        return `https://${trimmed}`;
    }
    return trimmed;
}

/**
 * Build the `core/link` format object, applying the "open in new tab"
 * rel/target pair the same way `@wordpress/format-library` does and
 * normalizing the URL via {@link normalizeLinkUrl}.
 *
 * @since 1.7.0
 *
 * @param  LinkFormatInput  next  The link values chosen in the popover.
 *
 * @return The `core/link` format object.
 */
export function buildLinkFormat(next: LinkFormatInput): RichTextFormatObject {
    const attributes: Record<string, string> = { url: normalizeLinkUrl(next.url) };

    if (next.opensInNewTab) {
        attributes.target = '_blank';
        attributes.rel = 'noreferrer noopener';
    }

    return { type: LINK_FORMAT_NAME, attributes };
}

/** The `core/link` format object at a given character index, if any. */
function linkAt(
    formats: Array<RichTextFormatObject[] | undefined>,
    index: number
): RichTextFormatObject | undefined {
    if (index < 0 || index >= formats.length || !Array.isArray(formats[index])) {
        return undefined;
    }
    return (formats[index] as RichTextFormatObject[]).find((f) => f.type === LINK_FORMAT_NAME);
}

/** Two link runs are the same link only when href/target/rel all match. */
function sameLink(a: RichTextFormatObject | undefined, b: RichTextFormatObject | undefined): boolean {
    if (!a || !b) return false;
    return (
        a.attributes?.url === b.attributes?.url &&
        a.attributes?.target === b.attributes?.target &&
        a.attributes?.rel === b.attributes?.rel
    );
}

/**
 * Find the contiguous `[start, end)` range of the single `core/link` run
 * that covers the caret, so an edit/remove targets exactly that link even
 * when the selection is collapsed inside it. Adjacent links with
 * different attributes are treated as separate runs and are not merged.
 *
 * @since 1.7.0
 *
 * @param  RichTextValueLike  value  The current rich-text value.
 *
 * @return The `[start, end)` tuple, or `null` when the caret isn't on a link.
 */
export function activeLinkRange(value: RichTextValueLike): [number, number] | null {
    const formats = value.formats;
    const caret = value.start ?? 0;

    // The caret sits *between* characters. Probe the character to the
    // right first, then the one to the left (caret at the link's end).
    let index = caret;
    let base = linkAt(formats, index);
    if (!base) {
        index = caret - 1;
        base = linkAt(formats, index);
    }
    if (!base) {
        return null;
    }

    let start = index;
    while (start > 0 && sameLink(linkAt(formats, start - 1), base)) {
        start--;
    }

    let end = index + 1;
    while (sameLink(linkAt(formats, end), base)) {
        end++;
    }

    return [start, end];
}
