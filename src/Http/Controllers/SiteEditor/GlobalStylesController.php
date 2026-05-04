<?php

/**
 * GlobalStyles controller — H6 site-editor.
 *
 * Wraps cms-framework's H3 module in the WP REST `__unstableBase`
 * singleton shape. cms-framework stores one row per theme; visual-editor
 * exposes that as a singleton scoped to the active theme. The
 * `__base__` sentinel id surfaces when no DB override exists yet
 * (theme defaults are authoritative); a numeric id surfaces once the
 * user customizes.
 *
 * Endpoints:
 * - GET    `/global-styles/lookup`     — discover the current id
 * - GET    `/global-styles/base`       — theme defaults (read-only)
 * - GET    `/global-styles/{id}`       — fetch by id
 * - PUT    `/global-styles/{id}`       — update or upsert
 *
 * The `lookup` and `base` endpoints support the shim's discovery flow:
 * the editor calls `lookup` once at boot to learn the singleton id,
 * then uses normal entity fetching with that id. `base` returns the
 * theme defaults independently of the user customization, so the
 * variation picker can show "reset to theme" at any time without
 * requiring a separate fetch.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor;

use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\UpdateGlobalStylesRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\GlobalStylesAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedGlobalStyles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class GlobalStylesController extends Controller
{
	protected const CMS_GLOBAL_STYLES_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\GlobalStyles';

	protected const CMS_RESOLVER_BINDING = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\GlobalStylesResolver';

	/**
	 * Singleton sentinel id used in `lookup` + `show` when no DB row
	 * backs the active theme's global styles yet. Mirrors
	 * {@see GlobalStylesAdapter::SINGLETON_ID}.
	 */
	protected const SINGLETON_ID = '__base__';

	/**
	 * @since 1.0.0
	 */
	public function __construct( protected GlobalStylesResolver $resolver )
	{
	}

	/**
	 * GET `/global-styles/lookup` — return `{ id }` for the active theme's
	 * global styles, creating no DB row. The id is `__base__` when no
	 * customization exists yet, or numeric once the user has saved
	 * changes.
	 *
	 * @since 1.0.0
	 */
	public function lookup(): JsonResponse
	{
		$resolved = $this->resolver->get();

		// cms-framework's resolver carries `wp_id => 0` when no DB row
		// backs the active theme; treat both null and 0 as "use the
		// sentinel id". See {@see GlobalStylesAdapter::toArray()}.
		$id = $resolved instanceof ResolvedGlobalStyles
			&& null !== $resolved->wpId
			&& $resolved->wpId > 0
				? $resolved->wpId
				: self::SINGLETON_ID;

		return response()->json( [ 'id' => $id ] );
	}

	/**
	 * GET `/global-styles/base` — theme defaults, surfaced as the
	 * read-only baseline the inspector compares against. Always returns
	 * the resolver's view (file → DB merge); the front-end can compute
	 * a "modified vs theme" diff by comparing against this payload.
	 *
	 * @since 1.0.0
	 */
	public function base(): JsonResponse
	{
		$resolved = $this->resolver->get();

		if ( ! $resolved instanceof ResolvedGlobalStyles ) {
			return response()->json( [
				'id'         => self::SINGLETON_ID,
				'theme'      => '',
				'settings'   => new \stdClass(),
				'styles'     => new \stdClass(),
				'variations' => [],
			] );
		}

		return response()->json( ( new GlobalStylesAdapter() )->toArray( $resolved ) );
	}

	/**
	 * GET `/global-styles/{id}` — fetch the singleton by id. Accepts
	 * `__base__` (theme defaults) or a numeric DB id.
	 *
	 * @since 1.0.0
	 */
	public function show( string $id ): JsonResponse
	{
		$resolved = $this->resolver->get();

		if ( ! $resolved instanceof ResolvedGlobalStyles ) {
			return response()->json( [ 'message' => 'Global styles not found.' ], Response::HTTP_NOT_FOUND );
		}

		$expected = null !== $resolved->wpId && $resolved->wpId > 0
			? (string) $resolved->wpId
			: self::SINGLETON_ID;

		if ( $id !== $expected ) {
			return response()->json( [ 'message' => 'Global styles not found.' ], Response::HTTP_NOT_FOUND );
		}

		return response()->json( ( new GlobalStylesAdapter() )->toArray( $resolved ) );
	}

	/**
	 * PUT `/global-styles/{id}` — update or upsert the active theme's
	 * global styles. The `__base__` id means "no DB row yet" → upsert
	 * a new row; a numeric id means "update the existing row by id".
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateGlobalStylesRequest $request, string $id ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$validated = $request->validated();
		$theme     = $this->resolveTheme( $validated );

		if ( null === $theme ) {
			return response()->json( [
				'message' => 'A theme is required to identify the global styles record.',
				'errors'  => [ 'theme' => [ 'The theme field is required when no active theme can be derived from the resolver.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$model = self::CMS_GLOBAL_STYLES_FQCN;

		if ( self::SINGLETON_ID === $id ) {
			// Upsert the active theme's row. The unique index on `theme`
			// guarantees only one row per theme; a concurrent first-write
			// race resolves through `firstOrNew` + `save`.
			$record         = $model::firstOrNew( [ 'theme' => $theme ] );
			$record->theme  = $theme;
		} else {
			$record = $model::query()->whereKey( $id )->first();

			if ( null === $record ) {
				return response()->json( [ 'message' => 'Global styles not found.' ], Response::HTTP_NOT_FOUND );
			}
		}

		$this->applyValidatedAttributes( $record, $validated );
		$record->save();

		$this->refreshResolver();

		$resolved = $this->resolver->get();

		if ( ! $resolved instanceof ResolvedGlobalStyles ) {
			return response()->json( [ 'message' => 'Global styles saved but could not be resolved.' ], Response::HTTP_INTERNAL_SERVER_ERROR );
		}

		return response()->json( ( new GlobalStylesAdapter() )->toArray( $resolved ) );
	}

	/**
	 * Pull the theme out of the request body or fall back to the resolver's
	 * current theme. cms-framework's resolver always knows the active theme
	 * through its `ThemeManager` dependency, so this falls back gracefully.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated
	 */
	protected function resolveTheme( array $validated ): ?string
	{
		if ( isset( $validated['theme'] ) && is_string( $validated['theme'] ) && '' !== $validated['theme'] ) {
			return $validated['theme'];
		}

		$resolved = $this->resolver->get();

		if ( $resolved instanceof ResolvedGlobalStyles && '' !== $resolved->theme ) {
			return $resolved->theme;
		}

		return null;
	}

	/**
	 * Apply validated request fields to the model. `settings` and `styles`
	 * are array-cast columns so passing arrays is the correct write form.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated
	 */
	protected function applyValidatedAttributes( object $record, array $validated ): void
	{
		foreach ( [ 'title', 'variation' ] as $field ) {
			if ( array_key_exists( $field, $validated ) ) {
				$record->{$field} = $validated[ $field ];
			}
		}

		if ( array_key_exists( 'settings', $validated ) ) {
			$record->settings = is_array( $validated['settings'] ) ? $validated['settings'] : [];
		}

		if ( array_key_exists( 'styles', $validated ) ) {
			$record->styles = is_array( $validated['styles'] ) ? $validated['styles'] : [];
		}
	}

	/**
	 * @since 1.0.0
	 */
	protected function cmsFrameworkAvailable(): bool
	{
		if ( ! class_exists( self::CMS_GLOBAL_STYLES_FQCN ) ) {
			return false;
		}

		return app()->bound( self::CMS_RESOLVER_BINDING );
	}

	/**
	 * @since 1.0.0
	 */
	protected function cmsFrameworkUnavailable(): JsonResponse
	{
		return response()->json(
			[ 'message' => 'The site editor requires artisanpack-ui/cms-framework.' ],
			Response::HTTP_NOT_FOUND,
		);
	}

	/**
	 * Force the resolver singleton to re-read after a write so subsequent
	 * lookups in the same request reflect the new state.
	 *
	 * @since 1.0.0
	 */
	protected function refreshResolver(): void
	{
		$this->resolver = new GlobalStylesResolver( applyFilters( 'ap.visual-editor.global-styles', null ) );

		app()->instance( GlobalStylesResolver::class, $this->resolver );
	}
}
