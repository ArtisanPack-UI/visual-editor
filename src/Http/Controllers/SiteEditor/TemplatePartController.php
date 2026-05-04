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
	 * @since 1.0.0
	 */
	public function show( string $slug ): JsonResponse
	{
		$resolved = $this->resolver->find( $slug );

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

		if ( array_key_exists( 'slug', $validated ) && $validated['slug'] !== $slug ) {
			return response()->json( [
				'message' => 'Body slug does not match URL slug.',
				'errors'  => [ 'slug' => [ 'Slug in the request body must match the URL slug.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		unset( $validated['slug'] );

		$model = self::CMS_TEMPLATE_PART_FQCN;
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
				if ( $this->isUniqueViolation( $e ) ) {
					$existing = $model::query()
						->where( 'theme', $theme )
						->where( 'slug', $slug )
						->firstOrFail();
					$existing->update( $this->modelAttributesFromRequest( $validated ) );
				} else {
					throw $e;
				}
			}
		} else {
			$existing->update( $this->modelAttributesFromRequest( $validated ) );
		}

		$this->refreshResolver();

		$resolved = $this->resolver->find( $slug );

		if ( ! $resolved instanceof ResolvedTemplatePart ) {
			return response()->json( [ 'message' => 'Template part not found.' ], Response::HTTP_NOT_FOUND );
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

		// Cast to string defensively — see {@see TemplateController::destroy()}.
		$theme = trim( (string) $request->query( 'theme', '' ) );

		if ( '' === $theme ) {
			return response()->json( [
				'message' => 'A theme is required to identify the template part.',
				'errors'  => [ 'theme' => [ 'The theme query parameter is required for site-editor deletes.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$model = self::CMS_TEMPLATE_PART_FQCN;

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

		if ( array_key_exists( 'content', $validated ) ) {
			$content                     = is_array( $validated['content'] ) ? $validated['content'] : [];
			$attributes['block_content'] = isset( $content['blocks'] ) && is_array( $content['blocks'] )
				? array_values( $content['blocks'] )
				: [];
		}

		return $attributes;
	}

	/**
	 * @since 1.0.0
	 */
	protected function isUniqueViolation( QueryException $e ): bool
	{
		$sqlState = (string) $e->getCode();

		if ( '23000' === $sqlState || '23505' === $sqlState ) {
			return true;
		}

		return str_contains( strtolower( $e->getMessage() ), 'unique' );
	}

	/**
	 * @since 1.0.0
	 */
	protected function refreshResolver(): void
	{
		$this->resolver = new TemplatePartResolver( applyFilters( 'ap.visual-editor.template-parts', [] ) );

		app()->instance( TemplatePartResolver::class, $this->resolver );
	}
}
