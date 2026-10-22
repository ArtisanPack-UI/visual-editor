<?php

/**
 * Navigation collapse breakpoint rules (#977).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.2
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

/**
 * The bundled block-library CSS collapses a `core/navigation` with
 * `overlayMenu: "mobile"` behind its open button below a fixed 600px.
 * A navigation's `collapseBreakpoint` attribute (`mobile` / `tablet`)
 * moves that point to the matching device breakpoint of the registry:
 * the `<nav>` carries `collapses-at-{key}`, and the rules built here keep
 * the drawer collapsed up to the breakpoint's max-width and show the
 * inline menu above it, overriding the 600px rules in both directions.
 *
 * The React and Vue renderers mirror {@see css()} byte for byte in
 * `support/navigationCollapseCss.ts`; the editor canvas mirrors it in
 * `editor/navigation-collapse-breakpoint.tsx`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.15.2
 */
class NavigationCollapseStyles
{
	/**
	 * Breakpoint keys a navigation may collapse at, matching the
	 * registry's desktop-first device keys (and the screen-size
	 * visibility keys) so a block can be hidden at exactly the
	 * collapse point.
	 *
	 * @since 1.15.2
	 *
	 * @var array<int, string>
	 */
	public const KEYS = [ 'mobile', 'tablet' ];

	/**
	 * Class prefix stamped on the `<nav>`; the key is appended.
	 *
	 * @since 1.15.2
	 */
	public const CLASS_PREFIX = 'collapses-at-';

	/**
	 * Resolve a navigation's collapse breakpoint.
	 *
	 * Returns the key and its max-width, or `null` for the legacy 600px
	 * behavior: the attribute is absent, empty or unknown, the overlay
	 * isn't `mobile`, or the registry has no device entry for the key.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attributes  The navigation block's attributes.
	 * @param  BreakpointRegistry    $registry    The breakpoint registry.
	 *
	 * @return array{key: string, maxWidth: int}|null
	 */
	public static function resolve( array $attributes, BreakpointRegistry $registry ): ?array
	{
		$overlayMenu = isset( $attributes['overlayMenu'] ) && is_string( $attributes['overlayMenu'] ) ? $attributes['overlayMenu'] : 'mobile';
		$key         = isset( $attributes['collapseBreakpoint'] ) && is_string( $attributes['collapseBreakpoint'] ) ? $attributes['collapseBreakpoint'] : '';

		if ( 'mobile' !== $overlayMenu || ! in_array( $key, self::KEYS, true ) ) {
			return null;
		}

		$maxWidth = $registry->maxWidth( $key );

		if ( null === $maxWidth || $maxWidth <= 0 ) {
			return null;
		}

		return [
			'key'      => $key,
			'maxWidth' => $maxWidth,
		];
	}

	/**
	 * The `<nav>` class for a collapse breakpoint key.
	 *
	 * @since 1.15.2
	 *
	 * @param  string  $key  A key from {@see KEYS}.
	 */
	public static function className( string $key ): string
	{
		return self::CLASS_PREFIX . $key;
	}

	/**
	 * Build the collapse rules for one breakpoint.
	 *
	 * Selectors are prefixed with the `<nav>` class pair so they outrank
	 * the bundled `(min-width: 600px)` rules (`.a:not(.b):not(.c)`) on
	 * specificity, wherever in the page they land. `always` overlays
	 * (`hidden-by-default` / `always-shown`) are excluded exactly as
	 * upstream excludes them.
	 *
	 * @since 1.15.2
	 *
	 * @param  string  $key       A key from {@see KEYS}.
	 * @param  int     $maxWidth  The breakpoint's max-width in px.
	 */
	public static function css( string $key, int $maxWidth ): string
	{
		$nav       = '.wp-block-navigation.' . self::className( $key );
		$container = $nav . ' .wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open)';
		$open      = $nav . ' .wp-block-navigation__responsive-container-open:not(.always-shown)';
		$above     = $maxWidth + 1;

		return "@media (max-width: {$maxWidth}px) {"
			. "{$container} { display: none; }"
			. "{$open} { display: flex; }"
			. '}'
			. "@media (min-width: {$above}px) {"
			. "{$container} { display: block; width: 100%; position: relative; z-index: auto; background-color: inherit; }"
			. "{$container} .wp-block-navigation__responsive-container-close { display: none; }"
			. "{$open} { display: none; }"
			. '}';
	}
}
