/**
 * Shared CSS-value whitelist for the editor canvas.
 *
 * The same grammar as the Blade `BlockSupports::safeCssValue()` and the
 * React / Vue `safeCssValue()`, so the canvas drops exactly the values
 * the front ends drop. A value passes only when every character is a
 * letter, digit, ASCII whitespace or one of `_ + - * / . , ( ) % #`, so
 * it can't close a declaration (`;`), a rule (`{ }`) or the element it
 * is written into. The comment digraphs and the protocol-relative `//`
 * are rejected too, so it is safe only for non-URL properties.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

const DISALLOWED_CSS_VALUE_CHAR = /[^A-Za-z0-9_+\-*/.,()%#\t\n\r\f ]/;

// PHP `trim()` whitespace, which the Blade twin's empty check uses.
const BLANK_CSS_VALUE = /^[ \t\n\r\0\x0B]*$/;

/**
 * Returns `value` unchanged when it is safe, or `null` when it is blank
 * or carries a disallowed character or digraph. Callers drop the
 * declaration on `null`.
 *
 * @since 1.20.0
 */
export function safeCssValue(value: string): string | null {
    if (BLANK_CSS_VALUE.test(value) || DISALLOWED_CSS_VALUE_CHAR.test(value)) {
        return null;
    }

    if (value.includes('/*') || value.includes('*/') || value.includes('//')) {
        return null;
    }

    return value;
}
