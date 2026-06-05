<?php

/**
 * Abstract WP-shape entity resource.
 *
 * Shapes any `HasBlockContent` Eloquent model into the WordPress
 * `/wp/v2/{post,page}` envelope so the editor's `core-data` shim can
 * round-trip records via `useEntityRecord` / `saveEntityRecord` without
 * round-tripping through cms-framework's flat public REST surface.
 *
 * Concrete subclasses fix the `type` discriminator and may layer on
 * post-type-specific fields (categories/tags for posts;
 * parent/menu_order/template for pages) via {@see self::extraFields()}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @property Model $resource
 */
abstract class WpEntityResource extends JsonResource
{
	/**
	 * Returns the WP-shape `type` discriminator for this entity (e.g.
	 * `post`, `page`).
	 *
	 * @since 1.0.0
	 */
	abstract protected function type(): string;

	/**
	 * Transforms the model into the WP-shape envelope.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array
	{
		/** @var Model $model */
		$model = $this->resource;

		$title   = $this->stringField( $model, 'title' );
		$excerpt = $this->stringField( $model, 'excerpt' );

		$base = [
			'id'             => $model->getKey(),
			'slug'           => $this->stringField( $model, 'slug' ),
			'title'          => [ 'rendered' => $title, 'raw' => $title ],
			'excerpt'        => [ 'rendered' => $excerpt, 'raw' => $excerpt ],
			'content'        => [
				// `raw` would normally be the serialized HTML form a
				// host could render without parsing the block tree, but
				// HasBlockContent stores only the parsed block array —
				// callers that need HTML render the tree through the
				// matching renderer package. Kept on the envelope so
				// shim selectors that probe `content.raw` don't see
				// `undefined`.
				'raw'      => '',
				// `rendered` mirrors the front-end `_resolvedContent`
				// stamping (#526). Hosts whose model exposes a
				// `rendered_content` accessor (cms-framework's
				// HasBlockContent trait provides one) get the rendered
				// HTML on the editor-preview envelope; everything else
				// sees an empty string and falls back to the block tree.
				'rendered' => $this->stringField( $model, 'rendered_content' ),
				'blocks'   => $this->blocks( $model ),
			],
			'status'         => $this->stringField( $model, 'status', 'publish' ),
			'type'           => $this->type(),
			'author'         => $this->intField( $model, [ 'author_id' ] ),
			'featured_media' => $this->intField( $model, [ 'featured_media', 'featured_image_id' ] ),
			'date'           => $this->date( $model ),
			// Editor-preview envelope (issue #483). The WP REST shape
			// above exposes author/featured-media as ids only; the
			// `_preview` block carries the same resolved name/url/etc.
			// data the server-side PostResolver stamps onto front-end
			// `_resolved*` attributes so the editor canvas can preview
			// `artisanpack/post-*` blocks inside a query loop without a
			// follow-up `@wordpress/core-data` fetch.
			'_preview'       => $this->previewEnvelope( $model ),
		];

		return array_merge( $base, $this->extraFields( $model ) );
	}

	/**
	 * Editor-canvas preview envelope used by `artisanpack/query`'s
	 * inner `post-*` blocks (#483). Mirrors the `_resolved*` keys the
	 * server-side `PostResolver` stamps for the front-end renderers so
	 * the editor preview and the public render show the same author /
	 * date / featured image data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function previewEnvelope( Model $model ): array
	{
		return [
			'dateFormatted'   => $this->formattedDate( $model ),
			'author'          => $this->authorEnvelope( $model ),
			'featuredImage'   => $this->featuredImageEnvelope( $model ),
			// Post navigation / metadata family (#520). `terms` powers
			// the `artisanpack/post-terms` editor preview; `adjacent`
			// powers `artisanpack/post-navigation-link`; `term` carries
			// the post's primary-term description for the
			// `artisanpack/term-description` preview when the block is
			// placed inside a query loop.
			'terms'           => $this->termsEnvelope( $model ),
			'adjacent'        => $this->adjacentEnvelope( $model ),
			'term'            => $this->primaryTermEnvelope( $model ),
		];
	}

	/**
	 * Resolve the post's taxonomy terms into the preview envelope shape
	 * used by the `artisanpack/post-terms` editor preview. Map keyed by
	 * taxonomy slug, each value an array of `{name, slug, url}` records.
	 * Returns an empty map when the model does not expose any taxonomy
	 * relations.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	protected function termsEnvelope( Model $model ): array
	{
		$out = [];

		// Mirror the defensive lazy-load pattern below (categories/tags
		// branch and `primaryTermEnvelope()`): a host whose `terms`
		// relation throws should degrade to an empty envelope rather
		// than crash the entire WP-shape response.
		try {
			$general = $model->terms ?? null;
		} catch ( Throwable ) {
			$general = null;
		}

		if ( is_iterable( $general ) ) {
			foreach ( $general as $term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}

				$taxonomy = isset( $term->taxonomy ) && is_scalar( $term->taxonomy )
					? (string) $term->taxonomy
					: '';

				if ( '' === $taxonomy ) {
					continue;
				}

				$out[ $taxonomy ] ??= [];
				$out[ $taxonomy ][] = $this->normaliseTerm( $term );
			}
		}

		foreach ( [ 'categories' => 'category', 'tags' => 'post_tag' ] as $relation => $taxonomy ) {
			try {
				$collection = $model->{$relation} ?? null;
			} catch ( Throwable ) {
				continue;
			}

			if ( ! is_iterable( $collection ) ) {
				continue;
			}

			$out[ $taxonomy ] ??= [];

			foreach ( $collection as $term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}

				$out[ $taxonomy ][] = $this->normaliseTerm( $term );
			}
		}

		return $out;
	}

	/**
	 * Resolve the post's adjacent (previous + next) post links into the
	 * preview envelope shape used by the
	 * `artisanpack/post-navigation-link` editor preview. Returns
	 * `{previous: null, next: null}` when the model does not expose any
	 * adjacency relation.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>|null>
	 */
	protected function adjacentEnvelope( Model $model ): array
	{
		return [
			'previous' => $this->adjacentEntry( $model, 'previous' ),
			'next'     => $this->adjacentEntry( $model, 'next' ),
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	protected function adjacentEntry( Model $model, string $direction ): ?array
	{
		$candidates = 'previous' === $direction
			? [ 'previous_post', 'previousPost', 'prev_post', 'prevPost', 'prev', 'previous' ]
			: [ 'next_post', 'nextPost', 'next' ];

		foreach ( $candidates as $key ) {
			try {
				$value = $model->{$key} ?? null;
			} catch ( Throwable ) {
				continue;
			}

			if ( ! is_object( $value ) ) {
				continue;
			}

			$title = isset( $value->title ) && is_scalar( $value->title ) ? (string) $value->title : '';
			$url   = '';
			if ( isset( $value->permalink ) && is_scalar( $value->permalink ) ) {
				$url = (string) $value->permalink;
			} elseif ( isset( $value->url ) && is_scalar( $value->url ) ) {
				$url = (string) $value->url;
			}

			if ( '' === $title && '' === $url ) {
				continue;
			}

			return [
				'title' => $title,
				'url'   => $url,
			];
		}

		return null;
	}

	/**
	 * Resolve the post's primary term into the preview envelope shape
	 * used by the `artisanpack/term-description` editor preview. Returns
	 * null when the model does not expose any terms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>|null
	 */
	protected function primaryTermEnvelope( Model $model ): ?array
	{
		foreach ( [ 'primary_term', 'primaryTerm' ] as $key ) {
			try {
				$value = $model->{$key} ?? null;
			} catch ( Throwable ) {
				continue;
			}

			if ( is_object( $value ) ) {
				return $this->normaliseTermWithDescription( $value );
			}
		}

		try {
			$general = $model->terms ?? null;
		} catch ( Throwable ) {
			$general = null;
		}

		if ( is_iterable( $general ) ) {
			foreach ( $general as $term ) {
				if ( is_object( $term ) ) {
					return $this->normaliseTermWithDescription( $term );
				}
			}
		}

		foreach ( [ 'categories', 'tags' ] as $relation ) {
			try {
				$collection = $model->{$relation} ?? null;
			} catch ( Throwable ) {
				continue;
			}

			if ( ! is_iterable( $collection ) ) {
				continue;
			}

			foreach ( $collection as $term ) {
				if ( is_object( $term ) ) {
					return $this->normaliseTermWithDescription( $term );
				}
			}
		}

		return null;
	}

	/**
	 * @return array<string, string>
	 */
	protected function normaliseTerm( object $term ): array
	{
		// `url` is preferred for hosts that expose a generic URL accessor,
		// but `permalink` is the cms-framework convention (PostCategory /
		// PostTag use `getPermalinkAttribute()`). Fall through so the
		// editor-preview link target populates regardless.
		$url = '';
		if ( isset( $term->url ) && is_scalar( $term->url ) ) {
			$url = (string) $term->url;
		} elseif ( isset( $term->permalink ) && is_scalar( $term->permalink ) ) {
			$url = (string) $term->permalink;
		}

		return [
			'name' => isset( $term->name ) && is_scalar( $term->name ) ? (string) $term->name : '',
			'slug' => isset( $term->slug ) && is_scalar( $term->slug ) ? (string) $term->slug : '',
			'url'  => $url,
		];
	}

	/**
	 * @return array<string, string>
	 */
	protected function normaliseTermWithDescription( object $term ): array
	{
		return array_merge( $this->normaliseTerm( $term ), [
			'description' => isset( $term->description ) && is_scalar( $term->description )
				? (string) $term->description
				: '',
		] );
	}

	/**
	 * Resolve the post's canonical date and format it for display.
	 * Matches `PostResolver::resolveDate()`'s `F j, Y` format so the
	 * editor preview and the server-rendered front end agree.
	 *
	 * @since 1.0.0
	 */
	protected function formattedDate( Model $model ): ?string
	{
		$iso = $this->date( $model );

		if ( null === $iso ) {
			return null;
		}

		try {
			return Carbon::parse( $iso )->translatedFormat( 'F j, Y' );
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * Resolve the post's author into the preview envelope shape used
	 * by `mapWpEntityToPost()` on the client. Returns null when the
	 * model does not expose an `author` relation/property.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>|null
	 */
	protected function authorEnvelope( Model $model ): ?array
	{
		$author = $model->author ?? null;

		if ( null === $author ) {
			return null;
		}

		return [
			'name'      => isset( $author->name ) && is_scalar( $author->name ) ? (string) $author->name : '',
			'bio'       => isset( $author->bio ) && is_scalar( $author->bio )
				? (string) $author->bio
				: ( isset( $author->description ) && is_scalar( $author->description ) ? (string) $author->description : '' ),
			'url'       => isset( $author->url ) && is_scalar( $author->url )
				? (string) $author->url
				: ( isset( $author->website ) && is_scalar( $author->website ) ? (string) $author->website : '' ),
			'avatarUrl' => isset( $author->avatar_url ) && is_scalar( $author->avatar_url ) ? (string) $author->avatar_url : '',
		];
	}

	/**
	 * Resolve the featured image into the preview envelope shape used
	 * by `mapWpEntityToPost()` on the client. Uses the media-library
	 * helpers (`apGetMediaUrl()` / `apGetMedia()`) when available so
	 * a visual-editor install without media-library degrades to null
	 * instead of erroring.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>|null
	 */
	protected function featuredImageEnvelope( Model $model ): ?array
	{
		$mediaId = $this->intField( $model, [ 'featured_media', 'featured_image_id' ] );

		if ( null === $mediaId ) {
			return null;
		}

		$url    = '';
		$alt    = '';
		$width  = 0;
		$height = 0;

		// `function_exists()` only guards against missing helpers — a
		// host implementation that throws would otherwise bubble out
		// through `toArray()` and fail the entire response. Mirrors
		// the defensive pattern in `relationIds()` below.
		if ( function_exists( 'apGetMediaUrl' ) ) {
			try {
				$resolved = apGetMediaUrl( $mediaId, 'full' );
				$url      = is_string( $resolved ) ? $resolved : '';
			} catch ( Throwable ) {
				$url = '';
			}
		}

		if ( function_exists( 'apGetMedia' ) ) {
			try {
				$media = apGetMedia( $mediaId );

				if ( is_object( $media ) ) {
					$alt    = isset( $media->alt_text ) && is_scalar( $media->alt_text ) ? (string) $media->alt_text : '';
					$width  = isset( $media->width ) && is_numeric( $media->width ) ? (int) $media->width : 0;
					$height = isset( $media->height ) && is_numeric( $media->height ) ? (int) $media->height : 0;
				}
			} catch ( Throwable ) {
				$alt    = '';
				$width  = 0;
				$height = 0;
			}
		}

		if ( '' === $url ) {
			return null;
		}

		return [
			'url'    => $url,
			'alt'    => $alt,
			'width'  => $width,
			'height' => $height,
		];
	}

	/**
	 * Hook for subclasses to layer on post-type-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function extraFields( Model $model ): array
	{
		return [];
	}

	/**
	 * Reads the model's saved block tree via the `HasBlockContent` trait
	 * helper, falling back to an empty array when the helper is missing
	 * (defensive — `WpEntityController::resourceClass()` already
	 * validates the trait at request time).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function blocks( Model $model ): array
	{
		if ( method_exists( $model, 'getBlockContent' ) ) {
			$value = $model->getBlockContent();

			return is_array( $value ) ? $value : [];
		}

		return [];
	}

	/**
	 * Coerces an arbitrary attribute to a string, returning `$default`
	 * when the value is null or non-scalar.
	 *
	 * @since 1.0.0
	 */
	protected function stringField( Model $model, string $key, string $default = '' ): string
	{
		$value = $model->getAttribute( $key );

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( null === $value ) {
			return $default;
		}

		return is_scalar( $value ) ? (string) $value : $default;
	}

	/**
	 * Returns the first non-null integer-valued attribute among
	 * `$candidates`, or null when no candidate is present. Lets the
	 * resource adapt to host models that name their featured-image
	 * column differently (`featured_media` in WP, `featured_image_id`
	 * in cms-framework).
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>  $candidates
	 */
	protected function intField( Model $model, array $candidates ): ?int
	{
		foreach ( $candidates as $key ) {
			$value = $model->getAttribute( $key );

			if ( is_int( $value ) ) {
				return $value;
			}

			if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
				return (int) $value;
			}
		}

		return null;
	}

	/**
	 * Resolves the canonical date for the record. Prefers
	 * `published_at`, falling back to `created_at`. Emits an ISO 8601
	 * string so the shim's date selectors match the WP REST shape.
	 *
	 * @since 1.0.0
	 */
	protected function date( Model $model ): ?string
	{
		$candidates = [ 'published_at', 'created_at' ];

		foreach ( $candidates as $key ) {
			$value = $model->getAttribute( $key );

			if ( null === $value ) {
				continue;
			}

			if ( is_object( $value ) && method_exists( $value, 'toIso8601String' ) ) {
				return $value->toIso8601String();
			}

			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Plucks ids from a HasMany / BelongsToMany relation, returning an
	 * empty array when the relation isn't defined on the model. Used by
	 * `PostResource` for categories/tags and `PageResource` for any
	 * relation-shaped extra.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, int>
	 */
	protected function relationIds( Model $model, string $relation ): array
	{
		if ( ! method_exists( $model, $relation ) ) {
			return [];
		}

		try {
			$collection = $model->{$relation};
		} catch ( Throwable ) {
			return [];
		}

		if ( ! is_object( $collection ) || ! method_exists( $collection, 'pluck' ) ) {
			return [];
		}

		// Database drivers return primary keys as either int or numeric
		// string depending on the column type and PDO config; filter to
		// numeric values then cast each one explicitly so the @return
		// `array<int, int>` annotation stays honest.
		return array_values( array_map(
			'intval',
			array_filter(
				$collection->pluck( 'id' )->all(),
				static fn ( $id ): bool => is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) )
			)
		) );
	}
}
