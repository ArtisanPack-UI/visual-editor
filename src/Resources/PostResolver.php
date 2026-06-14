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
		// Comments-family forks (#519) Pass 2 — post-level comment metadata.
		// The per-comment cluster (`comment-*` inside `comment-template`)
		// resolves through {@see CommentResolver}; these post-level blocks
		// resolve from the parent post's comment counters / URL the same
		// way `post-author-*` resolves from `$post->author`.
		'core/post-comments-count',
		'core/post-comments-link',
		'core/post-comments-title',
		'core/post-comments-form',
		'artisanpack/post-comments-count',
		'artisanpack/post-comments-link',
		'artisanpack/post-comments-title',
		'artisanpack/post-comments-form',
		// Site-chrome comments-number block (#500). Reuses the
		// existing `_resolvedCommentCount` stamp — the renderer
		// combines it with the saved singular / plural labels.
		'artisanpack/comments-number',
		// Post navigation / metadata family forks (#520) — same `_resolved*`
		// contract, new namespace. `term-description` is archive-context
		// only, but we stamp the post's primary-term description on it so
		// hosts that drop the block inside a query loop still get content.
		'core/post-navigation-link',
		'core/post-terms',
		'core/read-more',
		'core/term-description',
		'artisanpack/post-navigation-link',
		'artisanpack/post-terms',
		'artisanpack/read-more',
		'artisanpack/term-description',
		// Single-post content cluster (#501). Both blocks render against
		// the host post: `author-social-icons` resolves the post author's
		// stored profile URLs; `social-share-content` builds per-platform
		// share URLs from the post's permalink, title, and featured image.
		'artisanpack/author-social-icons',
		'artisanpack/social-share-content',
	];

	/**
	 * Container blocks that swap the post context of their inner blocks
	 * to the adjacent post in the same post type (#499). The block
	 * itself gets a `_resolvedHasAdjacent` flag so the renderer can
	 * decide whether to emit a wrapper or nothing; the inner tree is
	 * recursively stamped against the resolved adjacent post so every
	 * `post-*` child renders the neighbor's data.
	 *
	 * @var array<int, string>
	 */
	protected const ADJACENT_POST_CONTAINERS = [
		'artisanpack/next-post'     => 'next',
		'artisanpack/previous-post' => 'previous',
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

		// Adjacent-post container blocks (#499): swap the post context
		// for the inner tree to the resolved adjacent post so every
		// `post-*` child renders the neighbor's data. The block itself
		// gets a `_resolvedHasAdjacent` flag so the renderer can emit a
		// no-op shell when no neighbor exists.
		if ( array_key_exists( $name, self::ADJACENT_POST_CONTAINERS ) ) {
			$direction = self::ADJACENT_POST_CONTAINERS[ $name ];
			$adjacent  = $this->adjacentPost( $post, $direction );

			$rawInner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
				? $block['innerBlocks']
				: [];

			$inner = null === $adjacent
				? $rawInner
				: $this->stampTree( $rawInner, $adjacent );

			$attributes = array_merge(
				[ '_resolvedHasAdjacent' => null !== $adjacent ],
				$attributes
			);

			return array_merge( $block, [
				'attributes'  => $attributes,
				'innerBlocks' => $inner,
			] );
		}

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
			'post-comments-form'      => $this->resolveCommentsForm( $post ),
			'post-comments-count',
			'comments-number'         => $this->resolveCommentsCount( $post ),
			'post-comments-link'      => $this->resolveCommentsLink( $post ),
			'post-comments-title'     => $this->resolveCommentsTitle( $post ),
			'post-navigation-link'    => $this->resolvePostNavigationLink( $post ),
			'post-terms'              => $this->resolvePostTerms( $post ),
			'read-more'               => $this->resolveReadMore( $post ),
			'term-description'        => $this->resolveTermDescription( $post ),
			'author-social-icons'     => $this->resolveAuthorSocialIcons( $post ),
			'social-share-content'    => $this->resolveSocialShareContent( $post ),
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
		// Prefer `$post->rendered_content` when the host model exposes a
		// rendered-HTML accessor (cms-framework's HasBlockContent trait
		// provides one). Falls back to `$post->content`, which may be a
		// saved block tree, a pre-rendered HTML string, or null. The
		// block partial just needs a string; pass HTML through, otherwise
		// leave empty so the partial emits its placeholder shell.
		$rendered = $post->rendered_content ?? null;

		if ( is_string( $rendered ) && '' !== $rendered ) {
			return [
				'_resolvedContent' => $rendered,
			];
		}

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

	/**
	 * Stamp the post's id on a `post-comments-form` block so its
	 * renderer can emit a hidden `<input name="post_id">` and route
	 * the submission to the right post. Without this, a standalone
	 * comments-form (one not nested inside an `artisanpack/comments`
	 * wrapper) sends an empty `post_id` and the host's form-handler
	 * rejects on validation.
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveCommentsForm( object $post ): array
	{
		return [
			'_resolvedPostId' => isset( $post->id ) ? (int) $post->id : 0,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveCommentsCount( object $post ): array
	{
		return [
			'_resolvedCommentCount' => $this->commentCount( $post ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveCommentsLink( object $post ): array
	{
		$count = $this->commentCount( $post );

		return [
			'_resolvedCommentCount'   => $count,
			'_resolvedCommentsUrl'    => $this->commentsUrl( $post ),
			'_resolvedCommentsLabel'  => trans_choice(
				'{0} :count Comments|{1} :count Comment|[2,*] :count Comments',
				$count,
				[ 'count' => $count ]
			),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveCommentsTitle( object $post ): array
	{
		$count = $this->commentCount( $post );

		return [
			'_resolvedCommentCount'   => $count,
			'_resolvedCommentsTitle'  => trans_choice(
				'{0} No Comments|{1} 1 Comment|[2,*] :count Comments',
				$count,
				[ 'count' => $count ]
			),
		];
	}

	/**
	 * Stamp the adjacent (previous + next) post links onto a
	 * `post-navigation-link` block. The block's `type` attribute decides
	 * which pair the renderer picks at render time — the resolver always
	 * stamps both so a single iteration covers either configuration.
	 *
	 * The adjacent posts are read from one of the common host
	 * conventions ($post->previous_post / $post->prev / $post->next /
	 * $post->next_post). Hosts that don't expose an adjacency relation
	 * get empty values; the renderer emits a no-link shell.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolvePostNavigationLink( object $post ): array
	{
		$previous = $this->adjacentPost( $post, 'previous' );
		$next     = $this->adjacentPost( $post, 'next' );

		return [
			'_resolvedPrevUrl'   => null === $previous ? '' : $this->permalink( $previous ),
			'_resolvedPrevTitle' => null === $previous ? '' : (string) ( $previous->title ?? '' ),
			'_resolvedNextUrl'   => null === $next ? '' : $this->permalink( $next ),
			'_resolvedNextTitle' => null === $next ? '' : (string) ( $next->title ?? '' ),
			// Hosts that don't expose a `previous_post` / `next_post`
			// accessor on their Post model see both directions resolve to
			// empty. The renderer collapses that to a no-link shell so
			// the surrounding template still lays out correctly; wire the
			// accessors on the host model to populate the front end.
		];
	}

	/**
	 * Stamp the post's taxonomy terms onto a `post-terms` block. Stamps
	 * a `_resolvedTermsByTaxonomy` map keyed by taxonomy slug — the
	 * renderer picks the relevant entry using the block's own `term`
	 * attribute and joins them using `separator` / `prefix` / `suffix`.
	 *
	 * Reads terms from whichever convention the underlying model
	 * exposes:
	 *
	 *  - `$post->terms` — an iterable of term-shaped objects with a
	 *     `taxonomy` property (matches the WP convention).
	 *  - `$post->categories` / `$post->tags` — well-known shortcuts that
	 *     map onto the `category` / `post_tag` taxonomies.
	 *
	 * Each term is normalised to `{name, slug, url}` so the renderer
	 * doesn't have to know the underlying object shape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolvePostTerms( object $post ): array
	{
		return [
			'_resolvedTermsByTaxonomy' => $this->termsByTaxonomy( $post ),
		];
	}

	/**
	 * Stamp the post's permalink onto a `read-more` block. The block's
	 * own `content` attribute carries the link text; the renderer just
	 * needs the `href`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveReadMore( object $post ): array
	{
		return [
			'_resolvedPermalink' => $this->permalink( $post ),
		];
	}

	/**
	 * Allow-list for HTML tags surviving the term-description sanitizer.
	 * Mirrors {@see CommentResolver::COMMENT_CONTENT_ALLOWED_TAGS} so
	 * descriptions can carry basic formatting (paragraphs, emphasis,
	 * inline code, anchors) without giving the host an unsanitized
	 * `dangerouslySetInnerHTML` injection vector on the editor and
	 * front-end renderers.
	 */
	protected const TERM_DESCRIPTION_ALLOWED_TAGS = '<a><abbr><b><blockquote><br><cite><code><em><i><p><q><s><strong>';

	/**
	 * Stamp the post's primary-term description onto a `term-description`
	 * block. `term-description` is an archive-context block in upstream
	 * Gutenberg, but stamping the primary-term description lets hosts
	 * drop the block inside a query loop and still get meaningful content
	 * (e.g. "showing posts in <category>"). When no primary term is
	 * available the renderer falls back to a no-content shell.
	 *
	 * The description is sanitized through an allow-list strip_tags +
	 * the same on-event-attribute / javascript: scrub `CommentResolver`
	 * uses, so the front-end renderer (Blade `{!! !!}`) and editor
	 * preview (React `dangerouslySetInnerHTML`) can trust the stamped
	 * string without a follow-up escape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveTermDescription( object $post ): array
	{
		$term = $this->primaryTerm( $post );

		if ( null === $term ) {
			return [
				'_resolvedTermDescription' => '',
				'_resolvedTermName'        => '',
				'_resolvedTermUrl'         => '',
			];
		}

		$termUrl = '';
		if ( isset( $term->url ) && is_scalar( $term->url ) ) {
			$termUrl = (string) $term->url;
		} elseif ( isset( $term->permalink ) && is_scalar( $term->permalink ) ) {
			$termUrl = (string) $term->permalink;
		}

		$rawDescription = isset( $term->description ) && is_scalar( $term->description )
			? (string) $term->description
			: '';

		return [
			'_resolvedTermDescription' => $this->sanitizeTermDescription( $rawDescription ),
			'_resolvedTermName'        => isset( $term->name ) && is_scalar( $term->name ) ? (string) $term->name : '',
			'_resolvedTermUrl'         => $termUrl,
		];
	}

	/**
	 * Strip everything outside {@see self::TERM_DESCRIPTION_ALLOWED_TAGS}
	 * and scrub any `on*` event-handler attributes / `javascript:` URLs
	 * that survive the allow-list. Mirrors `CommentResolver::sanitize()`'s
	 * sequence so the two resolvers stay in sync.
	 */
	protected function sanitizeTermDescription( string $description ): string
	{
		if ( '' === $description ) {
			return '';
		}

		$description = strip_tags( $description, self::TERM_DESCRIPTION_ALLOWED_TAGS );
		$description = preg_replace( '/\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $description ) ?? '';
		$description = preg_replace( '/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'\s>]*\2/i', '$1=$2#$2', $description ) ?? '';

		return $description;
	}

	/**
	 * Resolve the adjacent post for the given direction. Tries the
	 * commonly-named accessors first; falls back to an Eloquent query
	 * against `published_at` when the host opts in via
	 * `artisanpack.visual-editor.resolver.adjacency.auto_query`. Returns
	 * null when neither path yields a row.
	 */
	protected function adjacentPost( object $post, string $direction ): ?object
	{
		$candidates = 'previous' === $direction
			? [ 'previous_post', 'previousPost', 'prev_post', 'prevPost', 'prev', 'previous' ]
			: [ 'next_post', 'nextPost', 'next' ];

		foreach ( $candidates as $key ) {
			$value = $post->{$key} ?? null;

			if ( is_object( $value ) ) {
				return $value;
			}
		}

		return $this->queryAdjacentPost( $post, $direction );
	}

	/**
	 * Generic safety-net for hosts whose Post model does not expose
	 * `previous_post` / `next_post` accessors but DOES expose an
	 * Eloquent query builder and a `published_at` timestamp.
	 *
	 * Gated behind `artisanpack.visual-editor.resolver.adjacency.auto_query`
	 * (default `false`) so hosts opt-in to the extra query per render.
	 * Returns null when the host has opted out, the model is not Eloquent,
	 * `published_at` is missing, or no adjacent row is found.
	 *
	 * @since 1.0.0
	 */
	protected function queryAdjacentPost( object $post, string $direction ): ?object
	{
		if ( true !== config( 'artisanpack.visual-editor.resolver.adjacency.auto_query', false ) ) {
			return null;
		}

		if ( ! method_exists( $post, 'newQuery' ) ) {
			return null;
		}

		$publishedAt = $post->published_at ?? null;

		if ( null === $publishedAt || '' === $publishedAt ) {
			return null;
		}

		$isPrevious = 'previous' === $direction;

		try {
			$query = $post->newQuery()
				->where( 'published_at', $isPrevious ? '<' : '>', $publishedAt )
				->orderBy( 'published_at', $isPrevious ? 'desc' : 'asc' );

			// Constrain the fallback to the same post type — the public
			// frontend renders adjacent-post containers per single-type
			// archive, so returning a different type as the "next post"
			// would surface the wrong template. `post_type` is the
			// convention; hosts that don't model it skip this scope.
			$postType = isset( $post->post_type ) && is_string( $post->post_type )
				? trim( $post->post_type )
				: '';

			if ( '' !== $postType ) {
				$query->where( 'post_type', $postType );
			}

			// Exclude the current post and add a deterministic tiebreaker
			// so equal `published_at` timestamps don't return $post itself
			// (or order non-deterministically across drivers).
			$postId = $post->id ?? null;

			if ( is_int( $postId ) || ( is_string( $postId ) && '' !== $postId ) ) {
				$query
					->where( 'id', '!=', $postId )
					->orderBy( 'id', $isPrevious ? 'desc' : 'asc' );
			}

			$result = $query->first();
		} catch ( \Throwable ) {
			return null;
		}

		return is_object( $result ) ? $result : null;
	}

	/**
	 * Normalise the post's terms into a `{taxonomy: [{name, slug, url}]}`
	 * map. Tolerant to several common model shapes; degrades to an empty
	 * map when the model does not expose any taxonomy relations.
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	protected function termsByTaxonomy( object $post ): array
	{
		$out = [];

		$general = $post->terms ?? null;

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
			$collection = $post->{$relation} ?? null;

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
	 * Resolve the post's "primary" term. Hosts that expose a
	 * `primary_term` / `primaryTerm` accessor (e.g. via a Yoast-style
	 * meta) win; otherwise the first term from the first taxonomy with
	 * any terms is used. Returns null when the post has no terms at all.
	 */
	protected function primaryTerm( object $post ): ?object
	{
		foreach ( [ 'primary_term', 'primaryTerm' ] as $key ) {
			$value = $post->{$key} ?? null;

			if ( is_object( $value ) ) {
				return $value;
			}
		}

		$general = $post->terms ?? null;

		if ( is_iterable( $general ) ) {
			foreach ( $general as $term ) {
				if ( is_object( $term ) ) {
					return $term;
				}
			}
		}

		foreach ( [ 'categories', 'tags' ] as $relation ) {
			$collection = $post->{$relation} ?? null;

			if ( ! is_iterable( $collection ) ) {
				continue;
			}

			foreach ( $collection as $term ) {
				if ( is_object( $term ) ) {
					return $term;
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
		// renderer-side link target populates regardless.
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
	 * Read the comment count from whichever convention the underlying
	 * model exposes — Eloquent's `comments_count` accessor (when the
	 * relation has been counted), a `comment_count` column (matches
	 * WP's posts table), or a counted `comments` collection. Defaults
	 * to zero so the partials render "0 Comments" rather than a
	 * placeholder shell.
	 */
	protected function commentCount( object $post ): int
	{
		$raw = $post->comments_count ?? $post->comment_count ?? null;

		if ( is_int( $raw ) || ( is_string( $raw ) && ctype_digit( $raw ) ) ) {
			return (int) $raw;
		}

		$comments = $post->comments ?? null;

		if ( is_countable( $comments ) ) {
			return count( $comments );
		}

		return 0;
	}

	protected function commentsUrl( object $post ): string
	{
		$url = $post->comments_url ?? null;

		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}

		$permalink = $this->permalink( $post );

		return '' === $permalink ? '' : $permalink . '#comments';
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

	/**
	 * Author social profile slugs the resolver knows how to pull from a
	 * post's author relation. The author-social-icons renderer trims
	 * `_resolvedAuthorSocialLinks` to whatever slugs the author has
	 * filled in; the block's own `socialIcons` attribute is the
	 * intersection picker applied at render time.
	 *
	 * @var array<int, string>
	 */
	protected const AUTHOR_SOCIAL_SLUGS = [
		'facebook',
		'twitter',
		'mastodon',
		'instagram',
		'tumblr',
		'email',
		'website',
	];

	/**
	 * Share-platform slugs the resolver knows how to build share URLs for.
	 * The social-share-content renderer respects the block's own
	 * `socialIcons` attribute as the visibility picker; the stamp here
	 * just provides the canonical `{slug, url}` pair for every platform
	 * so the renderer never has to know about share-URL syntax.
	 *
	 * @var array<int, string>
	 */
	protected const SHARE_PLATFORM_SLUGS = [
		'facebook',
		'twitter',
		'mastodon',
		'reddit',
		'pinterest',
		'email',
	];

	/**
	 * Stamp the post author's social profile URLs onto the
	 * `artisanpack/author-social-icons` block as `_resolvedAuthorSocialLinks`.
	 * Each entry is `{ slug, url }` — labels and SVG paths live in the
	 * renderer so an author exposing only a subset of platforms still
	 * produces a tight chip list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveAuthorSocialIcons( object $post ): array
	{
		$author = $post->author ?? null;
		$links  = [];

		if ( null !== $author ) {
			foreach ( self::AUTHOR_SOCIAL_SLUGS as $slug ) {
				$url = $this->authorSocialUrl( $author, $slug );

				if ( '' === $url ) {
					continue;
				}

				$links[] = [
					'slug' => $slug,
					'url'  => $url,
				];
			}
		}

		return [
			'_resolvedAuthorSocialLinks' => $links,
		];
	}

	/**
	 * Stamp pre-built share URLs for the host post onto the
	 * `artisanpack/social-share-content` block as `_resolvedShareLinks`.
	 * Each entry is `{ slug, url }`. The renderer maps `slug` to the
	 * shared icon path / human label registry.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveSocialShareContent( object $post ): array
	{
		$permalink = $this->permalink( $post );
		$title     = (string) ( $post->title ?? '' );
		$image     = $this->featuredImageUrl( $post );

		$encodedUrl   = '' === $permalink ? '' : rawurlencode( $permalink );
		$encodedTitle = '' === $title ? '' : rawurlencode( $title );
		$encodedImage = '' === $image ? '' : rawurlencode( $image );

		$links = [];

		foreach ( self::SHARE_PLATFORM_SLUGS as $slug ) {
			$url = $this->shareUrl( $slug, $encodedUrl, $encodedTitle, $encodedImage );

			if ( '' === $url ) {
				continue;
			}

			$links[] = [
				'slug' => $slug,
				'url'  => $url,
			];
		}

		return [
			'_resolvedShareLinks' => $links,
		];
	}

	/**
	 * Best-effort lookup of one social-profile URL on the author model.
	 * Hosts may store the URL on a same-named property (`$author->facebook`)
	 * or through a generic `social` array (`$author->social['facebook']`).
	 * The first non-empty hit wins.
	 */
	protected function authorSocialUrl( object $author, string $slug ): string
	{
		$candidates = [];

		$direct = $author->{$slug} ?? null;

		if ( is_string( $direct ) && '' !== trim( $direct ) ) {
			$candidates[] = trim( $direct );
		}

		$social = $author->social ?? null;

		if ( is_array( $social ) && isset( $social[ $slug ] ) && is_string( $social[ $slug ] ) ) {
			$value = trim( $social[ $slug ] );

			if ( '' !== $value ) {
				$candidates[] = $value;
			}
		}

		// `website` falls back to `url` / `website` accessors so hosts
		// that don't model a dedicated `website` field still surface a
		// link.
		if ( 'website' === $slug && [] === $candidates ) {
			foreach ( [ 'website', 'url' ] as $key ) {
				$url = $author->{$key} ?? null;

				if ( is_string( $url ) && '' !== trim( $url ) ) {
					$candidates[] = trim( $url );
					break;
				}
			}
		}

		if ( [] === $candidates ) {
			return '';
		}

		$value = $candidates[0];

		// Normalize bare email addresses to `mailto:` so the renderer
		// can route the chip as a real link without per-renderer URL
		// fixups. Validate the address first — author profile fields
		// may be user-editable, and an unvalidated value would let a
		// malformed `mailto:` body through.
		if ( 'email' === $slug && ! str_starts_with( $value, 'mailto:' ) ) {
			if ( false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
				return '';
			}

			return 'mailto:' . $value;
		}

		// Scheme allow-list: the chip URL is rendered as the `href` of
		// an `<a>` on the public frontend, so any `javascript:` /
		// `data:` / `vbscript:` value here would become stored XSS the
		// moment an author profile field is editable. Allow only
		// http(s), mailto, and tel; anything else is dropped.
		$scheme = strtolower( (string) parse_url( $value, PHP_URL_SCHEME ) );

		if ( ! in_array( $scheme, [ 'http', 'https', 'mailto', 'tel' ], true ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Read the post's featured-image URL through the visual-editor's
	 * media-library helper when available. Returns an empty string when
	 * the host has no featured image (or no media-library install).
	 */
	protected function featuredImageUrl( object $post ): string
	{
		$mediaId = $post->featured_image_id ?? $post->featured_media ?? null;

		if ( null === $mediaId || ! function_exists( 'apGetMediaUrl' ) ) {
			return '';
		}

		$resolved = apGetMediaUrl( (int) $mediaId, 'full' );

		return is_string( $resolved ) ? $resolved : '';
	}

	/**
	 * Build the share URL for a single platform. Returns an empty
	 * string when the platform's required pieces are missing so the
	 * renderer can drop the chip rather than emit a broken link.
	 */
	protected function shareUrl(
		string $slug,
		string $encodedUrl,
		string $encodedTitle,
		string $encodedImage
	): string {
		return match ( $slug ) {
			'facebook'  => '' === $encodedUrl
				? ''
				: 'https://www.facebook.com/sharer.php?u=' . $encodedUrl,
			'twitter'   => '' === $encodedUrl
				? ''
				: 'https://twitter.com/share?url=' . $encodedUrl
					. ( '' === $encodedTitle ? '' : '&text=' . $encodedTitle ),
			'mastodon'  => '' === $encodedUrl
				? ''
				: 'https://mastodonshare.com/?url=' . $encodedUrl
					. ( '' === $encodedTitle ? '' : '&text=' . $encodedTitle ),
			'reddit'    => '' === $encodedUrl
				? ''
				: 'https://www.reddit.com/submit?url=' . $encodedUrl,
			'pinterest' => '' === $encodedUrl
				? ''
				: 'https://pinterest.com/pin/create/bookmarklet/?url=' . $encodedUrl
					. ( '' === $encodedImage ? '' : '&media=' . $encodedImage )
					. ( '' === $encodedTitle ? '' : '&description=' . $encodedTitle ),
			'email'     => '' === $encodedUrl
				? ''
				: 'mailto:?subject=' . ( '' === $encodedTitle ? '' : $encodedTitle )
					. '&body=' . $encodedUrl,
			default     => '',
		};
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
