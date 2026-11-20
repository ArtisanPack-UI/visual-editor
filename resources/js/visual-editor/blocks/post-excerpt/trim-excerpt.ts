/**
 * Word-trims a post excerpt to the Post Excerpt block's Excerpt Length (#1139).
 *
 * Mirrors WordPress core's `wp_trim_words()`, which `core/post-excerpt`
 * applies to every excerpt: script and style blocks and tags are
 * stripped, whitespace collapses, and the text is cut to the word count
 * with a trailing ellipsis only when something was cut. Trimming twice is
 * a no-op. This copy trims the editor preview; the Blade renderer's
 * `ExcerptTrimmer` is the reference, mirrored in each JS renderer's
 * `support/excerpt.ts`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.22.0
 */

/** The block's default Excerpt Length (core's `excerpt_length`). */
export const DEFAULT_EXCERPT_LENGTH = 55;

/**
 * Trim `html` to `words` words of plain text.
 *
 * @param html  The resolved excerpt (may hold markup).
 * @param words The block's `excerptLength`; anything that isn't a positive number falls back to 55.
 */
export function trimExcerpt(html: string, words: unknown): string {
    const parsed = typeof words === 'number' ? words : typeof words === 'string' ? Number(words) : NaN;
    const length = Number.isFinite(parsed) && Math.trunc(parsed) > 0 ? Math.trunc(parsed) : DEFAULT_EXCERPT_LENGTH;

    const text = html
        .replace(/<(script|style)[^>]*?>[\s\S]*?<\/\1>/gi, '')
        // A tag ends at the first `>` outside a quoted attribute value, as in
        // PHP's `strip_tags()`.
        .replace(/<(?:[^>"']|"[^"]*"|'[^']*')*>/g, '')
        .replace(/\s+/g, ' ')
        .trim();

    if (text === '') {
        return '';
    }

    const pieces = text.split(' ');

    if (pieces.length <= length) {
        return text;
    }

    return `${pieces.slice(0, length).join(' ')}…`;
}
