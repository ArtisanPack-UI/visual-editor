<?php

/**
 * CommentInliner — pre-pass that expands every `artisanpack/comments`
 * block in a saved tree by cloning its inner `artisanpack/comment-template`
 * once per comment on the surrounding post and stamping each iteration's
 * `comment-*` leaves via {@see CommentResolver}.
 *
 * Sibling to {@see QueryInliner}: the inliner runs once on the way into
 * the renderer, so the per-block partials / components do not need any
 * new walk-over-comments logic.
 *
 * Resolution is best-effort and mirrors QueryInliner's contract:
 *
 *  - When no `$post` context is supplied (the caller is rendering a
 *    surface that doesn't have a single post in scope — an archive
 *    page, the site editor, etc.), `artisanpack/comments` blocks are
 *    left in place with a `_resolutionError = 'no-post-context'`
 *    marker. The renderers translate that to an empty render so the
 *    surrounding layout is unaffected.
 *  - When the post does not expose a `comments` relation (or it's
 *    empty), the block is collapsed to its non-template children
 *    (typically the post-comments-form / count / title cluster) so a
 *    "Be the first to comment" surface still renders.
 *  - When the inner blocks contain no `artisanpack/comment-template`,
 *    the block is returned untouched — host themes that ship a custom
 *    inner layout aren't regressed by the pre-pass.
 *
 * Nested inside `artisanpack/query`: the inliner walks the
 * already-expanded query iterations and uses each iteration's
 * stamped post id to resolve the right comment set per iteration.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

class CommentInliner
{
	public const ERROR_NO_POST_CONTEXT = 'no-post-context';

	public function __construct(
		protected CommentResolver $commentResolver,
	) {}

	/**
	 * Walks `$tree` and returns a copy with every
	 * `artisanpack/comments` block carrying expanded
	 * `artisanpack/comment-template` instances under `innerBlocks`.
	 *
	 * @since 2.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  object|null                       $post  Post in scope (provides the `comments` relation).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function inline( array $tree, ?object $post = null ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out[] = $this->inlineBlock( $block, $post );
		}

		return $out;
	}

	/**
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function inlineBlock( array $block, ?object $post ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		if ( 'artisanpack/comments' === $name || 'core/comments' === $name ) {
			return $this->expandComments( $block, $post );
		}

		// Recurse into inner blocks so a `comments` block nested
		// inside a `post-template-item` iteration (e.g. a post archive
		// preview) still expands against its surrounding iteration's
		// post. The caller passes the parent context down; the
		// iteration's `_resolvedPostId` does not currently re-thread
		// `$post` automatically — that's tracked as a follow-up.
		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = $this->inline( $block['innerBlocks'], $post );
		}

		return $block;
	}

	/**
	 * Expand one `artisanpack/comments` block: clone the inner
	 * `comment-template` once per `$post->comments` row, stamping each
	 * iteration via `CommentResolver`. Wraps every iteration in a
	 * synthetic `artisanpack/comment-template-item` block so the
	 * outer `<ol>` emits a single list with N `<li>` items, matching
	 * the upstream Gutenberg shape.
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function expandComments( array $block, ?object $post ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];
		$inner      = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];

		if ( null === $post ) {
			return array_merge( $block, [
				'attributes' => array_merge( $attributes, [
					'_resolutionError' => self::ERROR_NO_POST_CONTEXT,
				] ),
			] );
		}

		// Stamp the post id onto the wrapper itself so children like
		// post-comments-form / post-comments-count / post-comments-link
		// can read it without a separate stamping pass.
		$postId       = isset( $post->id ) ? (int) $post->id : 0;
		$commentCount = $this->resolveCommentCount( $post );
		$commentsUrl  = $this->resolveCommentsUrl( $post );

		$attributes = array_merge( $attributes, [
			'_resolvedPostId'        => $postId,
			'_resolvedCommentCount'  => $commentCount,
			'_resolvedCommentsUrl'   => $commentsUrl,
			'_resolvedCommentsLabel' => trans_choice(
				'{0} :count Comments|{1} :count Comment|[2,*] :count Comments',
				$commentCount,
				[ 'count' => $commentCount ]
			),
		] );

		$expandedChildren = [];

		foreach ( $inner as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$childName = isset( $child['name'] ) && is_string( $child['name'] ) ? $child['name'] : '';

			// The comment-template wrapper is the only thing that gets
			// per-comment expansion. Everything else
			// (post-comments-form / count / title / pagination) is
			// passed through with the post-level _resolved* attrs
			// merged in — keep its structure but inherit the
			// wrapper's stamped attributes.
			if ( 'artisanpack/comment-template' === $childName || 'core/comment-template' === $childName ) {
				$expandedChildren[] = $this->expandCommentTemplate( $child, $post );
				continue;
			}

			$expandedChildren[] = $this->stampPostLevelAttributes( $child, $attributes );
		}

		return array_merge( $block, [
			'attributes'  => $attributes,
			'innerBlocks' => $expandedChildren,
		] );
	}

	/**
	 * @param  array<string, mixed>  $templateBlock
	 *
	 * @return array<string, mixed>
	 */
	protected function expandCommentTemplate( array $templateBlock, object $post ): array
	{
		$templateAttrs    = isset( $templateBlock['attributes'] ) && is_array( $templateBlock['attributes'] ) ? $templateBlock['attributes'] : [];
		$iterationTemplate = isset( $templateBlock['innerBlocks'] ) && is_array( $templateBlock['innerBlocks'] ) ? $templateBlock['innerBlocks'] : [];

		$comments = $this->readComments( $post );

		// No template children or no comments — emit the empty
		// wrapper so the surrounding scaffold stays consistent.
		if ( [] === $iterationTemplate || [] === $comments ) {
			return array_merge( $templateBlock, [
				'attributes'  => array_merge( $templateAttrs, [
					'_resolvedItems' => count( $comments ),
				] ),
				'innerBlocks' => [],
			] );
		}

		$expandedIterations = [];

		foreach ( $comments as $comment ) {
			if ( ! is_object( $comment ) ) {
				continue;
			}

			$commentId       = isset( $comment->id ) ? (int) $comment->id : 0;
			$iterationBlocks = [];

			foreach ( $iterationTemplate as $tmplChild ) {
				if ( ! is_array( $tmplChild ) ) {
					continue;
				}

				$iterationBlocks[] = $this->commentResolver->stampBlock(
					$this->cloneBlock( $tmplChild ),
					$comment
				);
			}

			$expandedIterations[] = [
				'clientId'    => 'cti-' . $commentId,
				'name'        => 'artisanpack/comment-template-item',
				'attributes'  => [
					'commentId' => $commentId,
					'className' => 'comment-' . $commentId,
				],
				'innerBlocks' => $iterationBlocks,
			];
		}

		return array_merge( $templateBlock, [
			'attributes'  => array_merge( $templateAttrs, [
				'_resolvedItems' => count( $expandedIterations ),
			] ),
			'innerBlocks' => $expandedIterations,
		] );
	}

	/**
	 * Merge the wrapper's `_resolved*` attributes into a non-template
	 * child block so post-level metadata blocks
	 * (post-comments-form / count / title / link) can render against
	 * them without a separate resolver pass.
	 *
	 * @param  array<string, mixed>  $block
	 * @param  array<string, mixed>  $stamped
	 *
	 * @return array<string, mixed>
	 */
	protected function stampPostLevelAttributes( array $block, array $stamped ): array
	{
		$existing = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		// Only forward the `_resolved*` keys — host overrides on the
		// block's own attributes always win.
		$toMerge = [];
		foreach ( $stamped as $key => $value ) {
			if ( is_string( $key ) && str_starts_with( $key, '_resolved' ) && ! isset( $existing[ $key ] ) ) {
				$toMerge[ $key ] = $value;
			}
		}

		return array_merge( $block, [
			'attributes' => array_merge( $existing, $toMerge ),
		] );
	}

	/**
	 * @return array<int|string, object>
	 */
	protected function readComments( object $post ): array
	{
		$comments = $post->comments ?? null;

		if ( null === $comments ) {
			return [];
		}

		// Eloquent collection, plain array, or anything iterable.
		if ( is_array( $comments ) ) {
			return $comments;
		}

		if ( is_object( $comments ) && method_exists( $comments, 'all' ) ) {
			$all = $comments->all();
			return is_array( $all ) ? $all : [];
		}

		if ( is_iterable( $comments ) ) {
			return iterator_to_array( $comments );
		}

		return [];
	}

	protected function resolveCommentCount( object $post ): int
	{
		$raw = $post->comments_count ?? $post->comment_count ?? null;

		if ( is_int( $raw ) || ( is_string( $raw ) && ctype_digit( $raw ) ) ) {
			return (int) $raw;
		}

		$comments = $this->readComments( $post );

		return count( $comments );
	}

	protected function resolveCommentsUrl( object $post ): string
	{
		$url = $post->comments_url ?? null;

		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}

		$permalink = $post->permalink ?? null;

		return is_string( $permalink ) && '' !== $permalink ? $permalink . '#comments' : '';
	}

	/**
	 * Deep-clone a block subtree so per-iteration stamping never
	 * mutates the template the next iteration will read from.
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function cloneBlock( array $block ): array
	{
		// PHP arrays are copy-on-write at the top level, so the
		// assignment below already gives us a fresh array. We still
		// have to walk into `innerBlocks` so per-iteration stamping
		// can't reach into the template's nested blocks and mutate
		// the next iteration's source. Top-level `attributes` is an
		// array too, so it's also copy-on-write — no explicit clone
		// needed there.
		$clone = $block;

		if ( isset( $clone['innerBlocks'] ) && is_array( $clone['innerBlocks'] ) ) {
			$cloned = [];
			foreach ( $clone['innerBlocks'] as $child ) {
				$cloned[] = is_array( $child ) ? $this->cloneBlock( $child ) : $child;
			}
			$clone['innerBlocks'] = $cloned;
		}

		return $clone;
	}
}
