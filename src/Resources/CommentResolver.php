<?php

/**
 * CommentResolver — stamps `_resolved*` attributes onto `core/comment-*`
 * and `artisanpack/comment-*` blocks against a single comment record.
 *
 * Mirrors {@see PostResolver} but for the comment context: the
 * `CommentInliner` (or any future expansion of the dynamic block
 * registry) walks each inner `comment-template` instance once per
 * resolved comment and every supported per-comment display block
 * inside it gets the corresponding `_resolved*` keys stamped from
 * the current comment. The renderer-side code path reads those keys,
 * so the resolver is the only piece that has to know about the
 * underlying model shape.
 *
 * The resolver is tolerant: any field the underlying model does not
 * expose is left empty, and the block partials emit Gutenberg-shaped
 * placeholder markup so the rendered tree still has the right
 * structure.
 *
 * Comments family fork (#519) — Pass 1 (wrapper + template + 6 inner
 * blocks). Post-level comments metadata (`post-comments-form`,
 * `post-comments-count`, etc.) and pagination blocks land in Pass 2.
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

class CommentResolver
{
	/**
	 * Block names this resolver knows how to stamp. Blocks not in this
	 * list are returned untouched.
	 *
	 * @var array<int, string>
	 */
	protected const SUPPORTED_BLOCKS = [
		'core/comment-author-avatar',
		'core/comment-author-name',
		'core/comment-content',
		'core/comment-date',
		'core/comment-edit-link',
		'core/comment-reply-link',
		// Phase #519 forks — same `_resolved*` contract, new namespace.
		'artisanpack/comment-author-avatar',
		'artisanpack/comment-author-name',
		'artisanpack/comment-content',
		'artisanpack/comment-date',
		'artisanpack/comment-edit-link',
		'artisanpack/comment-reply-link',
	];

	/**
	 * Recursively walk a block subtree and stamp every supported
	 * comment display block against the given comment.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function stampTree( array $tree, object $comment ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out[] = $this->stampBlock( $block, $comment );
		}

		return $out;
	}

	/**
	 * Stamp a single block (and its inner blocks) against the given
	 * comment.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	public function stampBlock( array $block, object $comment ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		if ( in_array( $name, self::SUPPORTED_BLOCKS, true ) ) {
			$attributes = array_merge( $this->resolveAttributesFor( $name, $comment ), $attributes );
		}

		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $this->stampTree( $block['innerBlocks'], $comment )
			: [];

		return array_merge( $block, [
			'attributes'  => $attributes,
			'innerBlocks' => $inner,
		] );
	}

	/**
	 * Returns the stamped `_resolved*` attributes for a single block /
	 * comment pair. Returned as a "defaults" map — pre-existing
	 * `_resolved*` keys on the block win on merge.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveAttributesFor( string $name, object $comment ): array
	{
		// Match on the unqualified block slug so both the `core/*`
		// blocks and their `artisanpack/*` forks resolve through the
		// same branch.
		$slug = str_contains( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;

		return match ( $slug ) {
			'comment-author-avatar' => $this->resolveAvatar( $comment ),
			'comment-author-name'   => $this->resolveAuthorName( $comment ),
			'comment-content'       => $this->resolveContent( $comment ),
			'comment-date'          => $this->resolveDate( $comment ),
			'comment-edit-link'     => $this->resolveEditLink( $comment ),
			'comment-reply-link'    => $this->resolveReplyLink( $comment ),
			default                 => [],
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveAvatar( object $comment ): array
	{
		$author = $comment->author ?? null;
		$url    = '';

		if ( null !== $author ) {
			$url = (string) ( $author->avatar_url ?? $author->avatarUrl ?? '' );
		}

		if ( '' === $url ) {
			$url = (string) ( $comment->author_avatar_url ?? '' );
		}

		return [
			'_resolvedAvatarUrl' => $url,
			'_resolvedAvatarAlt' => $this->authorName( $comment ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveAuthorName( object $comment ): array
	{
		return [
			'_resolvedAuthorName' => $this->authorName( $comment ),
			'_resolvedAuthorUrl'  => $this->authorUrl( $comment ),
		];
	}

	/**
	 * Safe inline tags retained when sanitizing comment content. Mirrors
	 * the conservative subset WordPress allows for unauthenticated
	 * commenters, dropping structural / scripted tags entirely.
	 *
	 * @var string
	 */
	protected const COMMENT_CONTENT_ALLOWED_TAGS = '<a><abbr><acronym><b><blockquote><br><cite><code><del><em><i><p><q><s><strike><strong>';

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveContent( object $comment ): array
	{
		$content = $comment->content ?? null;

		return [
			'_resolvedContent' => $this->sanitizeCommentContent( is_string( $content ) ? $content : '' ),
		];
	}

	/**
	 * Defensively sanitize comment HTML before it is stamped onto
	 * `_resolvedContent` and rendered raw by the per-framework templates.
	 * Comment bodies are user-supplied and the host model layer does not
	 * filter them, so the resolver strips disallowed tags, inline event
	 * handlers, and `javascript:` URLs to make the stamped value safe
	 * against stored XSS regardless of which renderer consumes it.
	 *
	 * @since 1.0.0
	 */
	protected function sanitizeCommentContent( string $content ): string
	{
		if ( '' === $content ) {
			return '';
		}

		$content = strip_tags( $content, self::COMMENT_CONTENT_ALLOWED_TAGS );
		$content = preg_replace( '/\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content ) ?? '';
		$content = preg_replace( '/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'\s>]*\2/i', '$1=$2#$2', $content ) ?? '';

		return $content;
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveDate( object $comment ): array
	{
		$published = $this->toCarbon( $comment->created_at ?? $comment->published_at ?? null );

		return [
			'_resolvedDate'          => null === $published ? '' : $published->toIso8601String(),
			'_resolvedDateFormatted' => null === $published ? '' : $published->translatedFormat( 'F j, Y' ),
			'_resolvedPermalink'     => $this->permalink( $comment ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveEditLink( object $comment ): array
	{
		$url = $comment->edit_link ?? $comment->editLink ?? null;

		return [
			'_resolvedEditLinkUrl'   => is_string( $url ) ? $url : '',
			'_resolvedEditLinkLabel' => 'Edit',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function resolveReplyLink( object $comment ): array
	{
		$url = $comment->reply_link ?? $comment->replyLink ?? null;

		return [
			'_resolvedReplyLinkUrl'   => is_string( $url ) ? $url : '',
			'_resolvedReplyLinkLabel' => 'Reply',
		];
	}

	protected function authorName( object $comment ): string
	{
		$author = $comment->author ?? null;

		if ( null !== $author ) {
			$name = $author->name ?? $author->display_name ?? null;

			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}

		return (string) ( $comment->author_name ?? '' );
	}

	protected function authorUrl( object $comment ): string
	{
		$author = $comment->author ?? null;

		if ( null !== $author ) {
			$url = $author->url ?? $author->website ?? null;

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return (string) ( $comment->author_url ?? '' );
	}

	protected function permalink( object $comment ): string
	{
		$permalink = $comment->permalink ?? null;

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
