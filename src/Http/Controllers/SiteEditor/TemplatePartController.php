<?php

/**
 * TemplatePart controller — H6 site-editor.
 *
 * Wraps cms-framework's template-parts module in the WP REST
 * `wp_template_part` shape. Adds the closed-list `area` field on top
 * of {@see TemplateController}'s envelope; otherwise mirrors its read /
 * write contract behind the same `cmsFrameworkAvailable()` gate.
 *
 * Plan 14 §8 fixes the V1 area enum to `header | footer | sidebar |
 * general`; the form requests enforce the set, so this controller can
 * forward `area` to cms-framework without re-checking.
 *
 * Supersedes the plan 11 Phase D `TemplatePartController` that read /
 * wrote visual-editor's own `VisualEditorTemplatePart` model. Cleanup
 * of the orphaned model lands in a follow-up commit on this same H6
 * branch.
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

use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\StoreTemplatePartRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\UpdateTemplatePartRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\TemplatePartAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class TemplatePartController extends Controller
{
	protected const CMS_TEMPLATE_PART_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\TemplatePart';

	protected const CMS_RESOLVER_BINDING = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver';

	/**
	 * @since 1.0.0
	 */
	public function __construct( protected TemplatePartResolver $resolver )
	{
	}

	/**
	 * GET `/visual-editor/api/template-parts` — list parts for the active theme.
	 *
	 * @since 1.0.0
	 */
	public function index(): JsonResponse
	{
		$adapter = new TemplatePartAdapter();

		return response()->json( $adapter->collection( $this->resolver->all() ) );
	}

	/**
	 * GET `/visual-editor/api/template-parts/{slug}` — single part.
	 *
	 * H7 (#432). Same id-or-slug treatment as
	 * {@see TemplateController::show()} — the editor's `addEntities`
	 * registers `wp_template_part` with `key: 'id'` and the adapter
	 * sets `id = wpId ?? slug`, so the URL parameter can be either
	 * form. {@see findTemplatePartByIdOrSlug()} handles both.
	 *
	 * @since 1.0.0
	 */
	public function show( string $slug ): JsonResponse
	{
		$resolved = $this->findTemplatePartByIdOrSlug( $slug );

		if ( ! $resolved instanceof ResolvedTemplatePart ) {
			return response()->json( [ 'message' => 'Template part not found.' ], Response::HTTP_NOT_FOUND );
		}

		return response()->json( ( new TemplatePartAdapter() )->toArray( $resolved ) );
	}

	/**
	 * POST `/visual-editor/api/template-parts` — create a DB-stored part.
	 *
	 * @since 1.0.0
	 */
	public function store( StoreTemplatePartRequest $request ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$model = self::CMS_TEMPLATE_PART_FQCN;

		try {
			/** @var object $part */
			$part = $model::create( $this->modelAttributesFromRequest( $request->validated() ) );
		} catch ( QueryException $e ) {
			if ( $this->isUniqueViolation( $e ) ) {
				return response()->json( [
					'message' => 'A template part with this slug already exists for the active theme.',
					'errors'  => [ 'slug' => [ 'Slug must be unique within the theme.' ] ],
				], Response::HTTP_CONFLICT );
			}

			throw $e;
		}

		$this->refreshResolver();

		$resolved = $this->resolver->find( (string) $part->slug );

		return response()->json(
			$resolved instanceof ResolvedTemplatePart
				? ( new TemplatePartAdapter() )->toArray( $resolved )
				: [ 'message' => 'Template part created but could not be resolved.' ],
			Response::HTTP_CREATED,
		);
	}

	/**
	 * PUT `/visual-editor/api/template-parts/{slug}` — update or upsert.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateTemplatePartRequest $request, string $slug ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$validated = $request->validated();

		$model = self::CMS_TEMPLATE_PART_FQCN;

		// H7 (#432). Numeric URL parameter → primary-key update on
		// the row that already knows its `(theme, slug, area)`. Slug
		// path keeps the existing upsert behavior so a theme-only
		// part can be DB-overridden through a PUT.
		if ( ctype_digit( $slug ) ) {
			$existing = $model::query()->find( (int) $slug );

			if ( null === $existing ) {
				return response()->json(
					[ 'message' => 'Template part not found.' ],
					Response::HTTP_NOT_FOUND,
				);
			}

			if (
				array_key_exists( 'slug', $validated )
				&& $validated['slug'] !== (string) $existing->slug
			) {
				return response()->json( [
					'message' => 'Body slug does not match URL slug.',
					'errors'  => [ 'slug' => [ 'Slug in the request body must match the URL slug.' ] ],
				], Response::HTTP_UNPROCESSABLE_ENTITY );
			}

			unset( $validated['slug'] );

			$existing->update( $this->modelAttributesFromRequest( $validated ) );

			$resolverKey = (string) $existing->slug;
		} else {
			if ( array_key_exists( 'slug', $validated ) && $validated['slug'] !== $slug ) {
				return response()->json( [
					'message' => 'Body slug does not match URL slug.',
					'errors'  => [ 'slug' => [ 'Slug in the request body must match the URL slug.' ] ],
				], Response::HTTP_UNPROCESSABLE_ENTITY );
			}

			unset( $validated['slug'] );

			$theme = (string) ( $validated['theme'] ?? '' );

			if ( '' === $theme ) {
				return response()->json( [
					'message' => 'A theme is required to identify the template part.',
					'errors'  => [ 'theme' => [ 'The theme field is required for site-editor updates.' ] ],
				], Response::HTTP_UNPROCESSABLE_ENTITY );
			}

			$existing = $model::query()->where( 'theme', $theme )->where( 'slug', $slug )->first();

			if ( null === $existing ) {
				$attributes         = $this->modelAttributesFromRequest( $validated );
				$attributes['slug'] = $slug;

				// Area is required to create a new part record; the existing
				// row's area would otherwise survive the update branch.
				if ( ! array_key_exists( 'area', $attributes ) ) {
					return response()->json( [
						'message' => 'An area is required to upsert a new template part.',
						'errors'  => [ 'area' => [ 'The area field is required when creating a part.' ] ],
					], Response::HTTP_UNPROCESSABLE_ENTITY );
				}

				try {
					$existing = $model::create( $attributes );
				} catch ( QueryException $e ) {
					if ( ! $this->isUniqueViolation( $e ) ) {
						throw $e;
					}

					// See {@see TemplateController::update()} for the race-recovery
					// rationale. Rethrow the original exception when the
					// post-violation lookup still misses, rather than 404.
					$existing = $model::query()
						->where( 'theme', $theme )
						->where( 'slug', $slug )
						->first();

					if ( null === $existing ) {
						throw $e;
					}

					$existing->update( $this->modelAttributesFromRequest( $validated ) );
				}
			} else {
				$existing->update( $this->modelAttributesFromRequest( $validated ) );
			}

			$resolverKey = $slug;
		}

		$this->refreshResolver();

		$resolved = $this->resolver->find( $resolverKey );

		// The DB write succeeded; a post-write resolver miss shouldn't
		// surface as 404. See {@see TemplateController::update()} for
		// the full rationale.
		if ( ! $resolved instanceof ResolvedTemplatePart ) {
			return response()->json( [ 'message' => 'Template part updated but could not be resolved.' ] );
		}

		return response()->json( ( new TemplatePartAdapter() )->toArray( $resolved ) );
	}

	/**
	 * DELETE `/visual-editor/api/template-parts/{slug}?theme={theme}` —
	 * revert to theme by deleting the DB override scoped to `(theme, slug)`.
	 * Theme is required because cms-framework's unique index is
	 * `(theme, slug)`; see {@see TemplateController::destroy()} for the
	 * full rationale.
	 *
	 * @since 1.0.0
	 */
	public function destroy( Request $request, string $slug ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$model = self::CMS_TEMPLATE_PART_FQCN;

		// H7 (#432). Numeric URL parameter → primary-key delete; the
		// row owns its theme so the `?theme=` collision risk doesn't
		// apply. Slug path keeps the `?theme=` requirement.
		if ( ctype_digit( $slug ) ) {
			$existing = $model::query()->find( (int) $slug );

			if ( null === $existing ) {
				return response()->json(
					[ 'message' => 'No template-part override to revert.' ],
					Response::HTTP_NOT_FOUND,
				);
			}

			$existing->delete();

			$this->refreshResolver();

			return response()->json( null, Response::HTTP_NO_CONTENT );
		}

		// Cast to string defensively — see {@see TemplateController::destroy()}.
		$theme = trim( (string) $request->query( 'theme', '' ) );

		if ( '' === $theme ) {
			return response()->json( [
				'message' => 'A theme is required to identify the template part.',
				'errors'  => [ 'theme' => [ 'The theme query parameter is required for site-editor deletes.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$deleted = (int) $model::query()
			->where( 'theme', $theme )
			->where( 'slug', $slug )
			->delete();

		if ( 0 === $deleted ) {
			return response()->json( [ 'message' => 'No template-part override to revert.' ], Response::HTTP_NOT_FOUND );
		}

		$this->refreshResolver();

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * @since 1.0.0
	 */
	/**
	 * Resolve a part through the H5 resolver by either DB id or slug.
	 * Mirrors {@see TemplateController::findTemplateByIdOrSlug()};
	 * numeric inputs scan the resolver's `all()` for a matching
	 * `wpId`, slugs fall through to the slug-keyed lookup.
	 *
	 * @since 1.0.0
	 */
	protected function findTemplatePartByIdOrSlug( string $input ): ?ResolvedTemplatePart
	{
		if ( ctype_digit( $input ) ) {
			$id = (int) $input;

			foreach ( $this->resolver->all() as $candidate ) {
				if ( $candidate instanceof ResolvedTemplatePart && $candidate->wpId === $id ) {
					return $candidate;
				}
			}

			return null;
		}

		$resolved = $this->resolver->find( $input );

		return $resolved instanceof ResolvedTemplatePart ? $resolved : null;
	}

	protected function cmsFrameworkAvailable(): bool
	{
		if ( ! class_exists( self::CMS_TEMPLATE_PART_FQCN ) ) {
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
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated
	 *
	 * @return array<string, mixed>
	 */
	protected function modelAttributesFromRequest( array $validated ): array
	{
		$attributes = [];

		foreach ( [ 'slug', 'title', 'description', 'status', 'theme', 'is_custom', 'area' ] as $field ) {
			if ( array_key_exists( $field, $validated ) ) {
				$attributes[ $field ] = $validated[ $field ];
			}
		}

		// Only touch `block_content` when the payload explicitly carries
		// `content.blocks`. A partial update like `{ title: 'Renamed' }`
		// or `{ content: { raw: '...' } }` (no blocks key) leaves the
		// existing blocks intact — without this guard, any PUT that
		// happens to include `content` without `blocks` would wipe the
		// stored tree. Explicit empty (`content: { blocks: [] }`) still
		// clears, since the key is present.
		if ( isset( $validated['content']['blocks'] ) && is_array( $validated['content']['blocks'] ) ) {
			$attributes['block_content'] = array_values( $validated['content']['blocks'] );
		}

		return $attributes;
	}

	/**
	 * Detect a unique-constraint violation on a {@see QueryException}.
	 *
	 * See {@see TemplateController::isUniqueViolation()} for the full
	 * rationale on why SQLSTATE 23000 alone is too broad.
	 *
	 * @since 1.0.0
	 */
	protected function isUniqueViolation( QueryException $e ): bool
	{
		if ( '23505' === (string) $e->getCode() ) {
			return true;
		}

		$message = strtolower( $e->getMessage() );

		return str_contains( $message, 'unique' )
			|| str_contains( $message, 'duplicate entry' );
	}

	/**
	 * @since 1.0.0
	 *
	 * @see TemplateController::refreshResolver() for the static-config
	 *      merge rationale.
	 */
	protected function refreshResolver(): void
	{
		$static = (array) config( 'artisanpack.visual-editor.site-editor.template-parts', [] );
		$merged = applyFilters( 'ap.visual-editor.template-parts', $static );
		$merged = is_array( $merged ) ? $merged : [];
		$merged = array_merge( $merged, $static );

		$this->resolver = new TemplatePartResolver( $merged );

		app()->instance( TemplatePartResolver::class, $this->resolver );
	}
}
