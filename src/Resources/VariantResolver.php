<?php

/**
 * VariantResolver — server-side counterpart to the editor's
 * `variant-matcher.ts`. Given a list of `artisanpack/post-variant`
 * blocks, a precompiled position→variantId map, and a single post in
 * the loop, returns the winning variant id (or `null` for "use the
 * base post-template").
 *
 * Precedence cascade mirrors the editor:
 *
 *   1. `instance` (canvas click-to-edit overrides)
 *   2. `position` (first / last / nth / range)
 *   3. `pattern`  (odd / even / every-nth)
 *   4. `meta`     (sticky / featured / has-featured-image / author / taxonomy)
 *   5. `custom`   (`callback:<name>` → `apve_query_variant_match_<name>` filter)
 *   6. base post-template (no variant matched)
 *
 * Ties inside a tier break on `priority` ascending then document order.
 *
 * The precompiled map is consulted first for O(1) lookup of static
 * (position / pattern) rules — saves walking N variants per post. The
 * map is built editor-side via `compileStaticMap` and stored on the
 * parent `artisanpack/post-template` as `_compiledVariantMap`. When
 * the map is absent (older saves, hand-edited content, server-side
 * pattern expansion), the resolver falls back to walking variants and
 * evaluating their matchers directly.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

class VariantResolver
{
	protected const TIER_RANK = [
		'position' => 2,
		'pattern'  => 3,
		'meta'     => 4,
		'custom'   => 5,
	];

	protected const INSTANCE_TIER = 1;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	protected array $variants = [];

	/**
	 * Variants sorted once in {@see prime()} by precedence tier →
	 * priority → document order. Reused for every {@see resolve()}
	 * call on the same primed batch.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $sortedVariants = [];

	/**
	 * @var array<int, int>
	 */
	protected array $compiledMap = [];

	protected int $total = 0;

	/**
	 * Prime the resolver with the parsed variant blocks, the precompiled
	 * static map (if any), and the total result count. Called once per
	 * query expansion.
	 *
	 * @param  array<int, array<string, mixed>>  $variants     Raw variant block
	 *                                                         arrays, in their
	 *                                                         saved document order.
	 * @param  array<int|string, mixed>          $compiledMap  Position → variantId map
	 *                                                         from the post-template
	 *                                                         attrs.
	 */
	public function prime( array $variants, array $compiledMap, int $total ): void
	{
		$this->variants    = $this->describeVariants( $variants );
		$this->compiledMap = $this->normalizeMap( $compiledMap );
		$this->total       = $total;

		$this->sortedVariants = $this->variants;

		usort( $this->sortedVariants, function ( array $a, array $b ): int {
			$tierDiff = $a['tier'] - $b['tier'];

			if ( 0 !== $tierDiff ) {
				return $tierDiff;
			}

			$priorityDiff = $a['priority'] - $b['priority'];

			if ( 0 !== $priorityDiff ) {
				return $priorityDiff;
			}

			return $a['order'] - $b['order'];
		} );
	}

	/**
	 * Resolve the winning variant index (into the `variants` array
	 * primed via {@see prime()}) for one loop iteration. Returns
	 * `null` when no variant matches.
	 */
	public function resolve( int $index, object $post ): ?int
	{
		// Static map wins first — it's the editor's precompiled
		// answer for position / pattern rules. The map stores the
		// variant's document-order index, which the inliner uses to
		// read the original block array back.
		if ( isset( $this->compiledMap[ $index ] ) ) {
			$mapped = $this->compiledMap[ $index ];

			if ( $mapped >= 0 && $mapped < count( $this->variants ) ) {
				return $mapped;
			}
		}

		foreach ( $this->sortedVariants as $variant ) {
			if ( $this->matches( $variant, $index, $post ) ) {
				return $variant['order'];
			}
		}

		return null;
	}

	/**
	 * Convenience: read the inner-block tree off the resolved variant
	 * descriptor. Caller passes the original variant arrays; the
	 * descriptor remembers them via `order`.
	 *
	 * @param  array<int, array<string, mixed>>  $variants
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function innerBlocksFor( int $order, array $variants ): array
	{
		$block = $variants[ $order ] ?? null;

		if ( ! is_array( $block ) ) {
			return [];
		}

		$inner = $block['innerBlocks'] ?? [];

		return is_array( $inner ) ? $inner : [];
	}

	/**
	 * @param  array<int, array<string, mixed>>  $variants
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function describeVariants( array $variants ): array
	{
		$out = [];

		foreach ( array_values( $variants ) as $order => $variant ) {
			$attributes = isset( $variant['attributes'] ) && is_array( $variant['attributes'] )
				? $variant['attributes']
				: [];

			$matcher = isset( $attributes['matcher'] ) && is_array( $attributes['matcher'] )
				? $attributes['matcher']
				: [ 'kind' => 'position', 'value' => 'first' ];

			$kind = isset( $matcher['kind'] ) && is_string( $matcher['kind'] )
				? $matcher['kind']
				: 'position';

			$value = isset( $matcher['value'] ) && is_string( $matcher['value'] )
				? $matcher['value']
				: 'first';

			$priority = isset( $attributes['priority'] ) && is_numeric( $attributes['priority'] )
				? (int) $attributes['priority']
				: 10;

			$out[] = [
				'order'    => $order,
				'kind'     => $kind,
				'value'    => $value,
				'priority' => $priority,
				'tier'     => $this->tierFor( $kind, $value ),
			];
		}

		return $out;
	}

	protected function tierFor( string $kind, string $value ): int
	{
		if ( 'position' === $kind && str_starts_with( $value, 'instance:' ) ) {
			return self::INSTANCE_TIER;
		}

		return self::TIER_RANK[ $kind ] ?? 99;
	}

	/**
	 * @param  array<int|string, mixed>  $map
	 *
	 * @return array<int, int>
	 */
	protected function normalizeMap( array $map ): array
	{
		$out = [];

		foreach ( $map as $key => $value ) {
			if ( ! is_numeric( $key ) ) {
				continue;
			}

			if ( ! is_numeric( $value ) ) {
				continue;
			}

			$out[ (int) $key ] = (int) $value;
		}

		return $out;
	}

	/**
	 * @param  array<string, mixed>  $variant
	 */
	protected function matches( array $variant, int $index, object $post ): bool
	{
		$kind  = $variant['kind'];
		$value = $variant['value'];

		switch ( $kind ) {
			case 'position':
				return $this->matchesPosition( $value, $index );
			case 'pattern':
				return $this->matchesPattern( $value, $index );
			case 'meta':
				return $this->matchesMeta( $value, $post );
			case 'custom':
				return $this->matchesCustom( $value, $post, $index );
		}

		return false;
	}

	protected function matchesPosition( string $value, int $index ): bool
	{
		if ( str_starts_with( $value, 'instance:' ) ) {
			// `instance:<n1>` is treated as a fixed 1-based position
			// match when the editor's static map is absent.
			$raw = (int) substr( $value, strlen( 'instance:' ) );

			return $raw >= 1 && ( $raw - 1 ) === $index;
		}

		$position1 = $index + 1;

		if ( 'first' === $value ) {
			return 1 === $position1;
		}

		if ( 'last' === $value ) {
			return $this->total >= 1 && $position1 === $this->total;
		}

		if ( str_starts_with( $value, 'nth:' ) ) {
			$n = (int) substr( $value, 4 );

			return $n >= 1 && $position1 === $n;
		}

		if ( str_starts_with( $value, 'range:' ) ) {
			$range = substr( $value, 6 );
			$parts = explode( '-', $range );

			if ( 2 !== count( $parts ) ) {
				return false;
			}

			$from = (int) $parts[0];
			$to   = (int) $parts[1];

			return $from >= 1 && $to >= $from && $position1 >= $from && $position1 <= $to;
		}

		return false;
	}

	protected function matchesPattern( string $value, int $index ): bool
	{
		$position1 = $index + 1;

		if ( 'odd' === $value ) {
			return 1 === $position1 % 2;
		}

		if ( 'even' === $value ) {
			return 0 === $position1 % 2;
		}

		if ( str_starts_with( $value, 'every-nth:' ) ) {
			$parts = explode( ':', $value );
			$step  = isset( $parts[1] ) ? (int) $parts[1] : 0;

			if ( $step < 1 ) {
				return false;
			}

			$offset = $step;

			if ( ( $parts[2] ?? null ) === 'start' && isset( $parts[3] ) ) {
				$parsed = (int) $parts[3];

				if ( $parsed >= 1 ) {
					$offset = $parsed;
				}
			}

			return $position1 >= $offset && 0 === ( $position1 - $offset ) % $step;
		}

		return false;
	}

	protected function matchesMeta( string $value, object $post ): bool
	{
		if ( 'sticky' === $value ) {
			return true === ( $post->sticky ?? false ) || true === ( $post->is_sticky ?? false );
		}

		if ( 'featured' === $value ) {
			return true === ( $post->featured ?? false ) || true === ( $post->is_featured ?? false );
		}

		if ( 'has-featured-image' === $value ) {
			$candidates = [
				$post->featured_image_id ?? null,
				$post->featured_image ?? null,
				$post->thumbnail_id ?? null,
			];

			foreach ( $candidates as $candidate ) {
				if ( null === $candidate ) {
					continue;
				}

				if ( is_int( $candidate ) && $candidate > 0 ) {
					return true;
				}

				if ( is_object( $candidate ) ) {
					return true;
				}

				if ( is_string( $candidate ) && '' !== $candidate ) {
					return true;
				}
			}

			return false;
		}

		if ( str_starts_with( $value, 'author:' ) ) {
			$expected = (int) substr( $value, 7 );

			if ( $expected < 1 ) {
				return false;
			}

			$authorId = null;

			if ( isset( $post->author_id ) && is_numeric( $post->author_id ) ) {
				$authorId = (int) $post->author_id;
			} elseif ( isset( $post->user_id ) && is_numeric( $post->user_id ) ) {
				$authorId = (int) $post->user_id;
			} elseif ( isset( $post->author ) && is_object( $post->author ) && isset( $post->author->id ) ) {
				$authorId = (int) $post->author->id;
			}

			return $authorId === $expected;
		}

		if ( str_starts_with( $value, 'taxonomy:' ) ) {
			$parts = explode( ':', $value, 3 );

			if ( 3 !== count( $parts ) ) {
				return false;
			}

			[ , $taxonomy, $slug ] = $parts;

			if ( '' === $taxonomy || '' === $slug ) {
				return false;
			}

			return $this->postHasTerm( $post, $taxonomy, $slug );
		}

		return false;
	}

	protected function matchesCustom( string $value, object $post, int $index ): bool
	{
		if ( ! str_starts_with( $value, 'callback:' ) ) {
			return false;
		}

		$name = trim( substr( $value, 9 ) );

		if ( '' === $name || ! function_exists( 'ArtisanPackUI\\Hooks\\applyFilters' ) ) {
			return false;
		}

		$context = [
			'index' => $index,
			'total' => $this->total,
		];

		$result = \ArtisanPackUI\Hooks\applyFilters(
			'apve_query_variant_match_' . $name,
			false,
			$post,
			$context
		);

		return true === $result;
	}

	protected function postHasTerm( object $post, string $taxonomy, string $slug ): bool
	{
		// Map common taxonomy slugs to the loose relations posts expose.
		$relations = [];

		switch ( $taxonomy ) {
			case 'category':
				$relations[] = 'categories';
				break;
			case 'post_tag':
			case 'tag':
				$relations[] = 'tags';
				break;
			default:
				$relations[] = $taxonomy;
				$relations[] = $taxonomy . 's';
				break;
		}

		$relations[] = 'terms';

		foreach ( $relations as $relation ) {
			$collection = $post->{$relation} ?? null;

			if ( ! is_iterable( $collection ) ) {
				continue;
			}

			foreach ( $collection as $term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}

				$termTaxonomy = $term->taxonomy ?? null;

				if ( null !== $termTaxonomy && $termTaxonomy !== $taxonomy ) {
					continue;
				}

				$termSlug = $term->slug ?? null;

				if ( is_string( $termSlug ) && $termSlug === $slug ) {
					return true;
				}
			}
		}

		return false;
	}
}
