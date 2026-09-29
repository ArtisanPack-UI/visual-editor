<?php

/**
 * Validator for a menu's `core/navigation-*` block tree.
 *
 * Applied to `content.blocks` on `POST` / `PUT /visual-editor/api/menus`,
 * and by {@see \ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\MenuController}
 * to the tree it parses out of a serialized `content` / `content.raw`
 * string, so every shape the endpoint accepts is bounded the same way
 * before {@see \ArtisanPackUI\VisualEditor\SiteEditor\MenuItemBlockBridge}
 * turns it into `menu_items` rows.
 *
 * Enforces:
 *
 *   - The same depth / node bounds as {@see TemplateBlockTreeRule}
 *     (`MAX_DEPTH` / `MAX_NODES`). Unlike that rule, `attributes` and
 *     `innerBlocks` may be omitted (the bridge treats a missing key as
 *     empty) but must be arrays when present.
 *   - Types on the attributes that map onto dedicated `menu_items`
 *     columns (navigation blocks only — other blocks are dropped by the
 *     bridge): `label`, `url`, `rel`, `className`, `kind`, `type` are
 *     string|null, `opensInNewTab` is bool|null, `id` is int|string|null.
 *   - A cap on each item's extra attributes — the ones persisted into the
 *     `block_attributes` JSON column — by JSON-encoded size and nesting
 *     depth, well below MySQL's JSON nesting limit of 100.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.12.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor;

use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use ArtisanPackUI\VisualEditor\SiteEditor\MenuItemBlockBridge;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class MenuContentBlocksRule implements ValidationRule
{
	/**
	 * Maximum navigation tree depth.
	 *
	 * @since 1.12.0
	 */
	public const MAX_DEPTH = TemplateBlockTreeRule::MAX_DEPTH;

	/**
	 * Maximum number of blocks in the tree.
	 *
	 * @since 1.12.0
	 */
	public const MAX_NODES = TemplateBlockTreeRule::MAX_NODES;

	/**
	 * Maximum JSON-encoded size, in bytes, of one item's extra attributes.
	 *
	 * @since 1.12.0
	 */
	public const MAX_EXTRA_ATTRIBUTES_BYTES = 16384;

	/**
	 * Maximum nesting depth of one item's extra attributes, counting the
	 * enclosing object stored in `block_attributes`.
	 *
	 * @since 1.12.0
	 */
	public const MAX_EXTRA_ATTRIBUTES_DEPTH = 16;

	/**
	 * Column-mapped attributes that must be a string (or null).
	 *
	 * @since 1.12.0
	 *
	 * @var array<int, string>
	 */
	protected const STRING_ATTRIBUTES = [ 'label', 'url', 'rel', 'className', 'kind', 'type' ];

	/**
	 * Validate the block tree.
	 *
	 * @since 1.12.0
	 *
	 * @param  string   $attribute  The attribute under validation.
	 * @param  mixed    $value      The block tree.
	 * @param  Closure  $fail       The failure callback.
	 */
	public function validate( string $attribute, mixed $value, Closure $fail ): void
	{
		if ( null === $value ) {
			return;
		}

		if ( ! is_array( $value ) ) {
			$fail( __( 'The :attribute must be an array of blocks.' ) );

			return;
		}

		$nodeCount = 0;
		$error     = $this->validateBlocks( $value, $attribute, 0, $nodeCount );

		if ( null !== $error ) {
			$fail( $error );
		}
	}

	/**
	 * Recursively validate a list of blocks, returning the first error.
	 *
	 * @since 1.12.0
	 *
	 * @param  array<array-key, mixed>  $blocks
	 */
	protected function validateBlocks( array $blocks, string $path, int $depth, int &$nodeCount ): ?string
	{
		// An empty leaf list never counts as a level, so a tree of
		// exactly `MAX_DEPTH` nested blocks is allowed.
		if ( [] !== $blocks && $depth >= self::MAX_DEPTH ) {
			return __( 'The :path exceeds the maximum navigation depth of :max.', [
				'path' => $path,
				'max'  => self::MAX_DEPTH,
			] );
		}

		if ( ! array_is_list( $blocks ) ) {
			return __( 'The :path must be a list of blocks.', [ 'path' => $path ] );
		}

		foreach ( $blocks as $index => $block ) {
			$blockPath = $path . '.' . $index;

			$nodeCount++;

			if ( $nodeCount > self::MAX_NODES ) {
				return __( 'The navigation tree exceeds the maximum of :max blocks.', [ 'max' => self::MAX_NODES ] );
			}

			if ( ! is_array( $block ) ) {
				return __( 'The :path must be a block object.', [ 'path' => $blockPath ] );
			}

			if ( ! isset( $block['name'] ) || ! is_string( $block['name'] ) || '' === $block['name'] ) {
				return __( 'The :path.name is required and must be a string.', [ 'path' => $blockPath ] );
			}

			$attributes = $block['attributes'] ?? [];

			if ( ! is_array( $attributes ) ) {
				return __( 'The :path.attributes must be an object.', [ 'path' => $blockPath ] );
			}

			$isNavigationBlock = MenuItemBlockBridge::NAV_LINK === $block['name']
				|| MenuItemBlockBridge::NAV_SUBMENU === $block['name'];

			if ( $isNavigationBlock ) {
				$attributeError = $this->validateNavigationAttributes( $attributes, $blockPath . '.attributes' );

				if ( null !== $attributeError ) {
					return $attributeError;
				}
			}

			$innerBlocks = $block['innerBlocks'] ?? [];

			if ( ! is_array( $innerBlocks ) ) {
				return __( 'The :path.innerBlocks must be an array.', [ 'path' => $blockPath ] );
			}

			$childError = $this->validateBlocks( $innerBlocks, $blockPath . '.innerBlocks', $depth + 1, $nodeCount );

			if ( null !== $childError ) {
				return $childError;
			}
		}

		return null;
	}

	/**
	 * Type-check the column-mapped attributes and bound the extras.
	 *
	 * @since 1.12.0
	 *
	 * @param  array<array-key, mixed>  $attributes
	 */
	protected function validateNavigationAttributes( array $attributes, string $path ): ?string
	{
		foreach ( self::STRING_ATTRIBUTES as $key ) {
			if ( array_key_exists( $key, $attributes ) && null !== $attributes[ $key ] && ! is_string( $attributes[ $key ] ) ) {
				return __( 'The :path.:key must be a string.', [ 'path' => $path, 'key' => $key ] );
			}
		}

		if ( array_key_exists( 'opensInNewTab', $attributes )
			&& null !== $attributes['opensInNewTab']
			&& ! is_bool( $attributes['opensInNewTab'] )
		) {
			return __( 'The :path.opensInNewTab must be a boolean.', [ 'path' => $path ] );
		}

		if ( array_key_exists( 'id', $attributes )
			&& null !== $attributes['id']
			&& ! is_int( $attributes['id'] )
			&& ! is_string( $attributes['id'] )
		) {
			return __( 'The :path.id must be an integer or a string.', [ 'path' => $path ] );
		}

		$extra = array_diff_key( $attributes, array_flip( [ ...self::STRING_ATTRIBUTES, 'opensInNewTab', 'id' ] ) );

		if ( [] === $extra ) {
			return null;
		}

		// `+ 1` for the object the extras are stored under.
		if ( $this->nestingDepth( $extra, self::MAX_EXTRA_ATTRIBUTES_DEPTH ) + 1 > self::MAX_EXTRA_ATTRIBUTES_DEPTH ) {
			return __( 'The :path nest deeper than :max levels.', [
				'path' => $path,
				'max'  => self::MAX_EXTRA_ATTRIBUTES_DEPTH,
			] );
		}

		$encoded = json_encode( $extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );

		if ( false === $encoded || strlen( $encoded ) > self::MAX_EXTRA_ATTRIBUTES_BYTES ) {
			return __( 'The :path exceed the maximum size of :max bytes.', [
				'path' => $path,
				'max'  => self::MAX_EXTRA_ATTRIBUTES_BYTES,
			] );
		}

		return null;
	}

	/**
	 * Nesting depth of the arrays inside `$value` (the value itself
	 * counts as 0). Stops descending once `$limit` is passed so a
	 * pathological payload can't make the check itself expensive.
	 *
	 * @since 1.12.0
	 *
	 * @param  array<array-key, mixed>  $value
	 */
	protected function nestingDepth( array $value, int $limit ): int
	{
		$deepest = 0;

		foreach ( $value as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$childDepth = 1 + ( $limit > 0 ? $this->nestingDepth( $child, $limit - 1 ) : 0 );

			if ( $childDepth > $deepest ) {
				$deepest = $childDepth;
			}

			if ( $deepest > $limit ) {
				break;
			}
		}

		return $deepest;
	}
}
