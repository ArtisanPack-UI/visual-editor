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
 * Rewriting a bare `:root` to `.editor-styles-wrapper` narrows the tokens
 * onto the canvas surface element instead of the document root: every
 * block in the canvas still inherits them, and theme root spacing applies
 * where a theme author expects it. Specificity is unchanged — both
 * selectors weigh (0, 1, 0).
 *
 * A *compound* `:root` selector — `:root.dark`, `:root[data-theme="dark"]`,
 * … — is rewritten to `:where(html<suffix>) .editor-styles-wrapper`
 * instead. Themes toggle their dark-mode class/attribute on `<html>`, and
 * the site editor mounts inline under that same `<html>`, so scoping the
 * rule to a `html<suffix>` ancestor keeps dark-mode overrides applying
 * inside the canvas; the zero-specificity `:where()` leaves the weight at
 * (0, 1, 0) so it behaves like the bare-`:root` rewrite. (Rewriting it to
 * `.editor-styles-wrapper<suffix>` — the pre-#M3 behavior — silently
 * stopped matching, because the class never lands on the wrapper itself.)
 *
 * Scope is deliberately limited to `:root`. A theme's hand-authored
 * `themes/{slug}/style.css` can also carry bare `html { … }` / `body { … }`
 * / element rules; those are **not** rewritten here and, in the inline site
 * editor, style the editor shell unscoped (the same bug class #679 fixed
 * for `:root`). Full selector-list scoping is intentionally out of scope
 * for this pass to avoid a fragile in-house CSS parser in a rendering path;
 * it is tracked as a follow-up.
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
        //
        // The `url(` must be a function start, not the tail of a longer
        // ident (`blur(`, a `--my-url(` custom name): a preceding ident
        // character disqualifies it.
        if (
            (char === 'u' || char === 'U') &&
            URL_FUNCTION.test(css.slice(index, index + 4)) &&
            !(index > 0 && IDENT_CHAR.test(css[index - 1]))
        ) {
            out += css.slice(index, index + 4);
            index += 4;

            if (!startsQuotedValue(css, index)) {
                const stop = findUnquotedUrlEnd(css, index);
                out += css.slice(index, stop);
                index = stop;
            }

            continue;
        }

        if (char === ':' && matchesRootSelector(css, index)) {
            const next = css[index + ROOT_SELECTOR.length];

            // `:rootXXX` is a different identifier — leave it untouched.
            if (next !== undefined && IDENT_CHAR.test(next)) {
                out += char;
                index += 1;

                continue;
            }

            // A compound `:root` selector (`:root.dark`, `:root[…]`, a
            // pseudo) keeps its suffix on an `html` ancestor so themes that
            // toggle the class/attribute on `<html>` still reach the canvas.
            if (next === '.' || next === '#' || next === '[' || next === ':') {
                const suffixEnd = readCompoundSuffix(css, index + ROOT_SELECTOR.length);
                const suffix = css.slice(index + ROOT_SELECTOR.length, suffixEnd);
                out += `:where(html${suffix}) ${CANVAS_SCOPE_SELECTOR}`;
                index = suffixEnd;

                continue;
            }

            // Bare `:root`.
            out += CANVAS_SCOPE_SELECTOR;
            index += ROOT_SELECTOR.length;

            continue;
        }

        out += char;
        index += 1;
    }

    return out;
}

/**
 * Case-insensitive `:root` match at `index` (CSS pseudo-class names are
 * case-insensitive, so a theme emitting `:ROOT` scopes the same way).
 *
 * @param css   CSS being scanned.
 * @param index Index of the `:` believed to open the selector.
 *
 * @return True when `:root` begins at `index`.
 */
function matchesRootSelector(css: string, index: number): boolean {
    return (
        css.slice(index, index + ROOT_SELECTOR.length).toLowerCase() === ROOT_SELECTOR
    );
}

/**
 * Returns the index just past the closing `)` of an unquoted `url()`
 * value, honouring `\)` escapes so an escaped paren inside the URL doesn't
 * end the skip early. Falls back to the end of the input for an
 * unterminated value.
 *
 * @param css   CSS being scanned.
 * @param index Index just past the `url(`.
 *
 * @return Index one past the closing `)`.
 */
function findUnquotedUrlEnd(css: string, index: number): number {
    let cursor = index;

    while (cursor < css.length) {
        const char = css[cursor];

        if (char === '\\') {
            cursor += 2;

            continue;
        }

        if (char === ')') {
            return cursor + 1;
        }

        cursor += 1;
    }

    return css.length;
}

/**
 * Reads the compound-selector suffix that immediately follows `:root`
 * (class, id, attribute, and pseudo fragments) and returns the index one
 * past it. Attribute selectors and functional pseudos are traversed with
 * their quotes/parens balanced so a `]`, `)`, or combinator inside them
 * doesn't end the suffix early. Stops at the first combinator or selector
 * terminator (whitespace, `,`, `{`, `>`, `+`, `~`).
 *
 * @param css   CSS being scanned.
 * @param start Index just past the `:root`.
 *
 * @return Index one past the compound suffix.
 */
function readCompoundSuffix(css: string, start: number): number {
    let cursor = start;

    while (cursor < css.length) {
        const char = css[cursor];

        if (char === '.' || char === '#' || char === ':' || IDENT_CHAR.test(char)) {
            cursor += 1;

            continue;
        }

        if (char === '[') {
            cursor += 1;

            while (cursor < css.length && css[cursor] !== ']') {
                if (css[cursor] === '"' || css[cursor] === "'") {
                    cursor = findStringEnd(css, cursor);

                    continue;
                }

                cursor += 1;
            }

            if (cursor < css.length) {
                cursor += 1;
            }

            continue;
        }

        if (char === '(') {
            let depth = 1;
            cursor += 1;

            while (cursor < css.length && depth > 0) {
                const inner = css[cursor];

                if (inner === '"' || inner === "'") {
                    cursor = findStringEnd(css, cursor);

                    continue;
                }

                if (inner === '(') {
                    depth += 1;
                } else if (inner === ')') {
                    depth -= 1;
                }

                cursor += 1;
            }

            continue;
        }

        break;
    }

    return cursor;
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
