<?php

/**
 * Server-rendered `artisanpack/icon` block.
 *
 * Phase 1 (#552) ships the markup pipeline: attribute validation, inline
 * style/transform computation, link wrapping, a11y attribute emission,
 * and custom-SVG sanitization via {@see SvgSanitizer}. The icon-registry
 * resolution path (turning an `iconRef` into inline SVG markup) is wired
 * in Phase 3 (#554) when the FA 6 Free SVGs ship.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Icon;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;

class IconBlock extends DynamicBlock
{
	private const ALLOWED_SIZE_UNITS    = [ 'px', 'em', 'rem' ];
	private const ALLOWED_ROTATIONS     = [ 0, 90, 180, 270 ];
	private const ALLOWED_LINK_TARGETS  = [ '_blank', '_self', '_parent', '_top' ];

	public function __construct( private readonly SvgSanitizer $sanitizer )
	{
	}

	public function name(): string
	{
		return 'artisanpack/icon';
	}

	public function validateAttrs( array $attrs ): array
	{
		$size = isset( $attrs['size'] ) && is_numeric( $attrs['size'] )
			? (float) $attrs['size']
			: 32.0;
		$size = max( 1.0, min( 1024.0, $size ) );

		$sizeUnit = isset( $attrs['sizeUnit'] ) && in_array( $attrs['sizeUnit'], self::ALLOWED_SIZE_UNITS, true )
			? (string) $attrs['sizeUnit']
			: 'px';

		$rotation = isset( $attrs['rotation'] ) && in_array( (int) $attrs['rotation'], self::ALLOWED_ROTATIONS, true )
			? (int) $attrs['rotation']
			: 0;

		$linkTarget = isset( $attrs['linkTarget'] ) && in_array( $attrs['linkTarget'], self::ALLOWED_LINK_TARGETS, true )
			? (string) $attrs['linkTarget']
			: '';

		return [
			'iconRef'         => $this->normalizeIconRef( $attrs['iconRef'] ?? null ),
			'customSvg'       => is_string( $attrs['customSvg'] ?? null ) ? $attrs['customSvg'] : '',
			'size'            => $size,
			'sizeUnit'        => $sizeUnit,
			'color'           => $this->normalizeColor( $attrs['color'] ?? null ),
			'backgroundColor' => $this->normalizeColor( $attrs['backgroundColor'] ?? null ),
			'rotation'        => $rotation,
			'flipH'           => (bool) ( $attrs['flipH'] ?? false ),
			'flipV'           => (bool) ( $attrs['flipV'] ?? false ),
			'link'            => $this->normalizeLink( $attrs['link'] ?? null ),
			'linkTarget'      => $linkTarget,
			'linkRel'         => is_string( $attrs['linkRel'] ?? null ) ? (string) $attrs['linkRel'] : '',
			'titleAttr'       => is_string( $attrs['titleAttr'] ?? null ) ? (string) $attrs['titleAttr'] : '',
			'ariaLabel'       => is_string( $attrs['ariaLabel'] ?? null ) ? (string) $attrs['ariaLabel'] : '',
			'isDecorative'    => (bool) ( $attrs['isDecorative'] ?? false ),
			'className'       => is_string( $attrs['className'] ?? null ) ? (string) $attrs['className'] : '',
		];
	}

	public function render( array $attrs ): string
	{
		$attrs = $this->validateAttrs( $attrs );

		$body = $this->renderBody( $attrs );

		if ( $this->shouldRenderLink( $attrs ) ) {
			$body = $this->wrapInLink( $body, $attrs );
		}

		$wrapperClasses = $this->wrapperClasses( $attrs );

		// The wrapper is a plain block element so the editor's
		// `is-layout-constrained` parent keeps the icon inside the
		// content column. The inline-flex sizing lives on the body
		// span — see `innerStyle()`.
		return sprintf(
			'<div class="%s">%s</div>',
			e( implode( ' ', $wrapperClasses ) ),
			$body
		);
	}

	public function searchableText( array $attrs ): string
	{
		$attrs = $this->validateAttrs( $attrs );

		// Surface the human label (aria-label / title) to search — the icon
		// slug itself is technical noise, but the label is what authors
		// type when they're hunting for "github" or "Twitter Profile".
		return trim( $attrs['ariaLabel'] . ' ' . $attrs['titleAttr'] );
	}

	private function renderBody( array $attrs ): string
	{
		$bodyStyle = $this->bodyStyle( $attrs );
		$ariaAttrs = $this->ariaAttributes( $attrs );

		if ( '' !== trim( $attrs['customSvg'] ) ) {
			$result = $this->sanitizer->sanitize( $attrs['customSvg'] );

			return sprintf(
				'<span class="wp-block-artisanpack-icon__svg" style="%s"%s>%s</span>',
				e( $bodyStyle ),
				$ariaAttrs,
				$result->sanitized
			);
		}

		if ( null !== $attrs['iconRef'] ) {
			// Phase 3 (#554) swaps this `data-*` carrier for the resolved
			// inline `<svg>` once the FA 6 Free SVGs are bundled and the
			// icons registry is consulted at render time.
			return sprintf(
				'<span class="wp-block-artisanpack-icon__ref" data-icon-set="%s" data-icon-name="%s" style="%s"%s></span>',
				e( $attrs['iconRef']['set'] ),
				e( $attrs['iconRef']['name'] ),
				e( $bodyStyle ),
				$ariaAttrs
			);
		}

		return sprintf(
			'<span class="wp-block-artisanpack-icon__placeholder" style="%s" aria-hidden="true"></span>',
			e( $bodyStyle )
		);
	}

	private function wrapInLink( string $body, array $attrs ): string
	{
		$rel = $this->composeRel( $attrs['linkTarget'], $attrs['linkRel'] );

		$attrParts = [ sprintf( 'href="%s"', e( $attrs['link'] ) ) ];
		if ( '' !== $attrs['linkTarget'] ) {
			$attrParts[] = sprintf( 'target="%s"', e( $attrs['linkTarget'] ) );
		}
		if ( '' !== $rel ) {
			$attrParts[] = sprintf( 'rel="%s"', e( $rel ) );
		}

		return sprintf( '<a %s>%s</a>', implode( ' ', $attrParts ), $body );
	}

	private function shouldRenderLink( array $attrs ): bool
	{
		return '' !== $attrs['link']
			&& ( null !== $attrs['iconRef'] || '' !== trim( $attrs['customSvg'] ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-artisanpack-icon' ];
		if ( '' !== $attrs['className'] ) {
			$classes[] = $attrs['className'];
		}

		return $classes;
	}

	/**
	 * Inline-flex sized style for the body span.
	 *
	 * Sized on the BODY (the span carrying the icon) rather than the
	 * outer wrapper `<div>`, so the wrapper stays a plain block-flow
	 * element and the editor's layout-constrained parent keeps the
	 * icon inside the content column.
	 */
	private function bodyStyle( array $attrs ): string
	{
		$dimension = sprintf( '%s%s', $this->formatNumber( $attrs['size'] ), $attrs['sizeUnit'] );
		$parts     = [
			'width: ' . $dimension,
			'height: ' . $dimension,
			'display: inline-flex',
			'align-items: center',
			'justify-content: center',
			'line-height: 0',
		];

		if ( null !== $attrs['color'] ) {
			$parts[] = 'color: ' . $attrs['color'];
		}
		if ( null !== $attrs['backgroundColor'] ) {
			$parts[] = 'background-color: ' . $attrs['backgroundColor'];
		}

		$transform = $this->computeTransform( $attrs );
		if ( '' !== $transform ) {
			$parts[] = 'transform: ' . $transform;
			$parts[] = 'transform-origin: center';
		}

		return implode( '; ', $parts ) . ';';
	}

	private function computeTransform( array $attrs ): string
	{
		$parts = [];
		if ( 0 !== $attrs['rotation'] ) {
			$parts[] = sprintf( 'rotate(%ddeg)', $attrs['rotation'] );
		}
		if ( $attrs['flipH'] ) {
			$parts[] = 'scaleX(-1)';
		}
		if ( $attrs['flipV'] ) {
			$parts[] = 'scaleY(-1)';
		}

		return implode( ' ', $parts );
	}

	private function ariaAttributes( array $attrs ): string
	{
		$attrParts = [];
		if ( $attrs['isDecorative'] ) {
			$attrParts[] = ' aria-hidden="true"';
		} elseif ( '' !== $attrs['ariaLabel'] ) {
			$attrParts[] = sprintf( ' aria-label="%s"', e( $attrs['ariaLabel'] ) );
		}
		if ( '' !== $attrs['titleAttr'] ) {
			$attrParts[] = sprintf( ' title="%s"', e( $attrs['titleAttr'] ) );
		}

		return implode( '', $attrParts );
	}

	private function composeRel( string $linkTarget, string $rel ): string
	{
		$tokens = array_filter(
			array_map( 'trim', preg_split( '/\s+/', $rel ) ?: [] ),
			static fn ( string $token ): bool => '' !== $token,
		);

		if ( '_blank' === $linkTarget ) {
			$tokens[] = 'noopener';
			$tokens[] = 'noreferrer';
		}

		return implode( ' ', array_values( array_unique( $tokens ) ) );
	}

	/**
	 * @return array{set: string, name: string}|null
	 */
	private function normalizeIconRef( mixed $value ): ?array
	{
		if ( ! is_array( $value ) ) {
			return null;
		}

		$set  = is_string( $value['set'] ?? null ) ? trim( $value['set'] ) : '';
		$name = is_string( $value['name'] ?? null ) ? trim( $value['name'] ) : '';

		if ( '' === $set || '' === $name ) {
			return null;
		}

		// Tighten to the same character set the icons-registry uses for
		// folder names — anything else would either fail to resolve or
		// open a path-traversal vector at the registry layer.
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $set ) ) {
			return null;
		}
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_.-]*$/i', $name ) ) {
			return null;
		}

		return [ 'set' => $set, 'name' => $name ];
	}

	/**
	 * Restrict link URLs to safe schemes.
	 *
	 * Authors paste anything into the link field; without a scheme check
	 * `javascript:` and `data:` URIs would reach the rendered `<a href>`.
	 * We accept relative paths (so internal links keep working), the
	 * `#anchor` form, and `http/https/mailto/tel`. Everything else is
	 * coerced to the empty string, which makes `shouldRenderLink()`
	 * suppress the `<a>` wrapper entirely.
	 */
	private function normalizeLink( mixed $value ): string
	{
		if ( ! is_string( $value ) ) {
			return '';
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}

		// Fast-path the unambiguously safe relative + hash forms.
		if ( '/' === $trimmed[0] || '#' === $trimmed[0] || '?' === $trimmed[0] ) {
			return $trimmed;
		}

		// Scheme allowlist. Anything with a colon that isn't on the list
		// (e.g. `javascript:`, `data:`, `vbscript:`) is rejected. A bare
		// `example.com/path` has no colon, so it falls through to allow.
		$colonAt = strpos( $trimmed, ':' );
		if ( false === $colonAt ) {
			return $trimmed;
		}

		$scheme = strtolower( substr( $trimmed, 0, $colonAt ) );
		if ( ! in_array( $scheme, [ 'http', 'https', 'mailto', 'tel' ], true ) ) {
			return '';
		}

		return $trimmed;
	}

	private function normalizeColor( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return null;
		}

		// Conservative allowlist: hex, rgb(a), hsl(a), CSS variable, named.
		// The wrapper style is interpolated unquoted into the markup, so
		// rejecting anything weird here is cheap insurance against an
		// attacker reaching the wrapper via a future attribute path.
		if ( ! preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|var\(--[a-z0-9_-]+\)|[a-z]+)$/i', $trimmed ) ) {
			return null;
		}

		return $trimmed;
	}

	private function formatNumber( float $value ): string
	{
		// Render integers without a trailing `.0` so the wrapper style stays
		// stable across edits — `32px` instead of `32.000000px`.
		if ( floor( $value ) === $value ) {
			return (string) (int) $value;
		}

		return rtrim( rtrim( sprintf( '%.4f', $value ), '0' ), '.' );
	}
}
