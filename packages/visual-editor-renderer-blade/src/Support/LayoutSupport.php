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

use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use Throwable;

class LayoutSupport
{
	/**
	 * Fallback minimum column width for a grid layout that stores no
	 * `columnCount` / `minimumColumnWidth`. Matches the block editor's
	 * grid layout default (#871).
	 *
	 * @since 1.15.0
	 */
	public const DEFAULT_MINIMUM_COLUMN_WIDTH = '12rem';

	/**
	 * Largest `layout.columnCount` the renderer accepts (#871). Anything
	 * above it is dropped rather than minting an unbounded track list.
	 *
	 * @since 1.15.0
	 */
	public const MAX_COLUMN_COUNT = 64;

	/**
	 * Child selector the constrained layout caps, matching WordPress's
	 * `wp_get_layout_style()` (#871).
	 *
	 * @since 1.15.0
	 */
	public const CONSTRAINED_CHILD_SELECTOR = ' > :where(:not(.alignleft):not(.alignright):not(.alignfull))';

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
	 * Gap a flex or grid container uses for the side of a per-axis
	 * `blockGap` it leaves unset, matching `wp_get_layout_style()`'s
	 * fallback when a block declares no
	 * `supports.spacing.blockGap.__experimentalDefault` (M-15).
	 *
	 * @since 1.15.0
	 */
	public const DEFAULT_BLOCK_GAP_FALLBACK = '0.5em';

	/**
	 * `layout.justifyContent` values that become an
	 * `is-content-justification-{value}` class, matching the values the
	 * block editor's layout panels store (F-3).
	 *
	 * @since 1.15.0
	 *
	 * @var array<int, string>
	 */
	public const JUSTIFICATION_CLASSES = [ 'left', 'center', 'right', 'space-between', 'stretch' ];

	/**
	 * `layout.orientation` values that become an `is-{orientation}`
	 * class (F-3).
	 *
	 * @since 1.15.0
	 *
	 * @var array<int, string>
	 */
	public const ORIENTATION_CLASSES = [ 'horizontal', 'vertical' ];

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

		// A legacy `inherit: true` layout (saved before the constrained
		// type existed) is a constrained layout, as in WordPress's
		// `wp_render_layout_support_flag()` and the editor (F-3).
		if ( '' === $type && true === ( $attributes[ 'layout' ][ 'inherit' ] ?? null ) ) {
			$type = 'constrained';
		}

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

	/**
	 * Resolve the per-instance layout rules for a block, push them into
	 * the per-request CSS accumulator under a hashed `wp-container-{hash}`
	 * scope class, and return that class (or an empty list when the
	 * block's layout needs no per-instance rule) (#871).
	 *
	 * The shared `is-layout-{type}` class only carries the layout's
	 * defaults. The values an author sets in the layout panel — content
	 * and wide size, justification, orientation, wrap, grid columns —
	 * and the block's own Block spacing are per block, so, like
	 * WordPress's `wp_get_layout_style()`, they become a rule scoped to
	 * that one wrapper. The scope is hashed from the rule itself so
	 * identical layouts share one rule; the React and Vue renderers hash
	 * it the same way (`layoutContainer.ts`).
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes    Raw block attributes.
	 * @param  string                $blockSlug     Wrapper slug without the
	 *                                              `wp-block-` prefix.
	 * @param  string                $layoutClass   The resolved
	 *                                              `is-layout-{type}` class
	 *                                              the wrapper carries.
	 * @param  string|null           $wrapperClass  The class the layout
	 *                                              element carries instead
	 *                                              of `wp-block-{slug}` (a
	 *                                              cover's inner container),
	 *                                              or null.
	 * @param  string                $gapFallback   Gap for the unset side of
	 *                                              a per-axis flex or grid
	 *                                              `blockGap`.
	 *
	 * @return array<int, string>
	 */
	public static function containerClasses( array $attributes, string $blockSlug, string $layoutClass, ?string $wrapperClass = null, string $gapFallback = self::DEFAULT_BLOCK_GAP_FALLBACK ): array
	{
		$container = self::container( $attributes, $blockSlug, $layoutClass, $wrapperClass, $gapFallback );

		if ( null === $container ) {
			return [];
		}

		try {
			app( ResponsiveCssAccumulator::class )->push( $container['class'], $container['css'] );
		} catch ( Throwable $e ) {
			// Accumulator not booted (early or test path) — silently drop.
		}

		return [ $container['class'] ];
	}

	/**
	 * The `is-{orientation}`, `is-content-justification-{value}` and
	 * `is-nowrap` modifier classes WordPress's
	 * `wp_render_layout_support_flag()` (and the editor's layout hook)
	 * add to a layout wrapper (F-3). Only allowlisted values mint a
	 * class.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes     Raw block attributes.
	 * @param  bool                  $orientation    Whether to add the
	 *                                               orientation class (off
	 *                                               for blocks that add
	 *                                               their own).
	 * @param  bool                  $justification  Whether to add the
	 *                                               justification class (off
	 *                                               for blocks that add
	 *                                               their own or don't allow
	 *                                               justification).
	 *
	 * @return array<int, string>
	 */
	public static function modifierClasses( array $attributes, bool $orientation = true, bool $justification = true ): array
	{
		$layout  = is_array( $attributes['layout'] ?? null ) ? $attributes['layout'] : [];
		$classes = [];

		if ( $orientation && in_array( $layout['orientation'] ?? null, self::ORIENTATION_CLASSES, true ) ) {
			$classes[] = 'is-' . $layout['orientation'];
		}

		if ( $justification && in_array( $layout['justifyContent'] ?? null, self::JUSTIFICATION_CLASSES, true ) ) {
			$classes[] = 'is-content-justification-' . $layout['justifyContent'];
		}

		if ( 'nowrap' === ( $layout['flexWrap'] ?? null ) ) {
			$classes[] = 'is-nowrap';
		}

		return $classes;
	}

	/**
	 * Build the scope class and CSS for a block's layout, or null when
	 * the layout declares nothing beyond its type's defaults (#871).
	 *
	 * The selector chains the wrapper class, the per-block layout
	 * compound and the scope, so it outranks the shared containment
	 * rules (`.wp-block-group.wp-block-group-is-layout-constrained > …`)
	 * that apply the global content size.
	 *
	 * The block's own `style.spacing.blockGap` is scoped here too (M-15),
	 * the way `wp_get_layout_style()` does it: `gap` on a flex or grid
	 * container, `margin-block-start` between the children of a flow or
	 * constrained one. Because the rule targets this one wrapper, a
	 * nested container doesn't pick the spacing up.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes    Raw block attributes.
	 * @param  string                $blockSlug     Wrapper slug without the
	 *                                              `wp-block-` prefix.
	 * @param  string                $layoutClass   The resolved
	 *                                              `is-layout-{type}` class.
	 * @param  string|null           $wrapperClass  The class the layout
	 *                                              element carries instead
	 *                                              of `wp-block-{slug}`, or
	 *                                              null.
	 * @param  string                $gapFallback   Gap for the unset side of
	 *                                              a per-axis flex or grid
	 *                                              `blockGap`.
	 *
	 * @return array{class: string, css: string}|null
	 */
	public static function container( array $attributes, string $blockSlug, string $layoutClass, ?string $wrapperClass = null, string $gapFallback = self::DEFAULT_BLOCK_GAP_FALLBACK ): ?array
	{
		$layout = is_array( $attributes['layout'] ?? null ) ? $attributes['layout'] : [];
		$type   = str_starts_with( $layoutClass, 'is-layout-' ) ? substr( $layoutClass, strlen( 'is-layout-' ) ) : '';

		$rules = match ( $type ) {
			'constrained' => self::constrainedRules( $layout ),
			'flex'        => self::flexRules( $layout ),
			'grid'        => self::gridRules( $layout ),
			default       => [],
		};

		$rules = self::withGapRules( $rules, $type, $attributes, $gapFallback );

		if ( [] === $rules ) {
			return null;
		}

		$body = '';

		foreach ( $rules as [ $suffix, $declarations ] ) {
			$body .= $suffix . '{' . implode( ';', $declarations ) . ';}';
		}

		$element  = $wrapperClass ?? 'wp-block-' . $blockSlug;
		$hashKey  = ( null === $wrapperClass ? '' : $wrapperClass . '|' ) . $blockSlug . '|' . $layoutClass . '|' . $body;
		$scope    = 'wp-container-' . substr( sha1( $hashKey ), 0, 12 );
		$selector = '.' . $element . '.wp-block-' . $blockSlug . '-' . $layoutClass . '.' . $scope;
		$css      = '';

		foreach ( $rules as [ $suffix, $declarations ] ) {
			$css .= $selector . $suffix . '{' . implode( ';', $declarations ) . ';}';
		}

		return [
			'class' => $scope,
			'css'   => $css,
		];
	}

	/**
	 * A `blockGap` value that is safe to print, with a preset reference
	 * (`var:preset|spacing|40`) expanded, or null (F-10). Anything
	 * {@see self::cssLength()} rejects is dropped, so a stored
	 * `1rem;position:fixed` can't add declarations to the inline style
	 * or the per-instance rule.
	 *
	 * @since 1.15.0
	 */
	public static function blockGapValue( mixed $value ): ?string
	{
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );

		if ( str_starts_with( $value, 'var:preset|' ) ) {
			$segments = explode( '|', substr( $value, strlen( 'var:preset|' ) ) );
			$value    = 'var(--wp--preset--' . implode( '--', array_map( [ PresetRegistry::class, 'presetSlug' ], $segments ) ) . ')';
		}

		return self::cssLength( $value );
	}

	/**
	 * A stored length (`contentSize`, `wideSize`, `minimumColumnWidth`)
	 * that is safe to splice into a `<style>` block, or null. Values
	 * that fail {@see BlockSupports::safeCssValue()} or leave a
	 * parenthesis unbalanced are dropped (#871).
	 *
	 * @since 1.15.0
	 */
	public static function cssLength( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = BlockSupports::safeCssValue( trim( $value ) );

		if ( null === $value ) {
			return null;
		}

		$depth = 0;

		foreach ( str_split( $value ) as $char ) {
			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char && --$depth < 0 ) {
				return null;
			}
		}

		return 0 === $depth ? $value : null;
	}

	/**
	 * Constrained layout: cap unaligned children at the block's content
	 * size, aligned-wide children at its wide size, and honor the
	 * left / right justification (#871).
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $layout
	 *
	 * @return array<int, array{0: string, 1: array<int, string>}>
	 */
	protected static function constrainedRules( array $layout ): array
	{
		$contentSize = self::cssLength( $layout['contentSize'] ?? null );
		$wideSize    = self::cssLength( $layout['wideSize'] ?? null );
		$justify     = $layout['justifyContent'] ?? null;

		$marginLeft  = 'left' === $justify ? '0 !important' : 'auto !important';
		$marginRight = 'right' === $justify ? '0 !important' : 'auto !important';

		if ( null === $contentSize && null === $wideSize ) {
			if ( 'left' === $justify ) {
				return [ [ self::CONSTRAINED_CHILD_SELECTOR, [ 'margin-left:0 !important' ] ] ];
			}

			if ( 'right' === $justify ) {
				return [ [ self::CONSTRAINED_CHILD_SELECTOR, [ 'margin-right:0 !important' ] ] ];
			}

			return [];
		}

		return [
			[
				self::CONSTRAINED_CHILD_SELECTOR,
				[
					'max-width:' . ( $contentSize ?? $wideSize ),
					'margin-left:' . $marginLeft,
					'margin-right:' . $marginRight,
				],
			],
			[ ' > .alignwide', [ 'max-width:' . ( $wideSize ?? $contentSize ) ] ],
		];
	}

	/**
	 * Flex layout: wrap, orientation, justification and vertical
	 * alignment, mapped the way WordPress maps them (#871).
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $layout
	 *
	 * @return array<int, array{0: string, 1: array<int, string>}>
	 */
	protected static function flexRules( array $layout ): array
	{
		$isVertical = 'vertical' === ( $layout['orientation'] ?? null );

		$justifyOptions  = [ 'left' => 'flex-start', 'right' => 'flex-end', 'center' => 'center' ];
		$verticalOptions = [ 'top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end' ];

		if ( $isVertical ) {
			$justifyOptions['stretch']        = 'stretch';
			$verticalOptions['space-between'] = 'space-between';
		} else {
			$justifyOptions['space-between'] = 'space-between';
			$verticalOptions['stretch']      = 'stretch';
		}

		$justify  = self::option( $layout['justifyContent'] ?? null, $justifyOptions );
		$vertical = self::option( $layout['verticalAlignment'] ?? null, $verticalOptions );

		$declarations = [];

		if ( 'nowrap' === ( $layout['flexWrap'] ?? null ) ) {
			$declarations[] = 'flex-wrap:nowrap';
		}

		if ( $isVertical ) {
			$declarations[] = 'flex-direction:column';
			$declarations[] = 'align-items:' . ( $justify ?? 'flex-start' );

			if ( null !== $vertical ) {
				$declarations[] = 'justify-content:' . $vertical;
			}
		} else {
			if ( null !== $justify ) {
				$declarations[] = 'justify-content:' . $justify;
			}

			if ( null !== $vertical ) {
				$declarations[] = 'align-items:' . $vertical;
			}
		}

		return [] === $declarations ? [] : [ [ '', $declarations ] ];
	}

	/**
	 * Grid layout: a fixed column count, an auto-fill track list from
	 * the minimum column width, or both — the column count then caps an
	 * auto-fill grid that still collapses on narrow containers (#871).
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $layout
	 *
	 * @return array<int, array{0: string, 1: array<int, string>}>
	 */
	protected static function gridRules( array $layout ): array
	{
		$columnCount = self::columnCount( $layout['columnCount'] ?? null );
		$minimum     = self::cssLength( $layout['minimumColumnWidth'] ?? null );

		if ( null !== $columnCount && null !== $minimum ) {
			$track = 'max(min(' . $minimum . ', 100%), (100% - (var(--wp--style--block-gap, 0.5em) * ' . ( $columnCount - 1 ) . ')) / ' . $columnCount . ')';

			return [ [ '', [ 'grid-template-columns:repeat(auto-fill, minmax(' . $track . ', 1fr))', 'container-type:inline-size' ] ] ];
		}

		if ( null !== $columnCount ) {
			return [ [ '', [ 'grid-template-columns:repeat(' . $columnCount . ', minmax(0, 1fr))' ] ] ];
		}

		$minimum ??= self::DEFAULT_MINIMUM_COLUMN_WIDTH;

		return [ [ '', [ 'grid-template-columns:repeat(auto-fill, minmax(min(' . $minimum . ', 100%), 1fr))', 'container-type:inline-size' ] ] ];
	}

	/**
	 * Add the block's own `style.spacing.blockGap` to its layout rules
	 * (M-15), following `wp_get_layout_style()`:
	 *
	 * - flex and grid take a `gap` on the container. A per-axis value
	 *   becomes `gap: <row> <column>` (one value when both match), with
	 *   `$gapFallback` standing in for the side that isn't set;
	 * - flow and constrained space their children with
	 *   `margin-block-start`, from the top side of a per-axis value.
	 *
	 * Unsafe values are dropped ({@see self::blockGapValue()}).
	 *
	 * @since 1.15.0
	 *
	 * @param  array<int, array{0: string, 1: array<int, string>}>  $rules
	 * @param  array<string, mixed>                                 $attributes
	 *
	 * @return array<int, array{0: string, 1: array<int, string>}>
	 */
	protected static function withGapRules( array $rules, string $type, array $attributes, string $gapFallback ): array
	{
		$style   = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : [];
		$spacing = is_array( $style['spacing'] ?? null ) ? $style['spacing'] : [];
		$gap     = $spacing['blockGap'] ?? null;

		if ( 'flex' === $type || 'grid' === $type ) {
			if ( is_array( $gap ) ) {
				$row    = self::blockGapValue( $gap['top'] ?? null );
				$column = self::blockGapValue( $gap['left'] ?? null );

				if ( null === $row && null === $column ) {
					return $rules;
				}

				$row ??= $gapFallback;
				$column ??= $gapFallback;
				$value    = $row === $column ? $row : $row . ' ' . $column;
			} else {
				$value = is_string( $gap ) ? self::blockGapValue( $gap ) : null;
			}

			if ( null === $value ) {
				return $rules;
			}

			foreach ( $rules as $index => [ $suffix ] ) {
				if ( '' === $suffix ) {
					$rules[ $index ][1][] = 'gap:' . $value;

					return $rules;
				}
			}

			$rules[] = [ '', [ 'gap:' . $value ] ];

			return $rules;
		}

		if ( 'flow' === $type || 'constrained' === $type ) {
			$value = is_array( $gap )
				? self::blockGapValue( $gap['top'] ?? null )
				: ( is_string( $gap ) ? self::blockGapValue( $gap ) : null );

			if ( null !== $value ) {
				$rules[] = [ ' > * + *', [ 'margin-block-start:' . $value, 'margin-block-end:0' ] ];
			}
		}

		return $rules;
	}

	/**
	 * A positive whole `columnCount` up to {@see self::MAX_COLUMN_COUNT},
	 * or null. Accepts an integer, an integral float (how a JSON `3.0`
	 * decodes) or a digit string (#871).
	 *
	 * @since 1.15.0
	 */
	protected static function columnCount( mixed $value ): ?int
	{
		if ( is_string( $value ) && 1 === preg_match( '/^[0-9]+$/', trim( $value ) ) ) {
			$value = (int) trim( $value );
		} elseif ( is_float( $value ) && floor( $value ) === $value && $value <= self::MAX_COLUMN_COUNT ) {
			$value = (int) $value;
		}

		if ( ! is_int( $value ) || $value < 1 || $value > self::MAX_COLUMN_COUNT ) {
			return null;
		}

		return $value;
	}

	/**
	 * Map a stored option through an allowlist, or null.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, string>  $options
	 */
	protected static function option( mixed $value, array $options ): ?string
	{
		return is_string( $value ) && isset( $options[ $value ] ) ? $options[ $value ] : null;
	}
}
