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
	if ( value.trim() === '' || DISALLOWED_CSS_VALUE_CHAR.test( value ) ) {
		return null;
	}

	if ( value.includes( '/*' ) || value.includes( '*/' ) || value.includes( '//' ) ) {
		return null;
	}

	return value;
}
