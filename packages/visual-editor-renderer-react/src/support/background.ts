/**
 * Background image support (F-4) — twin of the Blade renderer's
 * `BlockSupports::applyBackground()` (#873).
 *
 * Reads `style.background.backgroundImage` (an `{ url, id, source, title }`
 * object or a bare URL string) plus `backgroundSize`, `backgroundPosition`,
 * `backgroundRepeat` and `backgroundAttachment`, and returns the matching
 * style declarations in Blade's order. Mirrors WordPress core's
 * `wp_render_background_support()` defaults: an image with no size is
 * `cover`, and a `contain` image with no position is centred. Without an
 * image nothing is emitted.
 *
 * The URL goes through `safeUrl()` (the twin of PHP `UrlSanitizer::safe()`)
 * and every ASCII whitespace, quote, parenthesis, backslash, `<`, `>` and
 * `;` is percent-encoded exactly as PHP `rawurlencode()` does, so a stored
 * URL can't close the `url('…')` token. The other values must pass
 * `safeCssValue()`.
 *
 * @since 1.15.0
 */

import { attrRecord, phpTrim } from './attributes';
import { safeCssValue } from './cssValue';
import { safeUrl } from './urlSanitizer';

export interface BackgroundStyle {
    backgroundImage?: string;
    backgroundSize?: string;
    backgroundPosition?: string;
    backgroundRepeat?: string;
    backgroundAttachment?: string;
}

const ATTACHMENTS: ReadonlyArray<string> = ['scroll', 'fixed', 'local'];

/**
 * The background declarations for a block, as a style object with
 * camelCased keys (React and Vue both accept them). Empty when the block
 * has no usable background image.
 */
export function applyBackground(attributes: Record<string, unknown>): BackgroundStyle {
    const background = attrRecord(attrRecord(attributes.style).background);
    const image = background.backgroundImage;
    const rawUrl =
        image !== null && typeof image === 'object'
            ? Array.isArray(image)
                ? undefined
                : (image as Record<string, unknown>).url
            : image;
    const url = typeof rawUrl === 'string' ? safeUrl(rawUrl) : '';

    if (url === '') {
        return {};
    }

    const out: BackgroundStyle = {
        backgroundImage: `url('${encodeBackgroundUrl(url)}')`,
    };

    const size = safeCssValue(stringAttr(background.backgroundSize)) ?? 'cover';
    let position = safeCssValue(stringAttr(background.backgroundPosition));

    if (position === null && size === 'contain') {
        position = '50% 50%';
    }

    out.backgroundSize = size;

    if (position !== null) {
        out.backgroundPosition = position;
    }

    const repeat = safeCssValue(stringAttr(background.backgroundRepeat));

    if (repeat !== null) {
        out.backgroundRepeat = repeat;
    }

    const attachment = stringAttr(background.backgroundAttachment);

    if (ATTACHMENTS.includes(attachment)) {
        out.backgroundAttachment = attachment;
    }

    return out;
}

/**
 * Percent-encode the characters PHP's `/[\s'"()\\<>;]/` matches (PCRE `\s`
 * is ASCII whitespace, vertical tab included) as `rawurlencode()` does.
 */
export function encodeBackgroundUrl(url: string): string {
    return url.replace(/[ \t\n\x0B\f\r'"()\\<>;]/g, (char) =>
        `%${char.charCodeAt(0).toString(16).toUpperCase().padStart(2, '0')}`
    );
}

/**
 * PHP `BlockSupports::stringAttr()`: a PHP-trimmed string, a number as PHP
 * prints it, else `''`.
 */
function stringAttr(value: unknown): string {
    if (typeof value === 'string') {
        return phpTrim(value);
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    return '';
}
