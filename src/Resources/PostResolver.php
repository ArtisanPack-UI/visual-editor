<?php

/**
 * PostResolver — stamps `_resolved*` attributes onto `core/post-*`
 * blocks against a single post record.
 *
 * Used by {@see QueryInliner} when expanding a `core/query` block: each
 * inner `core/post-template` instance gets walked once per result, and
 * every `core/post-*` block inside it gets the corresponding `_resolved*`
 * keys stamped from the current post. The block partials/components
 * already read those keys, so the renderer-side code path stays unchanged
 * — the resolver is the only piece that has to know about the underlying
 * model shape.
 *
 * The resolver is tolerant: any field the underlying model does not
 * expose is left empty, and the existing block partials emit Gutenberg-
 * shaped placeholder markup so the rendered tree still has the right
 * structure.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

use Illuminate\Support\Carbon;

class PostResolver
{
	/**
	 * Block names this resolver knows how to stamp. Blocks not in this
	 * list are returned untouched.
	 *
	 * @var array<int, string>
	 */
	protected const SUPPORTED_BLOCKS = [
		'core/post-title',
		'core/post-content',
		'core/post-excerpt',
		'core/post-date',
		'core/post-author',
		'core/post-featured-image',
		// Author-family forks (#518) — recommended replacements for the
		// deprecated `core/post-author`. Resolved through the same
		// `resolveAuthor()` branch since all three derive from the same
		// post-author relation.
		'core/post-author-name',
		'core/post-author-biography',
		'core/avatar',
		// Phase I5 forks (#413) — same `_resolved*` contract, new namespace.
		'artisanpack/post-title',
		'artisanpack/post-content',
		'artisanpack/post-excerpt',
		'artisanpack/post-date',
		'artisanpack/post-author',
		'artisanpack/post-featured-image',
		// Author-family forks (#518).
		'artisanpack/post-author-name',
		'artisanpack/post-author-biography',
		'artisanpack/avatar',
	];

	/**
	 * Recursively walk a block subtree and stamp every supported
	 * `core/post-*` block against the given post.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function stampTree( array $tree, object $post ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out[] = $this->stampBlock( $block, $post );
		}

		return $out;
	}

	/**
	 * Stamp a single block (and its inner blocks) against the given post.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	public function stampBlock( array $block, object $post ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		if ( in_array( $name, self::SUPPORTED_BLOCKS, true ) ) {
			$attributes = array_merge( $this->resolveAttributesFor( $name, $post ), $attributes );
		}

		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $this->stampTree( $block['innerBlocks'], $post )
			: [];

		return array_merge( $block, [
			'attributes'  => $attributes,
			'innerBlocks' => $inner,
		] );
	}

	/**
	 * Returns the stamped `_resolved*` attributes for a single block /
	 * post pair. Returned as a "defaults" map — pre-existing
	 * `_resolved*` keys on the block win on merge so a host that has
	 * already resolved values upstream keeps full control.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveAttributesFor( string $name, object $post ): array
	{
		// Match on the unqualified block slug so both the `core/*` blocks
		// and their Phase I5 `artisanpack/*` forks (#413) resolve through
		// the same branch.
		$slug = str_contains( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;

		return match ( $slug ) {
			'post-title'              => $this->resolveTitle( $post ),
			'post-content'            => $this->resolveContent( $post ),
			'post-excerpt'            => $this->resolveExcerpt( $post ),
			'post-date'               => $this->resolveDate( $post ),
			'post-author',
			'post-author-name',
			'post-author-biography',
			'avatar'                  => $this->resolveAuthor( $post ),
			'post-featured-image'     => $this->resolveFeaturedImage( $post ),
			default                   => [],
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveTitle( object $post ): array
	{
		return [
			'_resolvedTitle'     => (string) ( $post->title ?? '' ),
			'_resolvedPermalink' => $this->permalink( $post ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveContent( object $post ): array
	{
		// `content` may be a saved block tree (HasBlockContent stores
		// it as JSON), a pre-rendered HTML string, or null. The block
		// partial just needs a string; pass HTML through, otherwise
		// leave empty so the partial emits its placeholder shell.
		$content = $post->content ?? null;

		return [
			'_resolvedContent' => is_string( $content ) ? $content : '',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveExcerpt( object $post ): array
	{
		return [
			'_resolvedExcerpt'   => (string) ( $post->excerpt ?? '' ),
			'_resolvedPermalink' => $this->permalink( $post ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveDate( object $post ): array
	{
		$published = $this->toCarbon( $post->published_at ?? null );
		$modified  = $this->toCarbon( $post->updated_at ?? null );

		return [
			'_resolvedDate'                  => null === $published ? '' : $published->toIso8601String(),
			'_resolvedDateFormatted'         => null === $published ? '' : $published->translatedFormat( 'F j, Y' ),
			'_resolvedModifiedDate'          => null === $modified ? '' : $modified->toIso8601String(),
			'_resolvedModifiedDateFormatted' => null === $modified ? '' : $modified->translatedFormat( 'F j, Y' ),
			'_resolvedPermalink'             => $this->permalink( $post ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveAuthor( object $post ): array
	{
		$author = $post->author ?? null;

		return [
			'_resolvedAuthorName'   => null === $author ? '' : (string) ( $author->name ?? '' ),
			'_resolvedAuthorBio'    => null === $author ? '' : (string) ( $author->bio ?? $author->description ?? '' ),
			'_resolvedAuthorUrl'    => null === $author ? '' : (string) ( $author->url ?? $author->website ?? '' ),
			'_resolvedAuthorAvatar' => null === $author ? '' : (string) ( $author->avatar_url ?? '' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveFeaturedImage( object $post ): array
	{
		$mediaId = $post->featured_image_id ?? $post->featured_media ?? null;

		$url    = '';
		$alt    = '';
		$width  = 0;
		$height = 0;

		if ( null !== $mediaId && function_exists( 'apGetMediaUrl' ) ) {
			$resolved = apGetMediaUrl( (int) $mediaId, 'full' );
			$url      = is_string( $resolved ) ? $resolved : '';
		}

		// `apGetMedia()` returns the full media record; fall through
		// gracefully when the helper is unavailable (visual-editor
		// installed without `media-library`).
		if ( null !== $mediaId && function_exists( 'apGetMedia' ) ) {
			$media = apGetMedia( (int) $mediaId );

			if ( is_object( $media ) ) {
				$alt    = (string) ( $media->alt_text ?? '' );
				$width  = (int) ( $media->width ?? 0 );
				$height = (int) ( $media->height ?? 0 );
			}
		}

		return [
			'_resolvedImageUrl'    => $url,
			'_resolvedImageAlt'    => $alt,
			'_resolvedImageWidth'  => $width,
			'_resolvedImageHeight' => $height,
			'_resolvedPermalink'   => $this->permalink( $post ),
		];
	}

	protected function permalink( object $post ): string
	{
		// Many `HasBlockContent` models expose `permalink` as an Eloquent
		// attribute accessor. Fall back to an empty string when the
		// model does not provide one — block partials handle the empty
		// case by rendering an unlinked element.
		$permalink = $post->permalink ?? null;

		return is_string( $permalink ) ? $permalink : '';
	}

	protected function toCarbon( mixed $value ): ?Carbon
	{
		if ( $value instanceof Carbon ) {
			return $value;
		}

		if ( null === $value || '' === $value ) {
			return null;
		}

		try {
			return Carbon::parse( $value );
		} catch ( \Throwable ) {
			return null;
		}
	}
}
