<?php

/**
 * BindingSources controller.
 *
 * Powers the editor's "link to data" inspector (#504): lists the
 * registered binding source drivers and, per source + resource, the
 * fields the picker can offer. The editor consumes these to render the
 * source dropdown, the field dropdown, and the empty-state hint.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BindingSourcesController extends Controller
{
	public function __construct(
		protected BlockBindingSourceRegistry $registry,
		protected ConfigRepository $config
	) {
	}

	/**
	 * List every registered binding source.
	 *
	 * @since 1.1.0
	 */
	public function index(): JsonResponse
	{
		$sources = array_map(
			static fn ( BlockBindingSource $source ): array => [
				'name' => $source->name(),
			],
			array_values( $this->registry->all() )
		);

		return response()->json( [
			'sources' => $sources,
		] );
	}

	/**
	 * Return the field catalog for one source against a resource slug.
	 *
	 * Query params:
	 *  - `resource` (optional) — slug from
	 *    `config('artisanpack.visual-editor.resources')`. Falls back to
	 *    an empty catalog when omitted or unknown.
	 *
	 * @since 1.1.0
	 */
	public function fields( Request $request, string $source ): JsonResponse
	{
		$driver = $this->registry->get( $source );

		if ( null === $driver ) {
			return response()->json( [
				'error'  => 'source_not_registered',
				'source' => $source,
			], 404 );
		}

		$resource   = (string) $request->query( 'resource', '' );
		$modelClass = $this->resolveModelClass( $resource );

		return response()->json( [
			'source'   => $driver->name(),
			'resource' => $resource,
			'fields'   => $driver->availableFields( $resource, $modelClass ),
		] );
	}

	/**
	 * Resolve the model class registered for a resource slug, or null
	 * when nothing matches. Reads the raw config rather than going
	 * through `ResourceResolver` so unknown / invalid entries return
	 * cleanly instead of throwing 404 — the picker treats "no model" as
	 * "no discoverable fields," not as an error.
	 *
	 * @since 1.1.0
	 *
	 * @return class-string<\Illuminate\Database\Eloquent\Model>|null
	 */
	protected function resolveModelClass( string $resource ): ?string
	{
		if ( '' === $resource ) {
			return null;
		}

		$resources = (array) $this->config->get( 'artisanpack.visual-editor.resources', [] );

		$class = $resources[ $resource ] ?? null;

		// Hard-gate non-Eloquent classes so a misconfigured resource
		// entry can't leak through to source drivers expecting a
		// `class-string<Model>`. The picker just returns an empty field
		// catalog for that resource — surfacing a 500 here would break
		// the inspector for every block.
		return is_string( $class )
			&& '' !== $class
			&& class_exists( $class )
			&& is_subclass_of( $class, Model::class )
				? $class
				: null;
	}
}
