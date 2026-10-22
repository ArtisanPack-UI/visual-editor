/**
 * Shared CSS-value whitelist — Vue renderer (#720).
 *
 * The one grammar for "what is a safe user-authored CSS value" written into
 * a `<style>` block. Mirrors the Blade `BlockSupports::safeCssValue()` and
 * the sibling copy in the React renderer so all three renderers drop the same
 * hostile values (kept honest by the `renderer-markup-parity` suite).
 *
 * A value passes only when every character is in the whitelist — letters,
 * digits, ASCII whitespace, and the punctuation that lengths, percentages,
 * `calc()`, and CSS custom properties need (`_ + - * / . , ( ) % #`). The
 * CSS-structural characters that could close a declaration, rule, or the
 * `<style>` element itself (`; { } < > : " ' \ @`) are all excluded, so a
 * stored value can neither inject a sibling declaration/rule nor break out
 * of the tag.
 *
 * The whitespace set is spelled out as the ASCII characters (` \t\n\r\f`)
 * rather than `\s` on purpose: PCRE `\s` (the Blade twin carries no `/u`)
 * and ECMAScript `\s` disagree on Unicode whitespace (e.g. U+00A0), which
 * would let JS keep a value Blade drops. The literal class keeps all three
 * renderers byte-identical.
 *
 * The slash and asterisk are whitelisted (legal `calc()` operators), so the
 * digraphs `/*`, `*` + `/` and `//` are rejected explicitly: an unterminated
 * comment-open would swallow every following rule in the shared `<style>`
 * block, and a double slash is the protocol-relative prefix a `url()` would
 * need. This helper is therefore safe only for non-URL properties.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.7.0
 */

const DISALLOWED_CSS_VALUE_CHAR = /[^A-Za-z0-9_+\-*/.,()%#\t\n\r\f ]/;

/**
 * Return `value` unchanged when it is safe, or `null` when it is empty or
 * carries a disallowed character or CSS comment / protocol-relative digraph.
 * Callers drop the rule on `null` rather than emit a mangled one.
 */
export function safeCssValue( value: string ): string | null {
	// PHP-trim-equivalent empty check: PHP `trim()` (which the Blade twin
	// `BlockSupports::safeCssValue()` uses) strips only " \t\n\r\0\x0B" —
	// NOT the form feed `\f` that JS `String.trim()` also strips. Using
	// `.trim()` here would reject a lone `\f` that PHP keeps (and `\f` is an
	// allowed CSS-value char below), so match PHP's whitespace set exactly.
	if ( /^[ \t\n\r\0\x0B]*$/.test( value ) || DISALLOWED_CSS_VALUE_CHAR.test( value ) ) {
		return null;
	}

	if ( value.includes( '/*' ) || value.includes( '*/' ) || value.includes( '//' ) ) {
		return null;
	}

	return value;
}

/**
 * The CSS functions a custom gradient may call — the gradient functions
 * plus the colour, custom-property and math functions their stops use.
 * None fetches a resource. Mirrors Blade's
 * `BlockSupports::GRADIENT_FUNCTIONS` and the React renderer's copy.
 *
 * @since 1.15.3
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
 * Validate a custom gradient bound for a `background` declaration — the
 * twin of Blade's `BlockSupports::safeGradientValue()` (#920 review).
 *
 * `background` fetches, so the {@link safeCssValue} whitelist (which
 * still rejects `;`, `{`, `}`, `:`, quotes and comment digraphs) is not
 * enough on its own: the value must also be one `var(--…)` reference or
 * one `(repeating-)?(linear|radial|conic)-gradient(…)` call whose
 * parentheses balance and close at the end, calling only
 * `GRADIENT_FUNCTIONS`. Returns the value, or `null` to drop it.
 *
 * @since 1.15.3
 */
export function safeGradientValue( value: string ): string | null {
	if ( safeCssValue( value ) === null ) {
		return null;
	}

	if ( /^var\(--[a-zA-Z0-9_-]+\)$/.test( value ) ) {
		return value;
	}

	if ( ! /^(?:repeating-)?(?:linear|radial|conic)-gradient\(/i.test( value ) ) {
		return null;
	}

	for ( const match of value.matchAll( /([a-zA-Z_-][a-zA-Z0-9_-]*)\(/g ) ) {
		if ( ! GRADIENT_FUNCTIONS.includes( match[ 1 ].toLowerCase() ) ) {
			return null;
		}
	}

	let depth = 0;

	for ( let index = 0; index < value.length; index++ ) {
		if ( value[ index ] === '(' ) {
			depth++;

			continue;
		}

		if ( value[ index ] !== ')' ) {
			continue;
		}

		depth--;

		// A close before the end that returns to depth 0 means a second
		// top-level layer follows the gradient.
		if ( depth < 0 || ( depth === 0 && index !== value.length - 1 ) ) {
			return null;
		}
	}

	return depth === 0 ? value : null;
}

/**
 * Validate a custom `font-family` value — the twin of Blade's
 * `BlockSupports::safeFontFamilyValue()` (#920 review). Each complete
 * quoted family name of letters, digits, spaces, `_`, `.` and `-` is set
 * aside (`"Times New Roman", serif`); the rest must pass
 * {@link safeCssValue}, so an unbalanced quote or one wrapping a
 * structural character still rejects the value. Returns the value, or
 * `null` to drop it.
 *
 * @since 1.15.3
 */
export function safeFontFamilyValue( value: string ): string | null {
	const unquoted = value.replace( /"[a-zA-Z0-9 _.-]*"|'[a-zA-Z0-9 _.-]*'/g, 'x' );

	return safeCssValue( unquoted ) === null ? null : value;
}
