/**
 * Sanitises a resolved binding value for rendering as markup (#1041).
 *
 * Binding sources return stored values as they are, and a block inside
 * Single Content or a Query Loop row reads them from a record that any
 * other author may have written. `safeHTML` from `@wordpress/dom` isn't
 * enough for that: it removes only scripts and `on*` attributes, so an
 * `<iframe srcdoc>` or a `javascript:` link would still run in the
 * editor's origin. This keeps an allowlist of inline formatting instead.
 *
 * The value is parsed in an inert document, so nothing loads or runs
 * while it's being cleaned. Inline `style` is dropped, so a preview can
 * lack a highlight colour the front end shows.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

const ALLOWED_TAGS = new Set( [
	'a',
	'abbr',
	'b',
	'bdi',
	'bdo',
	'br',
	'cite',
	'code',
	'del',
	'em',
	'i',
	'ins',
	'kbd',
	'mark',
	'q',
	's',
	'small',
	'span',
	'strong',
	'sub',
	'sup',
	'u',
] );

// Removed with their contents. Every other disallowed tag is unwrapped,
// so a stray `<div>` in a field keeps its text.
const REMOVED_TAGS = new Set( [
	'embed',
	'iframe',
	'math',
	'noscript',
	'object',
	'script',
	'style',
	'svg',
	'template',
] );

const ALLOWED_ATTRIBUTES = new Set( [ 'class', 'href', 'rel', 'target' ] );

const SAFE_URL_PROTOCOLS = new Set( [ 'http:', 'https:', 'mailto:', 'tel:' ] );

// Resolves relative links; only its protocol is ever read.
const RELATIVE_URL_BASE = 'https://relative.invalid';

function isSafeUrl( value: string ): boolean {
	try {
		return SAFE_URL_PROTOCOLS.has( new URL( value, RELATIVE_URL_BASE ).protocol );
	} catch {
		return false;
	}
}

function sanitizeAttributes( element: Element ): void {
	for ( const { name, value } of Array.from( element.attributes ) ) {
		const allowed = ALLOWED_ATTRIBUTES.has( name )
			&& ( 'href' !== name || isSafeUrl( value ) );

		if ( ! allowed ) {
			element.removeAttribute( name );
		}
	}
}

function sanitizeChildren( parent: Element ): void {
	for ( const child of Array.from( parent.childNodes ) ) {
		if ( child.nodeType === child.TEXT_NODE ) {
			continue;
		}

		if ( child.nodeType !== child.ELEMENT_NODE ) {
			child.remove();
			continue;
		}

		const element = child as Element;
		const tag     = element.tagName.toLowerCase();

		if ( REMOVED_TAGS.has( tag ) ) {
			element.remove();
			continue;
		}

		sanitizeChildren( element );

		if ( ALLOWED_TAGS.has( tag ) ) {
			sanitizeAttributes( element );
		} else {
			element.replaceWith( ...Array.from( element.childNodes ) );
		}
	}
}

/**
 * Reduce a resolved value to inline formatting that's safe to render.
 *
 * @since 1.20.0
 */
export function sanitizeBoundHtml( html: string ): string {
	const { body } = document.implementation.createHTMLDocument( '' );

	body.innerHTML = html;
	sanitizeChildren( body );

	return body.innerHTML;
}
