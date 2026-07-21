<?php

/**
 * Breadcrumbs resolver for the Blade renderer (#565).
 *
 * Walks a saved block tree and stamps `_resolvedTrail` onto every
 * `artisanpack/breadcrumbs` block so the Blade / React / Vue partials
 * (which already consume `_resolvedTrail`) render a populated `<ol>`
 * on the public frontend.
 *
 * The trail is a list of `{ label: string, url: ?string, current: bool }`
 * entries. A default trail always starts with a "Home" hop pointing at
 * the configured home URL; when a host post is in scope the post (and
 * any of its parent ancestors, for hierarchical content) is appended,
 * with the final entry marked `current: true` so the renderer drops the
 * link and emits `aria-current="page"`.
 *
 * Hosts customize the trail through the
 * `ap.visualEditor.breadcrumbs.trail` filter — e.g. to insert a
 * "Category" hop between Home and a blog post — without having to
 * subclass the resolver. The filter receives the resolved trail, the
 * current post (or null), and the block's attributes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Resolvers;

use Throwable;

class BreadcrumbsResolver
{
	/**
	 * Block name this resolver stamps. Anything else is returned untouched.
	 */
	protected const BREADCRUMBS_BLOCK = 'artisanpack/breadcrumbs';

	/**
	 * Maximum depth we recurse when walking a hierarchical post's parent
	 * chain — guards against an accidental cycle (`page.parent_id` points
	 * back at the page itself, or a longer loop) without making the
	 * resolver care about model-level integrity.
	 */
	protected const MAX_PARENT_DEPTH = 32;

	/**
	 * Recursively walk a block subtree and stamp `_resolvedTrail` on every
	 * `artisanpack/breadcrumbs` block. Non-breadcrumbs blocks are walked
	 * through to handle nested cases (e.g. a breadcrumbs block inside a
	 * group), with their attributes left untouched.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function stampTree( array $tree, ?object $post = null ): array
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
	 * Stamp a single block (and recurse into its inner blocks) against the
	 * given post context. Only `artisanpack/breadcrumbs` blocks get a
	 * `_resolvedTrail` attribute; everything else is forwarded with its
	 * inner tree walked through.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	public function stampBlock( array $block, ?object $post = null ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] )
			? $block['attributes']
			: [];

		if ( self::BREADCRUMBS_BLOCK === $name ) {
			// A pre-stamped `_resolvedTrail` on the host's saved tree wins
			// over the resolver fallback — matches PostResolver's contract
			// so a host that has resolved the trail upstream (e.g. through
			// a server-side render pipeline that knows more about the
			// request than this resolver does) keeps full control.
			if ( ! array_key_exists( '_resolvedTrail', $attributes ) ) {
				$attributes['_resolvedTrail'] = $this->buildTrail( $post, $attributes );
			}
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
	 * Build the resolved trail for a single breadcrumbs block. The default
	 * shape is `[Home, …ancestors, current]`. Hosts override the result
	 * through the `ap.visualEditor.breadcrumbs.trail` filter when the
	 * `artisanpack-ui/hooks` helper is available.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes  Block attributes passed
	 *                                            through to the filter so
	 *                                            host code can inspect e.g.
	 *                                            the `breadcrumbsSchema` flag.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function buildTrail( ?object $post, array $attributes = [] ): array
	{
		$trail = $this->defaultTrail( $post );

		if ( function_exists( 'applyFilters' ) ) {
			$filtered = applyFilters( 'ap.visualEditor.breadcrumbs.trail', $trail, $post, $attributes );

			if ( is_array( $filtered ) ) {
				$trail = $filtered;
			}
		}

		return $this->finalizeTrail( $trail );
	}

	/**
	 * Compose the unfiltered default trail: a "Home" entry plus, when a
	 * post is in scope, any parent ancestors and the post itself.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function defaultTrail( ?object $post ): array
	{
		$homeUrl = $this->resolveHomeUrl();

		$trail = [
			[
				'label' => $this->resolveHomeLabel(),
				'url'   => $homeUrl,
			],
		];

		// No post in scope (homepage, 404, archive without a single record).
		// The trail collapses to the single Home entry the finalizer will
		// mark `current: true`, so the renderer drops the link and emits
		// `aria-current="page"`.
		if ( null === $post ) {
			return $trail;
		}

		$ancestors = $this->collectAncestors( $post );

		foreach ( $ancestors as $ancestor ) {
			$trail[] = [
				'label' => $this->postLabel( $ancestor ),
				'url'   => $this->postUrl( $ancestor ),
			];
		}

		$trail[] = [
			'label' => $this->postLabel( $post ),
			'url'   => $this->postUrl( $post ),
		];

		return $trail;
	}

	/**
	 * Normalise every entry: ensure each item has `label`, `url`, and
	 * `current` keys; mark the final entry `current: true` and strip its
	 * `url` so the renderer emits an unlinked `aria-current="page"` span;
	 * drop entries whose label is blank or non-scalar so the host can
	 * filter values out by returning empty strings.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<mixed>  $trail
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function finalizeTrail( array $trail ): array
	{
		$normalized = [];

		foreach ( $trail as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$label = $entry['label'] ?? '';

			if ( ! is_scalar( $label ) ) {
				continue;
			}

			$label = (string) $label;

			if ( '' === $label ) {
				continue;
			}

			$rawUrl = $entry['url'] ?? null;
			$url    = is_scalar( $rawUrl ) ? (string) $rawUrl : '';

			$normalized[] = [
				'label'   => $label,
				'url'     => '' === $url ? null : $url,
				'current' => ! empty( $entry['current'] ),
			];
		}

		if ( [] === $normalized ) {
			return [];
		}

		$lastIndex = count( $normalized ) - 1;

		// Only auto-mark the tail when no upstream entry has already
		// declared itself current. Host filters that nominate a different
		// entry as "current" (rare, but legal) keep control.
		$hasCurrent = false;
		foreach ( $normalized as $entry ) {
			if ( true === $entry['current'] ) {
				$hasCurrent = true;
				break;
			}
		}

		if ( ! $hasCurrent ) {
			$normalized[ $lastIndex ]['current'] = true;
			$normalized[ $lastIndex ]['url']     = null;
		}

		return $normalized;
	}

	/**
	 * Walk the post's parent chain (bottom-up) and return the ancestor
	 * objects in top-down order. Returns an empty array when the post
	 * has no parent accessor or no `parent_id`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, object>
	 */
	protected function collectAncestors( object $post ): array
	{
		$ancestors = [];
		$seen      = [];
		$current   = $this->parentPost( $post );
		$depth     = 0;

		while ( $current instanceof \stdClass || is_object( $current ) ) {
			$id = $this->postIdentity( $current );

			if ( null !== $id && isset( $seen[ $id ] ) ) {
				break;
			}

			if ( null !== $id ) {
				$seen[ $id ] = true;
			}

			$ancestors[] = $current;

			if ( ++$depth >= self::MAX_PARENT_DEPTH ) {
				break;
			}

			$current = $this->parentPost( $current );
		}

		return array_reverse( $ancestors );
	}

	/**
	 * Resolve a post's parent record. Tries the `parent` accessor first
	 * (Eloquent `belongsTo` convention) and falls back to a `parent_id`
	 * lookup through `newQuery()` for hosts that expose the foreign key
	 * but not a relation method. Returns null when neither path yields
	 * a row.
	 *
	 * @since 1.0.0
	 */
	protected function parentPost( object $post ): ?object
	{
		$parent = $post->parent ?? null;

		if ( is_object( $parent ) ) {
			return $parent;
		}

		$parentId = $post->parent_id ?? null;

		if ( null === $parentId || '' === $parentId ) {
			return null;
		}

		if ( ! method_exists( $post, 'newQuery' ) ) {
			return null;
		}

		try {
			$result = $post->newQuery()->find( $parentId );
		} catch ( Throwable ) {
			return null;
		}

		return is_object( $result ) ? $result : null;
	}

	/**
	 * Read a stable identity for a post (used to detect parent-chain
	 * cycles without trusting equality on the object reference).
	 *
	 * @since 1.0.0
	 */
	protected function postIdentity( object $post ): ?string
	{
		$id = $post->id ?? null;

		if ( is_int( $id ) || is_string( $id ) ) {
			return get_class( $post ) . ':' . $id;
		}

		return null;
	}

	/**
	 * Resolve the human-readable label for a post — title first, then
	 * `name`, then an empty string so the finalizer can drop the entry.
	 *
	 * @since 1.0.0
	 */
	protected function postLabel( object $post ): string
	{
		$title = $post->title ?? null;

		if ( is_scalar( $title ) && '' !== (string) $title ) {
			return (string) $title;
		}

		$name = $post->name ?? null;

		if ( is_scalar( $name ) && '' !== (string) $name ) {
			return (string) $name;
		}

		return '';
	}

	/**
	 * Resolve the permalink URL for a post. Mirrors PostResolver's
	 * `permalink()` — many `HasBlockContent` models expose `permalink`
	 * as an Eloquent attribute accessor; hosts that don't get an empty
	 * string and the renderer drops the link.
	 *
	 * @since 1.0.0
	 */
	protected function postUrl( object $post ): string
	{
		$permalink = $post->permalink ?? null;

		if ( is_string( $permalink ) ) {
			return $permalink;
		}

		$url = $post->url ?? null;

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Resolve the configured home URL — `config('artisanpack.visual-editor.breadcrumbs.home_url')`
	 * wins when set; otherwise falls back to `url('/')`.
	 *
	 * @since 1.0.0
	 */
	protected function resolveHomeUrl(): string
	{
		$configured = config( 'artisanpack.visual-editor.breadcrumbs.home_url' );

		if ( is_string( $configured ) && '' !== $configured ) {
			return $configured;
		}

		try {
			$resolved = url( '/' );

			return is_string( $resolved ) ? $resolved : '/';
		} catch ( Throwable ) {
			return '/';
		}
	}

	/**
	 * Resolve the human-readable label for the Home entry —
	 * `config('artisanpack.visual-editor.breadcrumbs.home_label')` wins
	 * when set; otherwise falls back to the translated "Home" string.
	 *
	 * @since 1.0.0
	 */
	protected function resolveHomeLabel(): string
	{
		$configured = config( 'artisanpack.visual-editor.breadcrumbs.home_label' );

		if ( is_string( $configured ) && '' !== $configured ) {
			return $configured;
		}

		return __( 'Home' );
	}
}
