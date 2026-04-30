<?php

/**
 * Server-rendered `core/archives` block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Core;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Renders monthly archive links for cms-framework's `Post` model,
 * matching upstream `core/archives` markup. Supports the upstream
 * `showLabel`, `showPostCounts`, and `displayAsDropdown` attributes.
 *
 * The archive URL pattern is `/blog/{year}/{month}` to match
 * cms-framework's blog defaults; hosts that route archives elsewhere
 * should override the `archive_url_pattern` config or rebind this
 * block via the dynamic-block registry.
 */
class ArchivesBlock extends DynamicBlock
{
	public function name(): string
	{
		return 'core/archives';
	}

	public function validateAttrs( array $attrs ): array
	{
		return [
			'showLabel'         => ! isset( $attrs['showLabel'] ) || (bool) $attrs['showLabel'],
			'showPostCounts'    => (bool) ( $attrs['showPostCounts'] ?? false ),
			'displayAsDropdown' => (bool) ( $attrs['displayAsDropdown'] ?? false ),
			'type'              => 'yearly' === ( $attrs['type'] ?? null ) ? 'yearly' : 'monthly',
			'className'         => isset( $attrs['className'] ) && is_string( $attrs['className'] ) ? $attrs['className'] : '',
		];
	}

	public function render( array $attrs ): string
	{
		$buckets = $this->fetchBuckets( $attrs['type'] )
			->map( fn ( array $bucket ): array => $this->decorate( $bucket ) );
		$classes = $this->wrapperClasses( $attrs );

		if ( $buckets->isEmpty() ) {
			return sprintf(
				'<div class="%s">%s</div>',
				e( implode( ' ', $classes ) ),
				e( __( 'No archives to show.' ) )
			);
		}

		if ( $attrs['displayAsDropdown'] ) {
			return $this->renderDropdown( $buckets, $classes, $attrs );
		}

		return $this->renderList( $buckets, $classes, $attrs );
	}

	/**
	 * Returns raw archive buckets sorted newest-first. Pulled out so
	 * tests can override the data source without exercising the database.
	 *
	 * @return Collection<int, array{year: int, month: int|null, count: int}>
	 */
	protected function fetchBuckets( string $type ): Collection
	{
		// Aggregating in PHP rather than SQL keeps this driver-agnostic
		// (SQLite uses `strftime`, MySQL uses `YEAR()`/`MONTH()`, Postgres
		// uses `EXTRACT`). For archive blocks the published-post count is
		// bounded enough that the round-trip is cheap; hosts with very
		// large datasets can rebind this block via the dynamic-block
		// registry to push the grouping into SQL.
		$timestamps = Post::query()
			->where( 'status', ContentStatus::Published->value )
			->whereNotNull( 'published_at' )
			->orderByDesc( 'published_at' )
			->pluck( 'published_at' );

		$buckets = $timestamps
			->map( static fn ( $value ): ?Carbon => $value instanceof Carbon ? $value : ( null === $value ? null : Carbon::parse( $value ) ) )
			->filter( static fn ( ?Carbon $date ): bool => null !== $date )
			->reduce( function ( Collection $carry, Carbon $date ) use ( $type ): Collection {
				$key = 'yearly' === $type
					? sprintf( '%04d', $date->year )
					: sprintf( '%04d-%02d', $date->year, $date->month );

				if ( ! $carry->has( $key ) ) {
					$carry->put( $key, [
						'year'  => $date->year,
						'month' => 'yearly' === $type ? null : $date->month,
						'count' => 0,
					] );
				}

				$bucket           = $carry->get( $key );
				$bucket['count'] += 1;
				$carry->put( $key, $bucket );

				return $carry;
			}, new Collection() )
			->sortKeysDesc()
			->values();

		return $buckets;
	}

	/**
	 * @param  array{year: int, month: int|null, count: int}  $bucket
	 *
	 * @return array{year: int, month: int|null, count: int, label: string, url: string}
	 */
	protected function decorate( array $bucket ): array
	{
		if ( null === $bucket['month'] ) {
			$bucket['label'] = (string) $bucket['year'];
			$bucket['url']   = url( sprintf( '/blog/%04d', $bucket['year'] ) );

			return $bucket;
		}

		$bucket['label'] = Carbon::create( $bucket['year'], $bucket['month'], 1 )->translatedFormat( 'F Y' );
		$bucket['url']   = url( sprintf( '/blog/%04d/%02d', $bucket['year'], $bucket['month'] ) );

		return $bucket;
	}

	/**
	 * @param  Collection<int, array{year: int, month: int|null, count: int, label: string, url: string}>  $buckets
	 * @param  array<int, string>                                                                          $classes
	 * @param  array<string, mixed>                                                                        $attrs
	 */
	protected function renderList( Collection $buckets, array $classes, array $attrs ): string
	{
		$classes[] = 'wp-block-archives-list';

		$items = $buckets->map( function ( array $bucket ) use ( $attrs ): string {
			$count = $attrs['showPostCounts']
				? sprintf( '&nbsp;(%d)', $bucket['count'] )
				: '';

			return sprintf(
				'<li><a href="%s">%s</a>%s</li>',
				e( $bucket['url'] ),
				e( $bucket['label'] ),
				$count
			);
		} )->implode( '' );

		return sprintf(
			'<ul class="%s">%s</ul>',
			e( implode( ' ', $classes ) ),
			$items
		);
	}

	/**
	 * @param  Collection<int, array{year: int, month: int|null, count: int, label: string, url: string}>  $buckets
	 * @param  array<int, string>                                                                          $classes
	 * @param  array<string, mixed>                                                                        $attrs
	 */
	protected function renderDropdown( Collection $buckets, array $classes, array $attrs ): string
	{
		$classes[] = 'wp-block-archives-dropdown';

		// Generate a per-render id so multiple `core/archives` blocks on the
		// same page don't share `id="wp-block-archives-dropdown"`. The label's
		// `for` attribute uses the same id so screen-reader association holds.
		$dropdownId = 'wp-block-archives-dropdown-' . uniqid();

		$labelMarkup = $attrs['showLabel']
			? sprintf( '<label class="screen-reader-text" for="%s">%s</label>', e( $dropdownId ), e( __( 'Archives' ) ) )
			: '';

		$options = $buckets->map( function ( array $bucket ) use ( $attrs ): string {
			$count = $attrs['showPostCounts']
				? sprintf( '&nbsp;(%d)', $bucket['count'] )
				: '';

			return sprintf(
				'<option value="%s">%s%s</option>',
				e( $bucket['url'] ),
				e( $bucket['label'] ),
				$count
			);
		} )->implode( '' );

		// The inline onchange mirrors upstream `core/archives` — selecting an
		// option navigates to the archive URL. Hosts under a strict CSP that
		// forbids inline handlers can rebind this block via the dynamic-block
		// registry to emit a data-attribute hook + a host-supplied script
		// instead.
		return sprintf(
			'<div class="%s">%s<select id="%s" onchange="if(this.value)document.location.href=this.value"><option value="">%s</option>%s</select></div>',
			e( implode( ' ', $classes ) ),
			$labelMarkup,
			e( $dropdownId ),
			e( __( 'Select Archive' ) ),
			$options
		);
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<int, string>
	 */
	protected function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-archives' ];

		if ( '' !== $attrs['className'] ) {
			$classes[] = $attrs['className'];
		}

		return $classes;
	}
}
