<?php

/**
 * Layout-support class serializer — Blade renderer (#700).
 *
 * Blocks that declare a layout carry two classes upstream: the shared
 * `is-layout-{type}` modifier and the per-block
 * `wp-block-{slug}-is-layout-{type}` compound. The shipped block-library
 * stylesheet targets the per-block form exclusively for several rules
 * (constrained containment on `.wp-block-group`, alignment handling on
 * `.wp-block-post-template`), so emitting only the shared class leaves
 * those rules unmatched.
 *
 * The block slug is passed in by each partial rather than derived from
 * the block name because several blocks render as a different wrapper
 * than their name suggests — `artisanpack/row` and `artisanpack/stack`
 * both render `wp-block-group` markup and therefore need the
 * `wp-block-group-is-layout-flex` compound, not `wp-block-row-…`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class LayoutSupport
{
	/**
	 * Layout types the renderer knows how to serialize. Anything else
	 * stored on the block falls back to the caller's default so an
	 * unknown value can't mint an arbitrary class token.
	 *
	 * @since 1.6.0
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_TYPES = [ 'constrained', 'flex', 'flow', 'grid' ];

	/**
	 * Resolve the shared layout class for a block from its saved
	 * `layout.type` attribute.
	 *
	 * @since 1.6.0
	 *
	 * @param  array<string, mixed>  $attributes  Raw block attributes.
	 * @param  string                $default     Type to use when the block
	 *                                            stores no (or an unknown)
	 *                                            `layout.type`.
	 *
	 * @return string The `is-layout-{type}` class.
	 */
	public static function layoutClass( array $attributes, string $default = 'flow' ): string
	{
		$type = isset( $attributes[ 'layout' ][ 'type' ] ) && is_string( $attributes[ 'layout' ][ 'type' ] )
			? trim( $attributes[ 'layout' ][ 'type' ] )
			: '';

		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			$type = in_array( $default, self::SUPPORTED_TYPES, true ) ? $default : 'flow';
		}

		return 'is-layout-' . $type;
	}

	/**
	 * Pair each shared layout class with its per-block compound so the
	 * block-library CSS that targets `wp-block-{slug}-is-layout-{type}`
	 * matches. Order mirrors upstream: shared class first, compound
	 * immediately after.
	 *
	 * @since 1.6.0
	 *
	 * @param  string  $blockSlug       Wrapper slug without the `wp-block-`
	 *                                  prefix (e.g. `group`, `post-content`).
	 * @param  string  ...$layoutClasses One or more `is-layout-{type}` classes.
	 *
	 * @return array<int, string> Flat class list ready to splice into
	 *                            `BlockSupports::wrapperAttrs()`.
	 */
	public static function pair( string $blockSlug, string ...$layoutClasses ): array
	{
		$classes = [];

		foreach ( $layoutClasses as $layoutClass ) {
			if ( '' === $layoutClass ) {
				continue;
			}

			$classes[] = $layoutClass;
			$classes[] = 'wp-block-' . $blockSlug . '-' . $layoutClass;
		}

		return $classes;
	}

	/**
	 * Shorthand for the common case: resolve the layout class from the
	 * block's attributes and return it paired with its compound.
	 *
	 * @since 1.6.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  string                $blockSlug
	 * @param  string                $default
	 *
	 * @return array<int, string>
	 */
	public static function wrapperForBlock( array $attributes, string $blockSlug, string $default = 'flow' ): array
	{
		return self::pair( $blockSlug, self::layoutClass( $attributes, $default ) );
	}
}
