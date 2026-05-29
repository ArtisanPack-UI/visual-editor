<?php

/**
 * Server-rendered `core/categories` block.
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

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders cms-framework post categories matching upstream
 * `core/categories` markup. Supports the upstream attributes the editor
 * exposes: `showHierarchy`, `showPostCounts`, `showOnlyTopLevel`,
 * `showEmpty`. `displayAsDropdown` falls back to the list rendering for
 * V1 — the upstream dropdown ships a JS handler we don't run server-side.
 */
class CategoriesBlock extends DynamicBlock
{
	public function name(): string
	{
		return 'artisanpack/categories';
	}

	public function validateAttrs( array $attrs ): array
	{
		return [
			'showHierarchy'     => (bool) ( $attrs['showHierarchy'] ?? false ),
			'showPostCounts'    => (bool) ( $attrs['showPostCounts'] ?? false ),
			'showOnlyTopLevel'  => (bool) ( $attrs['showOnlyTopLevel'] ?? false ),
			'showEmpty'         => (bool) ( $attrs['showEmpty'] ?? false ),
			'displayAsDropdown' => (bool) ( $attrs['displayAsDropdown'] ?? false ),
			'className'         => isset( $attrs['className'] ) && is_string( $attrs['className'] ) ? $attrs['className'] : '',
		];
	}

	public function render( array $attrs ): string
	{
		$categories = $this->fetchCategories( $attrs );

		if ( $categories->isEmpty() ) {
			return $this->emptyShell( $attrs, __( 'No categories' ) );
		}

		$items = $attrs['showHierarchy']
			? $this->renderHierarchy( $categories, null, $attrs )
			: $this->renderFlat( $categories, $attrs );

		$classes = $this->wrapperClasses( $attrs );

		return sprintf(
			'<ul class="%s">%s</ul>',
			e( implode( ' ', $classes ) ),
			$items
		);
	}

	/**
	 * Returns the category records used by {@see render()}. Pulled out so
	 * tests can override the data source without exercising the database.
	 *
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return Collection<int, object>
	 */
	protected function fetchCategories( array $attrs ): Collection
	{
		$query = PostCategory::query()->withCount( 'posts' );

		if ( $attrs['showOnlyTopLevel'] ) {
			$query->whereNull( 'parent_id' );
		}

		$query->orderBy( 'name' );

		$categories = $query->get();

		// Filtering empty categories post-hoc keeps the SQL portable.
		// `having` on a `withCount` alias works in MySQL but not in every
		// SQLite/Postgres configuration, so the in-memory filter is the
		// safer cross-database choice.
		if ( ! $attrs['showEmpty'] ) {
			$categories = $categories->filter(
				static fn ( object $category ): bool => (int) ( $category->posts_count ?? 0 ) > 0
			)->values();
		}

		return $categories;
	}

	/**
	 * @param  Collection<int, object>  $categories
	 * @param  array<string, mixed>     $attrs
	 */
	protected function renderFlat( Collection $categories, array $attrs ): string
	{
		return $categories->map(
			fn ( object $category ): string => $this->renderItem( $category, $attrs )
		)->implode( '' );
	}

	/**
	 * @param  Collection<int, object>  $categories
	 * @param  array<string, mixed>     $attrs
	 */
	protected function renderHierarchy( Collection $categories, ?int $parentId, array $attrs ): string
	{
		$children = $categories->where( 'parent_id', $parentId );

		if ( $children->isEmpty() ) {
			return '';
		}

		return $children->map( function ( object $category ) use ( $categories, $attrs ): string {
			$nested = $this->renderHierarchy( $categories, (int) $category->id, $attrs );

			$inner = $nested === ''
				? ''
				: sprintf( '<ul class="children">%s</ul>', $nested );

			return $this->renderItem( $category, $attrs, $inner );
		} )->implode( '' );
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderItem( object $category, array $attrs, string $extra = '' ): string
	{
		$count = $attrs['showPostCounts']
			? sprintf( ' (%d)', (int) ( $category->posts_count ?? 0 ) )
			: '';

		return sprintf(
			'<li class="cat-item cat-item-%d"><a href="%s">%s</a>%s%s</li>',
			(int) $category->id,
			e( (string) $category->permalink ),
			e( (string) $category->name ),
			$count,
			$extra
		);
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<int, string>
	 */
	protected function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-categories', 'wp-block-categories-list' ];

		if ( '' !== $attrs['className'] ) {
			$classes[] = $attrs['className'];
		}

		return $classes;
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function emptyShell( array $attrs, string $message ): string
	{
		return sprintf(
			'<ul class="%s"><li class="cat-item-empty">%s</li></ul>',
			e( implode( ' ', $this->wrapperClasses( $attrs ) ) ),
			e( $message )
		);
	}
}
