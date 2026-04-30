<?php

/**
 * Gutenberg attachment adapter.
 *
 * Transforms an `artisanpack-ui/media-library` Media record (or any
 * structurally-compatible media record) into the attachment shape
 * Gutenberg core blocks expect: `{ id, url, alt, caption, mime, sizes,
 * media_type, width, height, filename }`.
 *
 * Used server-side whenever the editor needs a Gutenberg-shaped media
 * record without making a round-trip to the client — for example, when
 * hydrating the Featured Image panel's initial value from a host model,
 * or when a dynamic block render callback needs to emit an attachment
 * fragment for a block's `innerHTML`.
 *
 * The adapter accepts any object or array that exposes the media-library
 * field names (`id`, `url`, `mime_type`, `alt_text`, `caption`, `width`,
 * `height`, `file_name`, `metadata`, `is_image`, `is_video`, `is_audio`,
 * `is_document`). This keeps the visual-editor package from requiring
 * `artisanpack-ui/media-library` as a hard dependency: hosts using a
 * different library can pass a duck-typed record.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\MediaBridge
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\MediaBridge;

use Illuminate\Contracts\Support\Arrayable;

class GutenbergAttachmentAdapter
{
	/**
	 * Convert a single media record to the Gutenberg attachment shape.
	 *
	 * Null alt and caption collapse to empty strings so core blocks never
	 * render literal `"null"` text. Width, height, filename, and sizes are
	 * omitted when absent so the output JSON stays terse.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>|Arrayable<string, mixed>|object  $media  Media record.
	 *
	 * @return array<string, mixed> Gutenberg-shaped attachment record.
	 */
	public function toGutenberg( array|object $media ): array
	{
		$source = $this->normalize( $media );

		$result = [
			'id'      => (int) ( $source['id'] ?? 0 ),
			'url'     => (string) ( $source['url'] ?? '' ),
			'alt'     => $this->stringOrEmpty( $source['alt_text'] ?? null ),
			'caption' => $this->stringOrEmpty( $source['caption'] ?? null ),
			'mime'    => (string) ( $source['mime_type'] ?? '' ),
		];

		$mediaType = $this->inferMediaType( $source );
		if ( null !== $mediaType ) {
			$result['media_type'] = $mediaType;
		}

		if ( $this->isPositiveInt( $source['width'] ?? null ) ) {
			$result['width'] = (int) $source['width'];
		}

		if ( $this->isPositiveInt( $source['height'] ?? null ) ) {
			$result['height'] = (int) $source['height'];
		}

		$filename = $source['file_name'] ?? null;
		if ( is_string( $filename ) && '' !== $filename ) {
			$result['filename'] = $filename;
		}

		$sizes = $this->extractSizes( $source );
		if ( null !== $sizes ) {
			$result['sizes'] = $sizes;
		}

		return $result;
	}

	/**
	 * Convert a collection of media records to Gutenberg attachment shapes.
	 *
	 * @since 1.0.0
	 *
	 * @param  iterable<array<string, mixed>|Arrayable<string, mixed>|object>  $items  Media records.
	 *
	 * @return array<int, array<string, mixed>> Attachment array.
	 */
	public function toGutenbergList( iterable $items ): array
	{
		$out = [];
		foreach ( $items as $item ) {
			$out[] = $this->toGutenberg( $item );
		}
		return $out;
	}

	/**
	 * Convert a single media record to the WP REST attachment shape.
	 *
	 * Distinct from {@see self::toGutenberg()}: where that emits the
	 * compact shape Gutenberg's `MediaUpload` component round-trips
	 * (`{ id, url, alt, sizes, ... }`), this method emits the WP REST
	 * `/wp/v2/media/{id}` envelope (`{ id, source_url, alt_text,
	 * media_details: { width, height, sizes }, ... }`) used by blocks
	 * that resolve attachments through `getEntityRecord('postType',
	 * 'attachment', id)` — `core/post-featured-image`, `core/cover`'s
	 * featured-image option, and any other block that reads the media
	 * record from the core-data store.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>|Arrayable<string, mixed>|object  $media  Media record.
	 *
	 * @return array<string, mixed>
	 */
	public function toWpRestShape( array|object $media ): array
	{
		$source = $this->normalize( $media );

		$id = (int) ( $source['id'] ?? 0 );

		$mediaDetails = [];
		if ( $this->isPositiveInt( $source['width'] ?? null ) ) {
			$mediaDetails['width'] = (int) $source['width'];
		}
		if ( $this->isPositiveInt( $source['height'] ?? null ) ) {
			$mediaDetails['height'] = (int) $source['height'];
		}

		$sizes = $this->extractSizes( $source );
		if ( null !== $sizes ) {
			$mediaDetails['sizes'] = $this->sizesToWpShape( $sizes );
		}

		return [
			'id'            => $id,
			'source_url'    => (string) ( $source['url'] ?? '' ),
			'alt_text'      => $this->stringOrEmpty( $source['alt_text'] ?? null ),
			'caption'       => [ 'rendered' => $this->stringOrEmpty( $source['caption'] ?? null ) ],
			'title'         => [ 'rendered' => $this->stringOrEmpty( $source['title'] ?? null ) ],
			'mime_type'     => (string) ( $source['mime_type'] ?? '' ),
			'media_type'    => $this->inferMediaType( $source ) ?? 'file',
			'media_details' => $mediaDetails,
		];
	}

	/**
	 * Reshape Gutenberg-style sizes (`{ slug: { url, width, height } }`)
	 * into the WP REST media-details sizes shape (`{ slug: { source_url,
	 * width, height } }`). Keeps the V1 surface narrow — additional
	 * fields (`mime_type`, `file`) are V1.1+ if and when consumers need
	 * them.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, array<string, mixed>>  $sizes
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function sizesToWpShape( array $sizes ): array
	{
		$out = [];
		foreach ( $sizes as $slug => $size ) {
			if ( ! is_array( $size ) ) {
				continue;
			}
			$reshaped = [];
			if ( isset( $size['url'] ) && is_string( $size['url'] ) ) {
				$reshaped['source_url'] = $size['url'];
			}
			if ( isset( $size['width'] ) && is_int( $size['width'] ) ) {
				$reshaped['width'] = $size['width'];
			}
			if ( isset( $size['height'] ) && is_int( $size['height'] ) ) {
				$reshaped['height'] = $size['height'];
			}
			if ( [] !== $reshaped ) {
				$out[ (string) $slug ] = $reshaped;
			}
		}
		return $out;
	}

	/**
	 * Reduce any supported input into an associative array for uniform
	 * field access. Objects exposing `toArray()` route through that path so
	 * Eloquent models surface their appended accessors (including `url`).
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>|Arrayable<string, mixed>|object  $media  Input record.
	 *
	 * @return array<string, mixed>
	 */
	protected function normalize( array|object $media ): array
	{
		if ( is_array( $media ) ) {
			return $media;
		}

		if ( $media instanceof Arrayable ) {
			return $media->toArray();
		}

		if ( method_exists( $media, 'toArray' ) ) {
			$array = $media->toArray();
			if ( is_array( $array ) ) {
				return $array;
			}
		}

		return get_object_vars( $media );
	}

	/**
	 * Classify the record into one of Gutenberg's four media categories.
	 *
	 * The media-library model exposes `is_image`/`is_video`/`is_audio`/
	 * `is_document` booleans; prefer those when present, then fall back to
	 * the mime-type prefix so duck-typed records without the flags still
	 * classify correctly.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $source  Normalized media fields.
	 *
	 * @return string|null One of 'image', 'video', 'audio', 'file'; null when undetermined.
	 */
	protected function inferMediaType( array $source ): ?string
	{
		if ( true === ( $source['is_image'] ?? null ) ) {
			return 'image';
		}

		if ( true === ( $source['is_video'] ?? null ) ) {
			return 'video';
		}

		if ( true === ( $source['is_audio'] ?? null ) ) {
			return 'audio';
		}

		if ( true === ( $source['is_document'] ?? null ) ) {
			return 'file';
		}

		$mime = $source['mime_type'] ?? null;
		if ( ! is_string( $mime ) || '' === $mime ) {
			return null;
		}

		$prefix = strtolower( explode( '/', $mime, 2 )[0] );
		switch ( $prefix ) {
			case 'image':
				return 'image';
			case 'video':
				return 'video';
			case 'audio':
				return 'audio';
			case 'application':
			case 'text':
				return 'file';
			default:
				return null;
		}
	}

	/**
	 * Pull image sizes out of the record. Accepts three shapes the
	 * media-library can emit:
	 *
	 *  - Top-level `image_sizes` (the Media model's `getImageSizes()`
	 *    helper output: `[ size_name => url_string ]`).
	 *  - `metadata.sizes` as `[ size_name => url_string ]`.
	 *  - `metadata.sizes` as `[ size_name => [ url, width?, height? ] ]`
	 *    (matches the JS adapter's richer shape).
	 *
	 * Returns null when no sizes are available so callers can omit the
	 * key entirely.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $source  Normalized media fields.
	 *
	 * @return array<string, array<string, mixed>>|null
	 */
	protected function extractSizes( array $source ): ?array
	{
		$candidates = [];

		$topLevel = $source['image_sizes'] ?? null;
		if ( is_array( $topLevel ) ) {
			$candidates = $topLevel;
		} else {
			$metadata = $source['metadata'] ?? null;
			if ( is_array( $metadata ) && isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				$candidates = $metadata['sizes'];
			}
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		$out = [];
		foreach ( $candidates as $name => $value ) {
			if ( ! is_string( $name ) || '' === $name ) {
				continue;
			}

			if ( is_string( $value ) && '' !== $value ) {
				$out[ $name ] = [ 'url' => $value ];
				continue;
			}

			if ( ! is_array( $value ) || ! isset( $value['url'] ) || ! is_string( $value['url'] ) ) {
				continue;
			}

			$entry = [ 'url' => $value['url'] ];
			if ( $this->isPositiveInt( $value['width'] ?? null ) ) {
				$entry['width'] = (int) $value['width'];
			}
			if ( $this->isPositiveInt( $value['height'] ?? null ) ) {
				$entry['height'] = (int) $value['height'];
			}
			$out[ $name ] = $entry;
		}

		return empty( $out ) ? null : $out;
	}

	/**
	 * Coerce null or non-string values into an empty string. Used for
	 * attachment fields Gutenberg assumes are always strings.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $value
	 *
	 * @return string
	 */
	protected function stringOrEmpty( $value ): string
	{
		return is_string( $value ) ? $value : '';
	}

	/**
	 * True when `$value` is a positive integer (or a numeric string that
	 * parses to one). Guards against media-library's nullable width/height
	 * columns emitting `null` into the attachment shape.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	protected function isPositiveInt( $value ): bool
	{
		if ( is_int( $value ) ) {
			return 0 < $value;
		}

		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return 0 < (int) $value;
		}

		return false;
	}
}
