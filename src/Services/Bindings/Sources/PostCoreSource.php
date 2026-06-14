<?php

/**
 * Binding source that reads a core column or accessor off the parent model.
 *
 * Targets the conventional "post" surface — title, excerpt, slug, status,
 * created_at / updated_at, plus author shortcuts. The set of recognized
 * keys is intentionally narrow so the inspector picker exposes a stable
 * list across content types; arbitrary column lookups belong in
 * {@see RelationSource}.
 *
 * Each key is resolved via the model's attribute accessor (so casts and
 * mutators apply), with one fallback for `author.name` so a host that
 * stores the author behind a `user` relation does not have to register a
 * custom driver to surface the byline.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings\Sources;

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostCoreSource implements BlockBindingSource
{
	/**
	 * Whitelist of keys this source resolves, paired with the inspector
	 * picker's labels and types. Hosts that need additional columns can
	 * register their own source — keeping the whitelist tight prevents
	 * the inspector from advertising fields that may not exist on every
	 * model.
	 *
	 * @var array<int, array{ key: string, label: string, type: string }>
	 */
	public const SUPPORTED_FIELDS = [
		[ 'key' => 'title',          'label' => 'Title',          'type' => 'string' ],
		[ 'key' => 'excerpt',        'label' => 'Excerpt',        'type' => 'string' ],
		[ 'key' => 'slug',           'label' => 'Slug',           'type' => 'string' ],
		[ 'key' => 'status',         'label' => 'Status',         'type' => 'string' ],
		[ 'key' => 'created_at',     'label' => 'Created at',     'type' => 'datetime' ],
		[ 'key' => 'updated_at',     'label' => 'Updated at',     'type' => 'datetime' ],
		[ 'key' => 'published_at',   'label' => 'Published at',   'type' => 'datetime' ],
		[ 'key' => 'featured_image', 'label' => 'Featured image', 'type' => 'image' ],
		[ 'key' => 'author_name',    'label' => 'Author name',    'type' => 'string' ],
	];

	public function name(): string
	{
		return 'post_core';
	}

	public function resolve( BindingContext $context, array $args ): mixed
	{
		$key = is_string( $args['key'] ?? null ) ? $args['key'] : '';

		if ( '' === $key ) {
			return null;
		}

		// Guard against arbitrary column reads: keep this source's
		// surface area aligned with the inspector picker's catalog so
		// a binding that bypasses the UI cannot exfiltrate, say, a
		// password hash or a soft-delete timestamp.
		if ( ! $this->isSupported( $key ) ) {
			return null;
		}

		$draft = $context->draftValue( $key );

		if ( null !== $draft && '' !== $draft ) {
			return $draft;
		}

		$model = $context->model();

		if ( ! $model instanceof Model ) {
			return null;
		}

		if ( 'author_name' === $key ) {
			return $this->resolveAuthorName( $model );
		}

		// Defer to the model's accessor so casts and mutators apply.
		$value = $model->getAttribute( $key );

		return $value;
	}

	public function eagerLoadRelations( array $bindingArgs ): array
	{
		foreach ( $bindingArgs as $args ) {
			$key = $args['key'] ?? null;

			if ( 'author_name' === $key ) {
				return [ 'author' ];
			}
		}

		return [];
	}

	public function availableFields( string $resource, ?string $modelClass = null ): array
	{
		return self::SUPPORTED_FIELDS;
	}

	/**
	 * True when `$key` is one of the source's declared `SUPPORTED_FIELDS`.
	 *
	 * @since 1.1.0
	 */
	protected function isSupported( string $key ): bool
	{
		foreach ( self::SUPPORTED_FIELDS as $field ) {
			if ( ( $field['key'] ?? null ) === $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the author's display name with a small set of fallbacks so
	 * the binding works against either a dedicated author relation or a
	 * direct `author_name` column.
	 *
	 * @since 1.1.0
	 */
	protected function resolveAuthorName( Model $model ): mixed
	{
		$direct = $model->getAttribute( 'author_name' );

		if ( null !== $direct && '' !== $direct ) {
			return $direct;
		}

		if ( method_exists( $model, 'author' ) && $model->author() instanceof BelongsTo ) {
			$author = $model->getAttribute( 'author' );

			if ( $author instanceof Model ) {
				$name = $author->getAttribute( 'name' );

				if ( null !== $name && '' !== $name ) {
					return $name;
				}
			}
		}

		return null;
	}
}
