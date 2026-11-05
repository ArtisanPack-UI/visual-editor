<?php

/**
 * Allowlist sanitizer for HTML stored in block attributes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;
use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Reduces an HTML fragment to the elements and attributes in
 * {@see self::ALLOWED_TAGS} (#1062).
 *
 * The result is written out by {@see self::serialize()}, never returned
 * from the parser or passed through from the input, so a difference
 * between how libxml and a browser read the input can't reach the page:
 * what's stored is canonical markup that holds only allowed tags, with
 * every attribute quoted and every text node escaped. The one exception
 * is `<svg>`, which is written out by {@see SvgSanitizer} and rejected here
 * if it holds a comment, processing instruction or CDATA section.
 *
 * Elements in {@see self::REMOVED_TAGS} go with their contents. Any other
 * tag that isn't allowed is unwrapped, so its text survives. `<svg>` goes
 * through {@see SvgSanitizer}, which is how a custom inline icon stays in
 * rich text.
 *
 * Hosts can change the allowlist with the
 * `ap.visualEditor.contentFilter.allowedHtml` filter. A tag added there
 * wins over {@see self::REMOVED_TAGS}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class BlockHtmlSanitizer
{
	/**
	 * Attributes any allowed tag may carry, along with `aria-*`.
	 *
	 * @since 1.20.0
	 *
	 * @var list<string>
	 */
	public const GLOBAL_ATTRIBUTES = [
		'class',
		'data-icon-name',
		'data-icon-set',
		'data-id',
		'data-type',
		'dir',
		'id',
		'lang',
		'role',
		'style',
		'title',
	];

	/**
	 * Allowed tags, each with the attributes it adds to
	 * {@see self::GLOBAL_ATTRIBUTES}.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, list<string>>
	 */
	public const ALLOWED_TAGS = [
		'a'          => [ 'download', 'href', 'hreflang', 'rel', 'target' ],
		'abbr'       => [],
		'address'    => [],
		'annotation' => [ 'encoding' ],
		'article'    => [],
		'aside'      => [],
		'audio'      => [ 'controls', 'loop', 'muted', 'preload', 'src' ],
		'b'          => [],
		'bdi'        => [],
		'bdo'        => [],
		'blockquote' => [ 'cite' ],
		'br'         => [],
		'caption'    => [],
		'cite'       => [],
		'code'       => [],
		'col'        => [ 'span' ],
		'colgroup'   => [ 'span' ],
		'dd'         => [],
		'del'        => [ 'cite', 'datetime' ],
		'details'    => [ 'name', 'open' ],
		'dfn'        => [],
		'div'        => [],
		'dl'         => [],
		'dt'         => [],
		'em'         => [],
		'figcaption' => [],
		'figure'     => [],
		'footer'     => [],
		'h1'         => [],
		'h2'         => [],
		'h3'         => [],
		'h4'         => [],
		'h5'         => [],
		'h6'         => [],
		'header'     => [],
		'hr'         => [],
		'i'          => [],
		'img'        => [ 'alt', 'decoding', 'height', 'loading', 'sizes', 'src', 'srcset', 'width' ],
		'ins'        => [ 'cite', 'datetime' ],
		'kbd'        => [],
		'li'         => [ 'value' ],
		'main'       => [],
		'mark'       => [],
		'math'       => [ 'data-latex', 'display', 'xmlns' ],
		'mfrac'      => [ 'linethickness' ],
		'mi'         => [ 'mathvariant' ],
		'mn'         => [],
		'mo'         => [ 'accent', 'fence', 'form', 'largeop', 'lspace', 'movablelimits', 'rspace', 'separator', 'stretchy' ],
		'mover'      => [ 'accent' ],
		'mroot'      => [],
		'mrow'       => [],
		'mspace'     => [ 'depth', 'height', 'width' ],
		'msqrt'      => [],
		'mstyle'     => [ 'displaystyle', 'mathvariant', 'scriptlevel' ],
		'msub'       => [],
		'msubsup'    => [],
		'msup'       => [],
		'mtable'     => [],
		'mtd'        => [],
		'mtext'      => [],
		'mtr'        => [],
		'munder'     => [ 'accentunder' ],
		'munderover' => [ 'accent', 'accentunder' ],
		'nav'        => [],
		'ol'         => [ 'reversed', 'start', 'type' ],
		'p'          => [],
		'picture'    => [],
		'pre'        => [],
		'q'          => [ 'cite' ],
		's'          => [],
		'samp'       => [],
		'section'    => [],
		'semantics'  => [],
		'small'      => [],
		'source'     => [ 'height', 'media', 'sizes', 'src', 'srcset', 'type', 'width' ],
		'span'       => [],
		'strong'     => [],
		'sub'        => [],
		'summary'    => [],
		'sup'        => [],
		'table'      => [],
		'tbody'      => [],
		'td'         => [ 'colspan', 'headers', 'rowspan' ],
		'tfoot'      => [],
		'th'         => [ 'abbr', 'colspan', 'headers', 'rowspan', 'scope' ],
		'thead'      => [],
		'time'       => [ 'datetime' ],
		'tr'         => [],
		'track'      => [ 'default', 'kind', 'label', 'src', 'srclang' ],
		'u'          => [],
		'ul'         => [],
		'var'        => [],
		'video'      => [ 'controls', 'height', 'loop', 'muted', 'playsinline', 'poster', 'preload', 'src', 'width' ],
		'wbr'        => [],
	];

	/**
	 * Tags removed with everything inside them.
	 *
	 * @since 1.20.0
	 *
	 * @var list<string>
	 */
	public const REMOVED_TAGS = [
		'applet',
		'base',
		'button',
		'embed',
		'frame',
		'frameset',
		'head',
		'iframe',
		'input',
		'link',
		'math',
		'meta',
		'noembed',
		'noframes',
		'noscript',
		'object',
		'plaintext',
		'script',
		'select',
		'style',
		'svg',
		'template',
		'textarea',
		'title',
		'xmp',
	];

	/**
	 * Inline CSS properties a `style` attribute may keep, as a pattern. It
	 * covers what the editor writes, and leaves out `position`, which
	 * would let markup cover the page.
	 *
	 * @since 1.20.0
	 */
	protected const SAFE_STYLE_PROPERTY = '/^(?:--[a-z0-9-]+|aspect-ratio|background(?:-color)?|border(?:-[a-z-]+)?|color|display|float|font(?:-[a-z-]+)?|gap|height|letter-spacing|line-height|margin(?:-[a-z-]+)?|max-(?:height|width)|min-(?:height|width)|object-(?:fit|position)|opacity|padding(?:-[a-z-]+)?|text-[a-z-]+|vertical-align|white-space|width|word-(?:break|spacing|wrap)|writing-mode)$/';

	/**
	 * CSS value syntax that can load a resource or hide what follows it.
	 *
	 * @since 1.20.0
	 */
	protected const UNSAFE_STYLE_VALUE = '/url\s*\(|image-set|expression|javascript:|@|\\\\|\/\*|<|>/i';

	/** Attributes holding one URL. */
	protected const URL_ATTRIBUTES = [ 'cite', 'href', 'poster', 'src' ];

	/** A base64 raster image, the one `data:` URL an `<img>` may keep. */
	protected const INLINE_IMAGE = '/^data:image\/(?:gif|jpeg|png|webp);base64,[a-z0-9+\/=\s]+$/i';

	/** Where the extracted `<svg>` strings go back in. */
	protected const SVG_PLACEHOLDER = 'ap-sanitized-svg';

	/**
	 * Sanitized `<svg>` strings from the fragment being processed.
	 *
	 * @var list<string>
	 */
	protected array $svgs = [];

	/**
	 * The allowlist in effect for the fragment being processed.
	 *
	 * @var array<string, list<string>>
	 */
	protected array $allowedTags = [];

	public function __construct( protected SvgSanitizer $svgSanitizer )
	{
	}

	/**
	 * Whether a string holds anything a browser would read as markup. A
	 * `<` is only markup before a letter, `/`, `!` or `?`, so `a < b`
	 * doesn't count and is never rewritten.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $value  The string to check.
	 */
	public static function hasMarkup( string $value ): bool
	{
		return 1 === preg_match( '/<[a-zA-Z\/!?]/', $value );
	}

	/**
	 * Sanitize an HTML fragment. A string with no markup is returned as it
	 * is.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $html  The fragment.
	 *
	 * @return string Canonical markup holding only what's allowed.
	 */
	public function sanitize( string $html ): string
	{
		if ( ! self::hasMarkup( $html ) ) {
			return $html;
		}

		$this->svgs        = [];
		$this->allowedTags = $this->allowedTags();

		$body = $this->parse( $this->extractSvgs( $html ) );

		return null === $body ? '' : $this->serialize( $body );
	}

	/**
	 * The allowlist after the host's filter.
	 *
	 * @since 1.20.0
	 *
	 * @return array<string, list<string>>
	 */
	protected function allowedTags(): array
	{
		if ( ! function_exists( 'applyFilters' ) ) {
			return self::ALLOWED_TAGS;
		}

		/**
		 * Filters the tags and attributes unprivileged authors may save.
		 *
		 * @since 1.20.0
		 *
		 * @param array<string, list<string>> $tags Tag names, each with the
		 *                                          attributes it adds to the
		 *                                          global list.
		 */
		$tags = applyFilters( 'ap.visualEditor.contentFilter.allowedHtml', self::ALLOWED_TAGS );

		// A tag mapped to anything but an attribute list would fail every
		// save, so it's dropped instead.
		return is_array( $tags ) ? array_filter( $tags, 'is_array' ) : self::ALLOWED_TAGS;
	}

	/**
	 * Swap each `<svg>` for a placeholder. The sanitizer needs the original
	 * text, because libxml's HTML parser lower-cases names such as
	 * `viewBox`. This scans rather than using a regex, which gives up on a
	 * long string, and takes a self-closed `<svg/>` as whole, which is how
	 * the sanitizer writes an empty one. An `<svg>` this misses is dropped
	 * by the allowlist.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $html  The fragment.
	 *
	 * @return string The fragment with placeholders.
	 */
	protected function extractSvgs( string $html ): string
	{
		$output = '';
		$offset = 0;
		$scan   = [ 'position' => 0, 'inTag' => false, 'quote' => null, 'afterEquals' => false ];

		while ( 1 === preg_match( '/<svg\b/i', $html, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$start = $match[0][1];

			// Inside another tag's attribute, it's text: a placeholder there
			// would break the attribute's quoting.
			if ( $this->isInsideTag( $html, $start, $scan ) ) {
				$output .= substr( $html, $offset, $start + 4 - $offset );
				$offset  = $start + 4;

				continue;
			}

			$tagEnd = strpos( $html, '>', $start );

			if ( false === $tagEnd ) {
				break;
			}

			$output .= substr( $html, $offset, $start - $offset );
			$end     = $this->svgEnd( $html, $tagEnd );

			if ( null === $end ) {
				// Left in, it would swallow the rest of the fragment.
				$offset = $tagEnd + 1;

				continue;
			}

			$output .= $this->svgPlaceholder( substr( $html, $start, $end - $start ) );
			$offset  = $end;
		}

		return $output . substr( $html, $offset );
	}

	/**
	 * Whether a position sits inside an open tag, such as in an attribute
	 * value. Reads the fragment forward from where `$scan` stopped, tracking
	 * tags (a `<` before a letter or `/`) and quoted attribute values (a
	 * quote right after `=`), so a `<` or `>` inside a quoted value is text.
	 * Positions must not decrease between calls that share `$scan`.
	 *
	 * @since 1.20.0
	 *
	 * @param  string                                                                         $html      The fragment.
	 * @param  int                                                                            $position  The offset to check.
	 * @param  array{position: int, inTag: bool, quote: string|null, afterEquals: bool}  $scan      Scanner state, carried between calls.
	 */
	protected function isInsideTag( string $html, int $position, array &$scan ): bool
	{
		for ( $index = $scan['position']; $index < $position; $index++ ) {
			$char = $html[ $index ];

			if ( null !== $scan['quote'] ) {
				if ( $char === $scan['quote'] ) {
					$scan['quote'] = null;
				}

				continue;
			}

			if ( ! $scan['inTag'] ) {
				$next = $html[ $index + 1 ] ?? '';

				if ( '<' === $char && ( '/' === $next || ctype_alpha( $next ) ) ) {
					$scan['inTag']       = true;
					$scan['afterEquals'] = false;
				}

				continue;
			}

			if ( '>' === $char ) {
				$scan['inTag'] = false;
			} elseif ( $scan['afterEquals'] && ( '"' === $char || "'" === $char ) ) {
				$scan['quote']       = $char;
				$scan['afterEquals'] = false;
			} elseif ( '=' === $char ) {
				$scan['afterEquals'] = true;
			} elseif ( ! ctype_space( $char ) ) {
				$scan['afterEquals'] = false;
			}
		}

		$scan['position'] = max( $scan['position'], $position );

		return $scan['inTag'];
	}

	/**
	 * The offset just past the `<svg>` whose opening tag ends at `$tagEnd`.
	 * Another `<svg` before its closing tag means this one was never closed,
	 * so it can't claim that element's.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $html    The fragment.
	 * @param  int     $tagEnd  Where its opening tag ends.
	 *
	 * @return int|null The offset, or null when it's never closed.
	 */
	protected function svgEnd( string $html, int $tagEnd ): ?int
	{
		if ( '/' === $html[ $tagEnd - 1 ] ) {
			return $tagEnd + 1;
		}

		$close = stripos( $html, '</svg', $tagEnd );
		$end   = false === $close ? false : strpos( $html, '>', $close );

		if ( false === $end ) {
			return null;
		}

		$between = substr( $html, $tagEnd, $close - $tagEnd );

		return 1 === preg_match( '/<svg\b/i', $between ) ? null : $end + 1;
	}

	/**
	 * Sanitize one `<svg>`, and return the placeholder standing in for it.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $svg  The element.
	 *
	 * @return string The placeholder, or '' when nothing is left of it.
	 */
	protected function svgPlaceholder( string $svg ): string
	{
		$clean = trim( $this->svgSanitizer->sanitize( $svg )->sanitized );

		// Nothing HTML parses differently from XML may come back.
		if ( 1 !== preg_match( '/^<svg\b/i', $clean ) || 1 === preg_match( '/<[!?]/', $clean ) ) {
			return '';
		}

		$this->svgs[] = $clean;

		return sprintf( '<%1$s data-index="%2$d"></%1$s>', self::SVG_PLACEHOLDER, count( $this->svgs ) - 1 );
	}

	/**
	 * Parse a fragment into a document's body.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $html  The fragment.
	 *
	 * @return DOMNode|null The body, or null when nothing could be parsed.
	 */
	protected function parse( string $html ): ?DOMNode
	{
		$clean = mb_scrub( str_replace( "\0", '', $html ), 'UTF-8' );

		if ( '' === $clean ) {
			return null;
		}

		$document = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );

		$document->loadHTML(
			'<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $clean . '</body></html>',
			LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE,
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $document->getElementsByTagName( 'body' )->item( 0 );
	}

	/**
	 * Write out a node's children, keeping only what's allowed.
	 *
	 * @since 1.20.0
	 *
	 * @param  DOMNode  $parent  The node.
	 */
	protected function serialize( DOMNode $parent ): string
	{
		$output = '';

		foreach ( $parent->childNodes as $node ) {
			if ( $node instanceof DOMElement ) {
				$output .= $this->serializeElement( $node );
			} elseif ( XML_TEXT_NODE === $node->nodeType || XML_CDATA_SECTION_NODE === $node->nodeType ) {
				$output .= htmlspecialchars( (string) $node->nodeValue, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			}
		}

		return $output;
	}

	/**
	 * Write out one element.
	 *
	 * @since 1.20.0
	 *
	 * @param  DOMElement  $element  The element.
	 */
	protected function serializeElement( DOMElement $element ): string
	{
		$tag = strtolower( $element->nodeName );

		if ( self::SVG_PLACEHOLDER === $tag ) {
			return $this->svgs[ (int) $element->getAttribute( 'data-index' ) ] ?? '';
		}

		if ( ! isset( $this->allowedTags[ $tag ] ) ) {
			return in_array( $tag, self::REMOVED_TAGS, true ) ? '' : $this->serialize( $element );
		}

		$open = '<' . $tag . $this->serializeAttributes( $element, $tag ) . '>';

		if ( in_array( $tag, [ 'br', 'col', 'hr', 'img', 'source', 'track', 'wbr' ], true ) ) {
			return $open;
		}

		return $open . $this->serialize( $element ) . '</' . $tag . '>';
	}

	/**
	 * Write out an element's allowed attributes.
	 *
	 * @since 1.20.0
	 *
	 * @param  DOMElement  $element  The element.
	 * @param  string      $tag      Its lower-cased name.
	 */
	protected function serializeAttributes( DOMElement $element, string $tag ): string
	{
		$output = '';

		foreach ( $element->attributes as $attribute ) {
			$name = strtolower( $attribute->nodeName );

			if ( ! $this->isAllowedAttribute( $name, $tag ) ) {
				continue;
			}

			$value = $this->attributeValue( $name, (string) $attribute->nodeValue, $tag );

			if ( null !== $value ) {
				$output .= sprintf( ' %s="%s"', $name, htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );
			}
		}

		return $output;
	}

	/**
	 * Whether an attribute name is allowed on a tag.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $name  The lower-cased attribute name.
	 * @param  string  $tag   The lower-cased tag name.
	 */
	protected function isAllowedAttribute( string $name, string $tag ): bool
	{
		return in_array( $name, self::GLOBAL_ATTRIBUTES, true )
			|| in_array( $name, $this->allowedTags[ $tag ], true )
			|| 1 === preg_match( '/^aria-[a-z]+$/', $name );
	}

	/**
	 * The value to keep for an allowed attribute.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $name   The lower-cased attribute name.
	 * @param  string  $value  Its value.
	 * @param  string  $tag    The lower-cased tag name.
	 *
	 * @return string|null The value, or null to drop the attribute.
	 */
	protected function attributeValue( string $name, string $value, string $tag ): ?string
	{
		if ( 'style' === $name ) {
			$style = $this->filterStyle( $value );

			return '' === $style ? null : $style;
		}

		if ( 'srcset' === $name ) {
			return $this->filterSrcset( $value );
		}

		if ( in_array( $name, self::URL_ATTRIBUTES, true ) ) {
			return $this->filterUrl( $value, 'img' === $tag && 'src' === $name );
		}

		if ( 'target' === $name ) {
			return in_array( strtolower( $value ), [ '_blank', '_parent', '_self', '_top' ], true ) ? $value : null;
		}

		return $value;
	}

	/**
	 * A URL with a safe scheme, or null.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $url              The URL.
	 * @param  bool    $allowInlineImage Whether a base64 raster image is fine here.
	 */
	protected function filterUrl( string $url, bool $allowInlineImage = false ): ?string
	{
		if ( $allowInlineImage && 1 === preg_match( self::INLINE_IMAGE, trim( $url ) ) ) {
			return trim( $url );
		}

		$safe = UrlSanitizer::safe( $url );

		return '' === $safe ? null : $safe;
	}

	/**
	 * A `srcset` value, or null when any candidate's URL isn't safe.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $srcset  The attribute value.
	 */
	protected function filterSrcset( string $srcset ): ?string
	{
		foreach ( explode( ',', $srcset ) as $candidate ) {
			$url = preg_split( '/\s+/', trim( $candidate ) )[0] ?? '';

			if ( '' !== $url && null === $this->filterUrl( $url ) ) {
				return null;
			}
		}

		return $srcset;
	}

	/**
	 * Keep the safe declarations of a `style` attribute.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $style  The attribute value.
	 */
	protected function filterStyle( string $style ): string
	{
		$kept = [];

		foreach ( explode( ';', $style ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );

			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$property = trim( $parts[0] );
			$value    = trim( $parts[1] );

			if ( '' !== $value && 1 === preg_match( self::SAFE_STYLE_PROPERTY, strtolower( $property ) ) && 1 !== preg_match( self::UNSAFE_STYLE_VALUE, $value ) ) {
				// Custom properties are case-sensitive.
				$kept[] = ( str_starts_with( $property, '--' ) ? $property : strtolower( $property ) ) . ':' . $value;
			}
		}

		return implode( ';', $kept );
	}
}
