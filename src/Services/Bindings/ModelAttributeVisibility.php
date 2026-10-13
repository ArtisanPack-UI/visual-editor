<?php

/**
 * Serialization-visibility check shared by the model-backed binding sources.
 *
 * Front-end renders publish resolved binding values to visitors (#848),
 * so a source must not read a column or relation the model keeps out of
 * its serialized form. This mirrors Eloquent's own `toArray()` rules: a
 * key listed in `$hidden` is never readable, and when `$visible` is
 * non-empty only the keys it lists are.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.13.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings;

use Illuminate\Database\Eloquent\Model;

final class ModelAttributeVisibility
{
	/**
	 * True when `$key` would appear in the model's serialized form, so a
	 * binding may read it.
	 *
	 * @since 1.13.1
	 *
	 * @param  Model   $model  Record the binding reads from.
	 * @param  string  $key    Attribute or relation name.
	 */
	public static function isReadable( Model $model, string $key ): bool
	{
		if ( in_array( $key, $model->getHidden(), true ) ) {
			return false;
		}

		$visible = $model->getVisible();

		return [] === $visible || in_array( $key, $visible, true );
	}
}
