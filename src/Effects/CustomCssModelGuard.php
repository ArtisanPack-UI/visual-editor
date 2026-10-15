<?php

/**
 * Model-level Custom CSS guard.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Effects;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Traversable;

/**
 * Runs {@see CustomCssGuard} on every Eloquent save of a block-content
 * column, so Custom CSS can't be added through a write path that skips
 * visual-editor's own controllers (H-2), such as cms-framework's Site
 * Editor API or a host's own endpoint.
 *
 * Every model that uses {@see \ArtisanPackUI\VisualEditor\Concerns\HasBlockContent}
 * is covered automatically, as is the snippet model. Other models are
 * covered with {@see self::watch()}.
 *
 * On save, when the column is dirty and the current user lacks
 * {@see BlockCssAccess::ABILITY}, a block keeps its Custom CSS only when
 * the same (normalised) value is already stored on the record, or a
 * {@see CustomCssGuard::filter()} call earlier in the request vouched for
 * it. That second source keeps the file→DB upserts of theme templates
 * working: the controller passes the theme file's blocks as the stored
 * tree, and the new row keeps the CSS the theme ships.
 *
 * The guard is skipped when there is no authenticated user and the app
 * runs in the console, so seeders, imports and queued jobs keep working,
 * and inside {@see self::trusted()}. It only sees Eloquent model events, so
 * mass updates through the query builder are not covered.
 *
 * Bound as a scoped instance, so approvals never outlive the request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
class CustomCssModelGuard
{
	/**
	 * Normalised Custom CSS values vouched for earlier in the request.
	 *
	 * @since 1.15.0
	 *
	 * @var array<string, bool>
	 */
	protected array $approved = [];

	/**
	 * How many {@see self::trusted()} calls are running.
	 *
	 * @since 1.15.0
	 */
	protected int $trustedDepth = 0;

	/**
	 * Guard a model's block column on every save.
	 *
	 * @since 1.15.0
	 *
	 * @param  class-string<Model>                  $modelClass  The model to watch.
	 * @param  Closure(Model): string|string  $column      The block column, or a callback returning it.
	 */
	public static function watch( string $modelClass, string|Closure $column ): void
	{
		$modelClass::saving( static function ( Model $model ) use ( $column ): void {
			app( self::class )->guard( $model, $column instanceof Closure ? $column( $model ) : $column );
		} );
	}

	/**
	 * Strip Custom CSS the current user may not add from a model's
	 * block column. Called from the model's `saving` event.
	 *
	 * The column may hold a cast array, an `Arrayable` (collection casts)
	 * or a JSON string; a JSON string is written back as a JSON string.
	 * The column is only rewritten when something was stripped.
	 *
	 * @since 1.15.0
	 *
	 * @param  Model   $model   The model being saved.
	 * @param  string  $column  Its block column.
	 */
	public function guard( Model $model, string $column ): void
	{
		if ( $this->trustedDepth > 0 || ! $model->isDirty( $column ) ) {
			return;
		}

		if ( null === Auth::user() && app()->runningInConsole() ) {
			return;
		}

		$value  = $model->getAttribute( $column );
		$blocks = $this->decode( $value );

		if ( ! is_array( $blocks ) ) {
			return;
		}

		$original = $model->exists ? $this->decode( $model->getOriginal( $column ) ) : null;
		$filtered = app( CustomCssGuard::class )->filterAgainst( $blocks, $original, array_keys( $this->approved ) );

		if ( $filtered === $blocks ) {
			return;
		}

		$model->setAttribute( $column, is_string( $value ) ? json_encode( $filtered ) : $filtered );
	}

	/**
	 * Vouch for normalised Custom CSS values for the rest of the request.
	 *
	 * {@see CustomCssGuard::filter()} calls this with the values of the
	 * stored tree it was given.
	 *
	 * @since 1.15.0
	 *
	 * @param  iterable<string>  $values  Normalised Custom CSS values.
	 */
	public function approve( iterable $values ): void
	{
		foreach ( $values as $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$this->approved[ $value ] = true;
			}
		}
	}

	/**
	 * Run a callback with the model guard switched off, for trusted
	 * server-side writes made while a user is logged in, such as cloning a
	 * theme pattern into the database or importing a theme.
	 *
	 * @since 1.15.0
	 *
	 * @template TReturn
	 *
	 * @param  callable(): TReturn  $callback  The trusted write.
	 *
	 * @return TReturn The callback's return value.
	 */
	public function trusted( callable $callback ): mixed
	{
		$this->trustedDepth++;

		try {
			return $callback();
		} finally {
			$this->trustedDepth--;
		}
	}

	/**
	 * A column value as a block array, or the value unchanged when it
	 * can't be one.
	 *
	 * @since 1.15.0
	 *
	 * @param  mixed  $value  A cast column value or a JSON string.
	 */
	protected function decode( mixed $value ): mixed
	{
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			return is_array( $decoded ) ? $decoded : null;
		}

		if ( $value instanceof Arrayable ) {
			return $value->toArray();
		}

		if ( $value instanceof Traversable ) {
			return iterator_to_array( $value );
		}

		return $value;
	}
}
