<?php

/**
 * Visual-editor's contract for resolving a `core/query` block payload to
 * a paginated record set.
 *
 * Implemented by {@see Adapters\CmsFramework\CmsFrameworkQueryResolver}
 * when cms-framework is installed; host applications can bind any other
 * implementation if they ship their own query runtime. The visual-editor
 * service provider only registers the cms-framework adapter when
 * `ArtisanPackUI\CMSFramework\Modules\Blog\Services\QueryRuntime` is on
 * the autoloader, so `app()->bound(QueryResolverContract::class)` is the
 * canonical "do we have a query runtime" check from controller / inliner
 * code.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface QueryResolverContract
{
	/**
	 * Resolve the given normalized `core/query` attributes to a paginated
	 * Eloquent (or compatible) result set.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	public function resolve( array $attributes ): LengthAwarePaginator;
}
