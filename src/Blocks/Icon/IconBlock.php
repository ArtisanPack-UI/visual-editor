<?php

/**
 * Server-rendered `artisanpack/icon` block.
 *
 * Phase 1 (#552) ships the markup pipeline: attribute validation, inline
 * style/transform computation, link wrapping, a11y attribute emission,
 * and custom-SVG sanitization via {@see SvgSanitizer}. The icon-registry
 * resolution path (turning an `iconRef` into inline SVG markup) is wired
 * in Phase 3 (#554) when the FA Free SVGs ship.
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
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;

class IconBlock extends DynamicBlock
{
	private const ALLOWED_SIZE_UNITS    = [ 'px', 'em', 'rem', '%', 'vw', 'vh' ];
	private const ALLOWED_ROTATIONS     = [ 0, 90, 180, 270 ];
	private const ALLOWED_LINK_TARGETS  = [ '_blank', '_self', '_parent', '_top' ];
	private const ALLOWED_BORDER_STYLES = [ 'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset' ];

	public function __construct(
		private readonly SvgSanitizer $sanitizer,
		private readonly ?IconSvgResolver $resolver = null,
	) {
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

		$width       = $this->normalizeDimension( $attrs['width'] ?? null );
		$widthUnit   = null === $width
			? null
			: ( $this->normalizeSizeUnit( $attrs['widthUnit'] ?? null ) ?? $sizeUnit );
		$height      = $this->normalizeDimension( $attrs['height'] ?? null );
		$heightUnit  = null === $height
			? null
			: ( $this->normalizeSizeUnit( $attrs['heightUnit'] ?? null ) ?? $sizeUnit );

		$rotation = isset( $attrs['rotation'] ) && in_array( (int) $attrs['rotation'], self::ALLOWED_ROTATIONS, true )
			? (int) $attrs['rotation']
			: 0;

		$linkTarget = isset( $attrs['linkTarget'] ) && in_array( $attrs['linkTarget'], self::ALLOWED_LINK_TARGETS, true )
			? (string) $attrs['linkTarget']
			: '';

		return [
			'iconRef'             => $this->normalizeIconRef( $attrs['iconRef'] ?? null ),
			'customSvg'           => is_string( $attrs['customSvg'] ?? null ) ? $attrs['customSvg'] : '',
			'size'                => $size,
			'sizeUnit'            => $sizeUnit,
			'width'               => $width,
			'widthUnit'           => $widthUnit,
			'height'              => $height,
			'heightUnit'          => $heightUnit,
			// Legacy top-level slots kept around so blocks saved before
			// the iconColor + WP-style-envelope work still render their
			// picked colors.
			'color'               => $this->normalizeColor( $attrs['color'] ?? null ),
			'backgroundColor'     => $this->normalizeColor( $attrs['backgroundColor'] ?? null ),
			'iconColor'           => $this->normalizeColor( $attrs['iconColor'] ?? null ),
			// Palette-color slugs from WP `supports.color` /
			// `supports.__experimentalBorder` — only the palette
			// `backgroundColor`/`borderColor` slugs reach top-level
			// attrs; custom hex values land inside `style`.
			'paletteBackground'   => $this->normalizePaletteSlug( $attrs['backgroundColor'] ?? null ),
			'paletteBorderColor'  => $this->normalizePaletteSlug( $attrs['borderColor'] ?? null ),
			'rotation'            => $rotation,
			'flipH'               => (bool) ( $attrs['flipH'] ?? false ),
			'flipV'               => (bool) ( $attrs['flipV'] ?? false ),
			'link'                => $this->normalizeLink( $attrs['link'] ?? null ),
			'linkTarget'          => $linkTarget,
			'linkRel'             => is_string( $attrs['linkRel'] ?? null ) ? (string) $attrs['linkRel'] : '',
			'titleAttr'           => is_string( $attrs['titleAttr'] ?? null ) ? (string) $attrs['titleAttr'] : '',
			'ariaLabel'           => is_string( $attrs['ariaLabel'] ?? null ) ? (string) $attrs['ariaLabel'] : '',
			'isDecorative'        => (bool) ( $attrs['isDecorative'] ?? false ),
			'className'           => is_string( $attrs['className'] ?? null ) ? (string) $attrs['className'] : '',
			'style'               => is_array( $attrs['style'] ?? null ) ? $attrs['style'] : [],
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
		$wrapperStyle   = $this->wrapperStyle( $attrs );

		// The wrapper is a plain block element so the editor's
		// `is-layout-constrained` parent keeps the icon inside the
		// content column. The inline-flex sizing lives on the body
		// span — see `bodyStyle()`. Margin is the one style we apply
		// here because `display: inline-flex` on the body span
		// (correctly) participates in inline-layout vertical metrics,
		// whereas margin is a block-level concern.
		return sprintf(
			'<div class="%s"%s>%s</div>',
			e( implode( ' ', $wrapperClasses ) ),
			'' !== $wrapperStyle ? sprintf( ' style="%s"', e( $wrapperStyle ) ) : '',
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
			$resolved = null !== $this->resolver
				? $this->resolver->resolve( $attrs['iconRef']['set'], $attrs['iconRef']['name'] )
				: null;

			if ( null !== $resolved ) {
				return sprintf(
					'<span class="wp-block-artisanpack-icon__ref" data-icon-set="%s" data-icon-name="%s" style="%s"%s>%s</span>',
					e( $attrs['iconRef']['set'] ),
					e( $attrs['iconRef']['name'] ),
					e( $bodyStyle ),
					$ariaAttrs,
					$this->prepareInlineSvg( $resolved )
				);
			}

			// Resolver miss — sync hasn't run, set not registered, or icon
			// removed upstream. Falling back to the placeholder keeps the
			// layout stable rather than leaving an empty `data-*` carrier
			// the front end has no way to fill.
			return sprintf(
				'<span class="wp-block-artisanpack-icon__placeholder" data-icon-set="%s" data-icon-name="%s" style="%s" aria-hidden="true"></span>',
				e( $attrs['iconRef']['set'] ),
				e( $attrs['iconRef']['name'] ),
				e( $bodyStyle )
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

		$style = is_array( $attrs['style'] ?? null ) ? $attrs['style'] : [];

		// WP `supports.color` palette selections (slugs) need their
		// `has-{slug}-background-color` + `has-background` class pair to
		// pick up the theme.json CSS variable. Custom hex values arrive
		// inside `style.color.background` and are applied as inline CSS
		// in `wrapperStyle()` instead.
		if ( '' !== $attrs['paletteBackground'] ) {
			$classes[] = sprintf( 'has-%s-background-color', $attrs['paletteBackground'] );
			$classes[] = 'has-background';
		} elseif ( null !== $this->normalizeColor( $style['color']['background'] ?? null ) ) {
			$classes[] = 'has-background';
		}

		// `supports.__experimentalBorder` mirrors the same palette/custom
		// split for border color.
		if ( '' !== $attrs['paletteBorderColor'] ) {
			$classes[] = sprintf( 'has-%s-border-color', $attrs['paletteBorderColor'] );
			$classes[] = 'has-border-color';
		} elseif ( null !== $this->normalizeColor( $style['border']['color'] ?? null ) ) {
			$classes[] = 'has-border-color';
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
	 *
	 * The body span also carries the icon's `color` — which the bundled
	 * SVGs pick up via `fill: currentcolor` (declared on
	 * `.wp-block-artisanpack-icon svg` in `icon.css`). Precedence:
	 * explicit `iconColor` wins, then the WP `style.color.text` slot
	 * (kept for blocks that still carry it), then the legacy top-level
	 * `color` attribute.
	 */
	private function bodyStyle( array $attrs ): string
	{
		$width  = sprintf( '%s%s', $this->formatNumber( $attrs['width']  ?? $attrs['size'] ), $attrs['widthUnit']  ?? $attrs['sizeUnit'] );
		$height = sprintf( '%s%s', $this->formatNumber( $attrs['height'] ?? $attrs['size'] ), $attrs['heightUnit'] ?? $attrs['sizeUnit'] );

		$parts = [
			'width: ' . $width,
			'height: ' . $height,
			'display: inline-flex',
			'align-items: center',
			'justify-content: center',
			'line-height: 0',
		];

		$style  = is_array( $attrs['style'] ?? null ) ? $attrs['style'] : [];
		$wpText = $this->normalizeColor( $style['color']['text'] ?? null );
		$color  = $attrs['iconColor'] ?? $wpText ?? $attrs['color'];
		if ( null !== $color ) {
			$parts[] = 'color: ' . $color;
		}

		$transform = $this->computeTransform( $attrs );
		if ( '' !== $transform ) {
			$parts[] = 'transform: ' . $transform;
			$parts[] = 'transform-origin: center';
		}

		return implode( '; ', $parts ) . ';';
	}

	/**
	 * Wrapper `<div>` style.
	 *
	 * Carries the WP-managed background/border/padding/margin from the
	 * `attributes.style` envelope plus the legacy top-level
	 * `backgroundColor` for pre-fix blocks. The icon's foreground color
	 * stays on the body span via `bodyStyle()` so the SVG's
	 * `fill: currentcolor` picks it up without spilling onto the wrapper.
	 * Returns an empty string (not `style=""`) when nothing applies so
	 * the wrapper markup stays attribute-clean.
	 */
	private function wrapperStyle( array $attrs ): string
	{
		$style = is_array( $attrs['style'] ?? null ) ? $attrs['style'] : [];
		$parts = [];

		// Custom hex background (style.color.background); the palette
		// slug case is handled via `has-{slug}-background-color` in
		// `wrapperClasses()`. The legacy top-level `backgroundColor`
		// hex is the fallback for blocks saved before the WP-managed
		// envelope reached this block.
		$wpBackground = $this->normalizeColor( $style['color']['background'] ?? null );
		$background   = '' === $attrs['paletteBackground'] ? ( $wpBackground ?? $attrs['backgroundColor'] ) : null;
		if ( null !== $background ) {
			$parts[] = 'background-color: ' . $background;
		}

		foreach ( $this->borderDeclarations( $style['border'] ?? null ) as $decl ) {
			$parts[] = $decl;
		}
		foreach ( $this->boxDeclarations( $style['spacing']['padding'] ?? null, 'padding' ) as $decl ) {
			$parts[] = $decl;
		}
		foreach ( $this->boxDeclarations( $style['spacing']['margin'] ?? null, 'margin' ) as $decl ) {
			$parts[] = $decl;
		}

		if ( [] === $parts ) {
			return '';
		}

		return implode( '; ', $parts ) . ';';
	}

	/**
	 * Emit CSS declarations for a WP border envelope.
	 *
	 * Accepts uniform (`width`/`color`/`style`/`radius` at the top level)
	 * and per-side (`top`/`right`/`bottom`/`left` objects) forms. Values
	 * are passed through {@see normalizeColor} for colors and
	 * {@see normalizeDimensionString} for widths/radii, so anything that
	 * smuggles a `;` or quote into the inline style falls back to the
	 * empty string and is dropped.
	 *
	 * @param  mixed  $border
	 *
	 * @return \Generator<int, string>
	 */
	private function borderDeclarations( mixed $border ): \Generator
	{
		if ( ! is_array( $border ) ) {
			return;
		}

		$radius = $border['radius'] ?? null;
		if ( is_string( $radius ) ) {
			$safe = $this->normalizeDimensionString( $radius );
			if ( null !== $safe ) {
				yield 'border-radius: ' . $safe;
			}
		} elseif ( is_array( $radius ) ) {
			foreach ( [
				'topLeft'     => 'border-top-left-radius',
				'topRight'    => 'border-top-right-radius',
				'bottomRight' => 'border-bottom-right-radius',
				'bottomLeft'  => 'border-bottom-left-radius',
			] as $key => $cssName ) {
				$safe = $this->normalizeDimensionString( $radius[ $key ] ?? null );
				if ( null !== $safe ) {
					yield $cssName . ': ' . $safe;
				}
			}
		}

		foreach ( [ 'color' => 'border-color', 'style' => 'border-style', 'width' => 'border-width' ] as $key => $cssName ) {
			$value = $border[ $key ] ?? null;
			$safe  = 'color' === $key
				? $this->normalizeColor( $value )
				: ( 'style' === $key ? $this->normalizeBorderStyle( $value ) : $this->normalizeDimensionString( $value ) );
			if ( null !== $safe ) {
				yield $cssName . ': ' . $safe;
			}
		}

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$entry = $border[ $side ] ?? null;
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$sideColor = $this->normalizeColor( $entry['color'] ?? null );
			$sideStyle = $this->normalizeBorderStyle( $entry['style'] ?? null );
			$sideWidth = $this->normalizeDimensionString( $entry['width'] ?? null );
			if ( null !== $sideColor ) {
				yield sprintf( 'border-%s-color: %s', $side, $sideColor );
			}
			if ( null !== $sideStyle ) {
				yield sprintf( 'border-%s-style: %s', $side, $sideStyle );
			}
			if ( null !== $sideWidth ) {
				yield sprintf( 'border-%s-width: %s', $side, $sideWidth );
			}
		}
	}

	/**
	 * Emit CSS declarations for a WP box (padding|margin) envelope.
	 *
	 * @param  mixed   $value
	 * @param  string  $shorthand  `padding` or `margin`.
	 *
	 * @return \Generator<int, string>
	 */
	private function boxDeclarations( mixed $value, string $shorthand ): \Generator
	{
		if ( null === $value ) {
			return;
		}
		if ( is_string( $value ) ) {
			$safe = $this->normalizeDimensionString( $value );
			if ( null !== $safe ) {
				yield $shorthand . ': ' . $safe;
			}
			return;
		}
		if ( ! is_array( $value ) ) {
			return;
		}
		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$safe = $this->normalizeDimensionString( $value[ $side ] ?? null );
			if ( null !== $safe ) {
				yield sprintf( '%s-%s: %s', $shorthand, $side, $safe );
			}
		}
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

	/**
	 * Coerce a WP palette-color slug into a kebab-case safe form.
	 *
	 * WP writes the slug of a palette pick into the top-level
	 * `backgroundColor` / `borderColor` attributes (NOT inside `style`).
	 * The slug is interpolated into a `has-{slug}-background-color`
	 * class, so anything that isn't `[a-z0-9-]+` would break the
	 * markup. Returns the empty string for missing / malformed values
	 * so the caller can just check `'' !== $slug`.
	 *
	 * Also rejects values that look like hex/rgb/etc. — those reach
	 * `backgroundColor` only on already-saved blocks where the slot was
	 * being used as the legacy hex storage; the hex fallback path in
	 * `wrapperStyle()` keeps them honored without misclassing them.
	 */
	private function normalizePaletteSlug( mixed $value ): string
	{
		if ( ! is_string( $value ) ) {
			return '';
		}
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}
		// A WP palette slug is always lowercase kebab-case; the legacy
		// hex/CSS-value forms all start with `#`, contain `(`, etc.
		if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $trimmed ) ) {
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

	/**
	 * Coerce a raw width/height attribute into a clamped float.
	 *
	 * Returns `null` (not a default size) when the value is missing or
	 * non-numeric so the caller can distinguish "author cleared the
	 * override" from "author typed 0" — null means fall back to `size`,
	 * which is the correct behavior for an unset width/height.
	 */
	private function normalizeDimension( mixed $value ): ?float
	{
		if ( null === $value || ! is_numeric( $value ) ) {
			return null;
		}
		return max( 1.0, min( 1024.0, (float) $value ) );
	}

	private function normalizeSizeUnit( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}
		return in_array( $value, self::ALLOWED_SIZE_UNITS, true ) ? $value : null;
	}

	/**
	 * Allowlist a CSS dimension string (e.g. `48px`, `1.5em`, `var(--gap)`).
	 *
	 * Returns `null` for anything else so the value is dropped from the
	 * rendered style rather than reaching the markup. The grammar accepts
	 * the same units as {@see ALLOWED_SIZE_UNITS}, plus `vmin`/`vmax`
	 * (read-only — never written here) and the `var(--token)` form WP's
	 * theme.json uses for global-style references.
	 */
	private function normalizeDimensionString( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return null;
		}
		if ( preg_match( '/^var\(--[a-z0-9_-]+\)$/i', $trimmed ) ) {
			return $trimmed;
		}
		// Whitespace-separated list of `<number><unit>?` tokens to cover
		// `padding: 4px 8px 4px 8px` and the like.
		if ( ! preg_match( '/^(-?\d+(\.\d+)?(px|em|rem|%|vw|vh|vmin|vmax)?)(\s+-?\d+(\.\d+)?(px|em|rem|%|vw|vh|vmin|vmax)?)*$/i', $trimmed ) ) {
			return null;
		}
		return $trimmed;
	}

	private function normalizeBorderStyle( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}
		$trimmed = strtolower( trim( $value ) );
		return in_array( $trimmed, self::ALLOWED_BORDER_STYLES, true ) ? $trimmed : null;
	}

	/**
	 * Inline-ready SVG markup.
	 *
	 * FA Free SVGs ship without `width`/`height` attributes, which means
	 * an inline `<svg>` falls back to the spec's 300×150 default and
	 * overflows the sized wrapper span. Inject `width="100%"` /
	 * `height="100%"` on the root `<svg>` so the SVG fills the wrapper's
	 * declared box. Strip any existing `width`/`height` first so an icon
	 * set with intrinsic dimensions (e.g. an admin-uploaded set in Phase 6)
	 * doesn't fight the wrapper.
	 *
	 * The bundled FA Free SVGs are trusted (pinned npm dep, npm-supply-
	 * chain risk only) — we do NOT route them through {@see SvgSanitizer}
	 * because doing so on every render would burn DOMDocument cost for no
	 * security gain. Admin-uploaded sets in Phase 6 (#557) will sanitize
	 * at upload time, not at render time.
	 */
	private function prepareInlineSvg( string $svg ): string
	{
		// Single-pass rewrite of the opening `<svg …>` tag: strip every
		// existing `width`/`height` attribute, then prepend the wrapper-
		// fill pair. Doing this in two preg_replace calls leaks duplicate
		// attributes when both are present, because `preg_replace` won't
		// match a second `width|height` inside the same `<svg …>` opener
		// once its lazy-match starting cursor has advanced past it.
		return preg_replace_callback(
			'/<svg\b([^>]*)>/i',
			static function ( array $match ): string {
				// Match any value quoting style — `width="24"`, `width='24'`,
				// or unquoted `width=24` — so an admin-uploaded set with an
				// HTML-lenient SVG can't smuggle a duplicate sizing attr
				// past the rewrite.
				$attrs = preg_replace(
					'/\s+(width|height)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
					'',
					$match[1],
				) ?? $match[1];

				return '<svg width="100%" height="100%"' . $attrs . '>';
			},
			$svg,
			1,
		) ?? $svg;
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
