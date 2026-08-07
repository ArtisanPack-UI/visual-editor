/**
 * Rewrites the `:root` selectors in the active theme's compiled
 * global-styles CSS so they scope to the editor canvas instead of the
 * host document's `<html>` element (#679).
 *
 * cms-framework's `GlobalStylesEmitter::emit()` follows the WordPress
 * convention of declaring `--wp--preset--*` tokens *and* theme.json's
 * root spacing on `:root`. That's correct for the front end and for a
 * canvas rendered inside a `<BlockCanvas>` iframe, where `:root` is the
 * iframe's own document. The site editor mounts inline (#418), so the
 * unmodified output matches the host `<html>` and pushes the whole
 * editor shell — top bar, navigator, inspector — in by the theme's root
 * padding.
 *
 * Rewriting `:root` to `.editor-styles-wrapper` is a strict narrowing:
 * the tokens land on the canvas surface element instead of the
 * document root, every block in the canvas still inherits them, and
 * theme root spacing applies where a theme author expects it.
 * Specificity is unchanged — both selectors weigh (0, 1, 0).
 *
 * The rewrite deliberately skips `:root` occurrences inside comments,
 * quoted strings, and unquoted `url()` values, so a `content` string or
 * an SVG data URI carrying its own `<style>` survives untouched.
 */

/**
 * Canvas surface class both site-editor canvases render
 * ({@see EntityEditorCanvas}, {@see PatternCanvas}). Gutenberg's own
 * editor styles use the same hook.
 */
export const CANVAS_SCOPE_SELECTOR = '.editor-styles-wrapper';

const ROOT_SELECTOR = ':root';

/**
 * Characters that would make `:root…` a different identifier (there is
 * no such pseudo-class today, but the guard keeps the rewrite honest if
 * one ever appears).
 */
const IDENT_CHAR = /[A-Za-z0-9_-]/;

/** Opening of a `url()` value, in either case. */
const URL_FUNCTION = /^url\($/i;

/**
 * Replaces every `:root` selector in the given CSS with the canvas
 * scope selector.
 *
 * @param css Raw CSS from the global-styles endpoint.
 *
 * @return CSS with `:root` rewritten to {@see CANVAS_SCOPE_SELECTOR}.
 */
export function scopeGlobalStylesCss(css: string): string {
    if (css === '') {
        return css;
    }

    let out = '';
    let index = 0;

    while (index < css.length) {
        const char = css[index];

        // Skip block comments wholesale — `:root` mentioned in the
        // emitter's own annotations must not be rewritten.
        if (char === '/' && css[index + 1] === '*') {
            const end = css.indexOf('*/', index + 2);
            const stop = -1 === end ? css.length : end + 2;
            out += css.slice(index, stop);
            index = stop;

            continue;
        }

        // Skip quoted strings — `content: ":root"` and `url(":root")`
        // are values, not selectors.
        if (char === '"' || char === "'") {
            const stop = findStringEnd(css, index);
            out += css.slice(index, stop);
            index = stop;

            continue;
        }

        // Skip unquoted `url()` values wholesale. An SVG data URI can
        // embed its own `<style>` block, and rewriting a selector
        // inside the image payload would corrupt the asset. Quoted
        // URLs fall through to the string skipper above, which tracks
        // escapes and so handles a `)` inside the URL correctly.
        if (
            (char === 'u' || char === 'U') &&
            URL_FUNCTION.test(css.slice(index, index + 4))
        ) {
            out += css.slice(index, index + 4);
            index += 4;

            if (!startsQuotedValue(css, index)) {
                const end = css.indexOf(')', index);
                const stop = -1 === end ? css.length : end + 1;
                out += css.slice(index, stop);
                index = stop;
            }

            continue;
        }

        if (char === ':' && css.startsWith(ROOT_SELECTOR, index)) {
            const next = css[index + ROOT_SELECTOR.length];

            if (next === undefined || !IDENT_CHAR.test(next)) {
                out += CANVAS_SCOPE_SELECTOR;
                index += ROOT_SELECTOR.length;

                continue;
            }
        }

        out += char;
        index += 1;
    }

    return out;
}

/**
 * Reports whether the value starting at `index` (after any leading
 * whitespace) opens with a quote — i.e. `url( "…" )` rather than
 * `url(…)`.
 *
 * @param css   CSS being scanned.
 * @param index Index just past the `url(`.
 *
 * @return True when the URL is quoted.
 */
function startsQuotedValue(css: string, index: number): boolean {
    let cursor = index;

    while (cursor < css.length && /\s/.test(css[cursor])) {
        cursor += 1;
    }

    return css[cursor] === '"' || css[cursor] === "'";
}

/**
 * Returns the index just past the closing quote of the string starting
 * at `start`, honouring backslash escapes. Falls back to the end of the
 * input for an unterminated string.
 *
 * @param css   CSS being scanned.
 * @param start Index of the opening quote.
 *
 * @return Index one past the closing quote.
 */
function findStringEnd(css: string, start: number): number {
    const quote = css[start];
    let index = start + 1;

    while (index < css.length) {
        const char = css[index];

        if (char === '\\') {
            index += 2;

            continue;
        }

        if (char === quote) {
            return index + 1;
        }

        index += 1;
    }

    return css.length;
}
