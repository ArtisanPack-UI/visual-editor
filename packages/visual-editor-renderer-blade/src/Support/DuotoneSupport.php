<?php

/**
 * Duotone filter support (#873).
 *
 * Front-end port of WordPress core's duotone block support
 * (`WP_Duotone`). A block that declares `supports.filter.duotone`
 * stores its pick under `style.color.duotone` in one of three shapes:
 *
 * - a custom color pair (or longer stop list): `["#000000", "#ffffff"]`;
 * - a theme preset reference: `var:preset|duotone|{slug}` (or the
 *   expanded `var(--wp--preset--duotone--{slug})` form), resolved
 *   against the active theme's `settings.color.duotone` presets;
 * - the literal `unset`, which turns an inherited filter off.
 *
 * Each pick compiles to a scope class for the block wrapper, a CSS rule
 * that points the block's media element at the filter
 * (`.wp-duotone-x img { filter: url(#wp-duotone-x) }`), and the SVG
 * `<filter>` element itself. {@see apply()} pushes the rule and filter
 * into the per-request {@see DuotoneCssAccumulator} so a filter shared
 * by several blocks is emitted once.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Services\DuotoneCssAccumulator;
use Throwable;

class DuotoneSupport
{
	/**
	 * The scope class / filter id prefix WordPress core uses.
	 */
	public const PREFIX = 'wp-duotone-';

	/**
	 * Compile a block's duotone pick and register it with the
	 * per-request accumulator. Returns the scope class the caller adds
	 * to the block wrapper, or `''` when the block has no usable
	 * duotone.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes      Raw block attributes.
	 * @param  string                $targetSelector  Descendant the filter
	 *                                                applies to (the
	 *                                                block.json
	 *                                                `selectors.filter.duotone`
	 *                                                target).
	 */
	public static function apply( array $attributes, string $targetSelector = 'img' ): string
	{
		$compiled = self::compile( $attributes, $targetSelector );

		if ( '' === $compiled['class'] ) {
			return '';
		}

		if ( function_exists( 'app' ) ) {
			try {
				// Keyed by class + target so one filter shared by blocks
				// with different media selectors keeps each rule.
				app( DuotoneCssAccumulator::class )->push(
					$compiled['class'] . ' ' . $targetSelector,
					$compiled['rules'],
					$compiled['filterId'],
					$compiled['filter'],
				);
			} catch ( Throwable ) {
				// Container not booted (isolated unit tests) — the
				// accumulator is a side channel, not the data path.
			}
		}

		return $compiled['class'];
	}

	/**
	 * Compile a block's duotone pick without side effects.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{class: string, rules: string, filterId: string, filter: string}
	 *         All four are `''` when there is nothing to emit. `filterId` /
	 *         `filter` are `''` for `unset`, which needs no SVG.
	 */
	public static function compile( array $attributes, string $targetSelector = 'img' ): array
	{
		$empty = [ 'class' => '', 'rules' => '', 'filterId' => '', 'filter' => '' ];
		$value = $attributes['style']['color']['duotone'] ?? null;

		if ( 'unset' === $value ) {
			$class = self::PREFIX . 'unset';

			return [
				'class'    => $class,
				'rules'    => sprintf( '.%s %s{filter:none !important}', $class, $targetSelector ),
				'filterId' => '',
				'filter'   => '',
			];
		}

		[ $suffix, $colors ] = self::resolve( $value );

		if ( null === $suffix ) {
			return $empty;
		}

		$stops = self::parseColors( $colors );

		if ( count( $stops ) < 2 ) {
			return $empty;
		}

		$id = self::PREFIX . $suffix;

		return [
			'class'    => $id,
			'rules'    => sprintf( '.%1$s %2$s{filter:url(#%1$s) !important}', $id, $targetSelector ),
			'filterId' => $id,
			'filter'   => self::filterSvg( $id, $stops ),
		];
	}

	/**
	 * Resolve a stored duotone value into its id suffix and its color
	 * stops: the preset slug for a preset reference, a content hash for a
	 * custom stop list.
	 *
	 * @since 1.15.0
	 *
	 * @return array{0: ?string, 1: array<int, mixed>}
	 */
	protected static function resolve( mixed $value ): array
	{
		if ( is_array( $value ) ) {
			$colors = array_values( $value );

			return [ substr( hash( 'xxh3', (string) json_encode( $colors ) ), 0, 10 ), $colors ];
		}

		if ( ! is_string( $value ) ) {
			return [ null, [] ];
		}

		$slug = null;

		if ( 1 === preg_match( '/^var:preset\|duotone\|([A-Za-z0-9_-]+)$/', $value, $matches ) ) {
			$slug = $matches[1];
		} elseif ( 1 === preg_match( '/^var\(--wp--preset--duotone--([A-Za-z0-9_-]+)\)$/', $value, $matches ) ) {
			$slug = $matches[1];
		}

		if ( null === $slug ) {
			return [ null, [] ];
		}

		$colors = self::presetColors( $slug );

		if ( null === $colors ) {
			return [ null, [] ];
		}

		return [ BlockSupports::slugify( $slug ), $colors ];
	}

	/**
	 * Look a duotone preset up in the active theme's resolved
	 * `settings.color.duotone`. Accepts both the flat list theme.json
	 * authors write and the origin-keyed (`theme` / `custom` / `default`)
	 * shape the resolved global styles use. Theme presets are checked
	 * first, then user-custom, then default.
	 *
	 * @since 1.15.0
	 *
	 * @return array<int, mixed>|null
	 */
	protected static function presetColors( string $slug ): ?array
	{
		try {
			$settings = PresetRegistry::activeThemeSettings();
		} catch ( Throwable ) {
			return null;
		}

		$presets = $settings['color']['duotone'] ?? null;

		if ( ! is_array( $presets ) ) {
			return null;
		}

		$lists = array_is_list( $presets )
			? [ $presets ]
			: array_values( array_filter( [
				$presets['theme'] ?? null,
				$presets['custom'] ?? null,
				$presets['default'] ?? null,
			], 'is_array' ) );

		foreach ( $lists as $list ) {
			foreach ( $list as $preset ) {
				if ( ! is_array( $preset ) || ( $preset['slug'] ?? null ) !== $slug ) {
					continue;
				}

				return is_array( $preset['colors'] ?? null ) ? array_values( $preset['colors'] ) : null;
			}
		}

		return null;
	}

	/**
	 * Parse the color stops into `[r, g, b, a]` channel lists (0–1).
	 * One unparseable stop invalidates the whole list, so a hostile or
	 * corrupt value can never reach the SVG markup.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<int, mixed>  $colors
	 *
	 * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
	 */
	protected static function parseColors( array $colors ): array
	{
		$stops = [];

		foreach ( $colors as $color ) {
			$parsed = is_string( $color ) ? self::parseColor( trim( $color ) ) : null;

			if ( null === $parsed ) {
				return [];
			}

			$stops[] = $parsed;
		}

		return $stops;
	}

	/**
	 * Parse one `#rgb[a]` / `#rrggbb[aa]` / `rgb[a]()` color.
	 *
	 * @since 1.15.0
	 *
	 * @return array{0: float, 1: float, 2: float, 3: float}|null
	 */
	protected static function parseColor( string $color ): ?array
	{
		if ( 1 === preg_match( '/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color, $matches ) ) {
			$hex = $matches[1];

			if ( strlen( $hex ) <= 4 ) {
				$hex = implode( '', array_map( static fn ( string $char ): string => $char . $char, str_split( $hex ) ) );
			}

			$channels = array_map( static fn ( string $pair ): float => hexdec( $pair ) / 255, str_split( $hex, 2 ) );

			return [ $channels[0], $channels[1], $channels[2], $channels[3] ?? 1.0 ];
		}

		if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(\d*\.?\d+)\s*)?\)$/i', $color, $matches ) ) {
			$channel = static fn ( string $value ): float => min( 255, (int) $value ) / 255;
			$alpha   = isset( $matches[4] ) && '' !== $matches[4] ? min( 1.0, (float) $matches[4] ) : 1.0;

			return [ $channel( $matches[1] ), $channel( $matches[2] ), $channel( $matches[3] ), $alpha ];
		}

		return null;
	}

	/**
	 * Build the `<filter>` element. Same structure WordPress core emits:
	 * a luminance color matrix, then a per-channel table transfer that
	 * maps the shadows → highlights range onto the color stops.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<int, array{0: float, 1: float, 2: float, 3: float}>  $stops
	 */
	protected static function filterSvg( string $id, array $stops ): string
	{
		$table = static fn ( int $channel ): string => implode( ' ', array_map(
			static fn ( array $stop ): string => self::formatNumber( $stop[ $channel ] ),
			$stops,
		) );

		return sprintf(
			'<filter id="%1$s"><feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0"/>'
			. '<feComponentTransfer color-interpolation-filters="sRGB"><feFuncR type="table" tableValues="%2$s"/><feFuncG type="table" tableValues="%3$s"/><feFuncB type="table" tableValues="%4$s"/><feFuncA type="table" tableValues="%5$s"/></feComponentTransfer>'
			. '<feComposite in2="SourceGraphic" operator="in"/></filter>',
			e( $id ),
			$table( 0 ),
			$table( 1 ),
			$table( 2 ),
			$table( 3 ),
		);
	}

	/**
	 * Format a 0–1 channel value compactly (`1`, `0.5`, `0.1373`).
	 *
	 * @since 1.15.0
	 */
	protected static function formatNumber( float $value ): string
	{
		$formatted = rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );

		return '' === $formatted ? '0' : $formatted;
	}
}
