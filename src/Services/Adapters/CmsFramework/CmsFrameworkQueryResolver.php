<?php

/**
 * Adapter binding the visual-editor's {@see QueryResolverContract} to
 * cms-framework's `QueryRuntime` service.
 *
 * Registered in `VisualEditorServiceProvider::register()` only when the
 * cms-framework class is available on the autoloader, so a standalone
 * visual-editor install never tries to instantiate the missing class.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Adapters\CmsFramework;

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CmsFrameworkQueryResolver implements QueryResolverContract
{
	/**
	 * The wrapped cms-framework `QueryRuntime` instance. Typed as `object`
	 * so the visual-editor file does not import the cms-framework class
	 * statically — that import would soft-couple the package to
	 * cms-framework on every autoload.
	 */
	public function __construct( protected object $runtime ) {}

	public function resolve( array $attributes ): LengthAwarePaginator
	{
		return $this->runtime->resolve( $attributes );
	}
}
