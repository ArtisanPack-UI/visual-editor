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

/**
 * The CSS functions a custom gradient may call. None fetches a resource.
 * Mirrors PHP `CssValue::GRADIENT_FUNCTIONS`.
 *
 * @since 1.20.0
 */
const GRADIENT_FUNCTIONS: ReadonlyArray<string> = [
    'linear-gradient',
    'radial-gradient',
    'conic-gradient',
    'repeating-linear-gradient',
    'repeating-radial-gradient',
    'repeating-conic-gradient',
    'rgb',
    'rgba',
    'hsl',
    'hsla',
    'hwb',
    'lab',
    'lch',
    'oklab',
    'oklch',
    'color',
    'color-mix',
    'var',
    'calc',
    'min',
    'max',
    'clamp',
];

/**
 * Whether a value is one gradient that can't fetch anything: a single
 * `var(--…)` reference, or one `(repeating-)?(linear|radial|conic)-gradient(…)`
 * call whose parentheses balance and close at the end, calling only
 * `GRADIENT_FUNCTIONS`. Checks shape only; callers still apply their own
 * character filter. Mirrors PHP `CssValue::isGradient()`.
 *
 * @since 1.20.0
 */
export function isGradient(value: string): boolean {
    if (/^var\(--[a-zA-Z0-9_-]+\)$/.test(value)) {
        return true;
    }

    if (!/^(?:repeating-)?(?:linear|radial|conic)-gradient\(/i.test(value)) {
        return false;
    }

    for (const match of value.matchAll(/([a-zA-Z_-][a-zA-Z0-9_-]*)\(/g)) {
        if (!GRADIENT_FUNCTIONS.includes(match[1].toLowerCase())) {
            return false;
        }
    }

    let depth = 0;

    for (let index = 0; index < value.length; index++) {
        if ('(' === value[index]) {
            depth++;

            continue;
        }

        if (')' !== value[index]) {
            continue;
        }

        depth--;

        // A close before the end that returns to depth 0 means a second
        // top-level layer follows the gradient.
        if (depth < 0 || (0 === depth && index !== value.length - 1)) {
            return false;
        }
    }

    return 0 === depth;
}
