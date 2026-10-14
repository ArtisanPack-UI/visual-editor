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
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionMethod;
use ReflectionNamedType;

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

	/**
	 * True when reading `$key` through `getAttribute()` can't run an
	 * arbitrary model method (#850). Eloquent treats any public method
	 * named like the key as a relation and calls it, so a binding key
	 * such as `forceDelete` or `restore` would write to the database
	 * during a render. Stored attributes, accessors, loaded relations,
	 * and keys with no matching method are safe; a method is only
	 * allowed when {@see isRelationMethod()} accepts it.
	 *
	 * @since 1.13.1
	 *
	 * @param  Model   $model  Record the binding reads from.
	 * @param  string  $key    Attribute or relation name.
	 */
	public static function isSafeToRead( Model $model, string $key ): bool
	{
		if (
			array_key_exists( $key, $model->getAttributes() )
			|| $model->relationLoaded( $key )
			|| $model->hasGetMutator( $key )
			|| $model->hasAttributeMutator( $key )
			|| ! method_exists( $model, $key )
		) {
			return true;
		}

		return self::isRelationMethod( $model, $key );
	}

	/**
	 * True when `$name` is a relation method a binding may call: public,
	 * no required parameters, defined below the base Eloquent `Model`,
	 * and declaring a native `Relation` return type. Relations without a
	 * return type aren't accepted, since nothing short of calling them
	 * tells a relation apart from a method with side effects.
	 *
	 * @since 1.13.1
	 *
	 * @param  Model   $model  Record the relation would load on.
	 * @param  string  $name   Method name.
	 */
	public static function isRelationMethod( Model $model, string $name ): bool
	{
		if ( '' === $name || ! method_exists( $model, $name ) || method_exists( Model::class, $name ) ) {
			return false;
		}

		$method = new ReflectionMethod( $model, $name );

		if ( ! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0 ) {
			return false;
		}

		$type = $method->getReturnType();

		return $type instanceof ReflectionNamedType
			&& ! $type->isBuiltin()
			&& is_a( $type->getName(), Relation::class, true );
	}
}
