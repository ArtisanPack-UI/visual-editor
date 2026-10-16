<?php

/**
 * Opt-in allowlist for the attributes block bindings may read off
 * related models (#869).
 *
 * {@see ModelAttributeVisibility} keeps `$hidden` columns out of
 * bindings, but anything else a related model serializes stays
 * readable — so a content editor can bind a block to `author.email` and
 * publish it on the front end. When the host sets
 * `artisanpack.visual-editor.bindings.enforce_allowlist` to true, a
 * binding may only read a related model's attribute when it is listed
 * for that model by any of:
 *
 *   1. the model itself, via {@see DefinesBindableAttributes};
 *   2. the `bindings.allowed_attributes` config map, keyed by model
 *      class (`[ User::class => [ 'name' ] ]`);
 *   3. the `ap.visualEditor.bindings.allowedAttributes` filter, which
 *      receives the merged list, the model, and the binding path, and
 *      returns the final list.
 *
 * `'*'` in the final list allows every key. The parent record's own
 * attributes are never gated here; the allowlist covers other models
 * reached through relations. Enforcement is off by default in 1.x so
 * existing relation bindings keep working.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;

final class BindingAttributeAllowlist
{
	/**
	 * Wildcard entry that allows every key on a model.
	 *
	 * @since 1.15.0
	 */
	public const WILDCARD = '*';

	/**
	 * True when the host has opted into deny-by-default bindings.
	 *
	 * @since 1.15.0
	 */
	public static function isEnforced(): bool
	{
		return true === (bool) config( 'artisanpack.visual-editor.bindings.enforce_allowlist', false );
	}

	/**
	 * True when a binding may read `$key` off the related `$model`.
	 * Always true while enforcement is off.
	 *
	 * @since 1.15.0
	 *
	 * @param  Model   $model  Related record the binding reads from.
	 * @param  string  $key    Attribute or relation name.
	 * @param  string  $path   Full binding path, passed to the filter.
	 */
	public static function allows( Model $model, string $key, string $path = '' ): bool
	{
		if ( ! self::isEnforced() ) {
			return true;
		}

		$allowed = self::allowedFor( $model, $path );

		return in_array( self::WILDCARD, $allowed, true ) || in_array( $key, $allowed, true );
	}

	/**
	 * The resolved allowlist for `$model`: the model's own declaration,
	 * merged with the config map, then passed through the
	 * `ap.visualEditor.bindings.allowedAttributes` filter.
	 *
	 * @since 1.15.0
	 *
	 * @param  Model   $model  Related record the binding reads from.
	 * @param  string  $path   Full binding path, passed to the filter.
	 *
	 * @return array<int, string>
	 */
	public static function allowedFor( Model $model, string $path = '' ): array
	{
		$allowed = $model instanceof DefinesBindableAttributes ? $model->bindableAttributes() : [];

		$map = config( 'artisanpack.visual-editor.bindings.allowed_attributes', [] );

		if ( is_array( $map ) ) {
			foreach ( $map as $class => $attributes ) {
				if ( is_string( $class ) && is_array( $attributes ) && $model instanceof $class ) {
					$allowed = array_merge( $allowed, $attributes );
				}
			}
		}

		if ( function_exists( 'applyFilters' ) ) {
			$allowed = applyFilters( 'ap.visualEditor.bindings.allowedAttributes', self::normalise( $allowed ), $model, $path );
		}

		return is_array( $allowed ) ? self::normalise( $allowed ) : [];
	}

	/**
	 * True when `$value` is a model, or a list that holds one at any
	 * depth: an array or any `Enumerable` (Eloquent and support
	 * collections, lazy collections). With the allowlist enforced, a
	 * binding source returns nothing for such a value, since publishing
	 * it would serialize every attribute of the record and bypass the
	 * per-attribute check.
	 *
	 * @since 1.15.0
	 *
	 * @param  mixed  $value  A resolved binding value.
	 */
	public static function containsModel( mixed $value ): bool
	{
		if ( $value instanceof Model ) {
			return true;
		}

		if ( $value instanceof Enumerable ) {
			$value = $value->all();
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( self::containsModel( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keep only non-empty string entries, de-duplicated.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<mixed>  $list
	 *
	 * @return array<int, string>
	 */
	private static function normalise( array $list ): array
	{
		$strings = array_filter( $list, static fn ( mixed $entry ): bool => is_string( $entry ) && '' !== trim( $entry ) );

		return array_values( array_unique( array_map( 'trim', $strings ) ) );
	}
}
