<?php

/**
 * Template controller — H6 site-editor.
 *
 * Wraps cms-framework's templates module in the WP REST `wp_template`
 * shape. Reads come through H5's {@see SiteEditorTemplateResolver}, which
 * has already merged static config + filter contributors (cms-framework
 * H1 registers itself there). Writes pass through to cms-framework's
 * `Template` model directly under a `class_exists` + service-binding
 * guard, returning 404 when cms-framework is not integrated. Per plan
 * 14 §2.1 the site-editor route is hard-coupled to cms-framework — the
 * 404 here surfaces that intentional asymmetry without crashing on
 * standalone visual-editor installs.
 *
 * Supersedes the plan 11 Phase D `TemplateController` that read/wrote
 * visual-editor's own `VisualEditorTemplate` model. Cleanup of the
 * orphaned model lands in a follow-up commit on this same H6 branch.
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

use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\StoreTemplateRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\UpdateTemplateRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\TemplateAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class TemplateController extends Controller
{
	/**
	 * Fully-qualified class name of cms-framework's `Template` model.
	 *
	 * Stored as a string so this controller compiles without
	 * cms-framework on the classpath. The `class_exists` gate in
	 * {@see cmsFrameworkAvailable()} resolves it at request time.
	 */
	protected const CMS_TEMPLATE_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\Template';

	/**
	 * Service binding cms-framework's H1 provider declares; its presence
	 * indicates the module's `boot()` has run and DB writes are safe.
	 *
	 * Combined with `class_exists`, this also rules out the test scenario
	 * where the class is autoloaded (because cms-framework is in
	 * `require-dev`) but the provider hasn't been booted — that's the
	 * correct moral equivalent of "cms-framework not installed" for
	 * standalone-install testing.
	 *
	 * Stored without a leading backslash so the string matches the
	 * `$app->bound()` lookup key, which PHP-DI normalizes to the bare FQCN.
	 */
	protected const CMS_RESOLVER_BINDING = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver';

	/**
	 * @since 1.0.0
	 */
	public function __construct( protected TemplateResolver $resolver )
	{
	}

	/**
	 * GET `/visual-editor/api/templates` — list resolved templates for the
	 * active theme. Returns a flat list keyed in slug-iteration order; no
	 * pagination wrapper, matching {@see TemplateAdapter::collection()}.
	 *
	 * @since 1.0.0
	 */
	public function index(): JsonResponse
	{
		$adapter = new TemplateAdapter();

		return response()->json( $adapter->collection( $this->resolver->all() ) );
	}

	/**
	 * GET `/visual-editor/api/templates/{slug}` — return a single template.
	 *
	 * @since 1.0.0
	 */
	public function show( string $slug ): JsonResponse
	{
		$resolved = $this->resolver->find( $slug );

		if ( ! $resolved instanceof ResolvedTemplate ) {
			return response()->json( [ 'message' => 'Template not found.' ], Response::HTTP_NOT_FOUND );
		}

		return response()->json( ( new TemplateAdapter() )->toArray( $resolved ) );
	}

	/**
	 * POST `/visual-editor/api/templates` — create a DB-stored template.
	 *
	 * Forwards the validated payload to cms-framework's `Template::create()`.
	 * On unique-constraint violation (slug already exists for the theme),
	 * surfaces a 409 with the same shape cms-framework's own controller
	 * uses, so consumers can handle the error consistently regardless of
	 * which surface they're talking to.
	 *
	 * Returns 404 when cms-framework is not integrated — the editor's
	 * site-editor route is hard-coupled to cms-framework per plan 14 §2.1.
	 *
	 * @since 1.0.0
	 */
	public function store( StoreTemplateRequest $request ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$model = self::CMS_TEMPLATE_FQCN;

		try {
			/** @var object $template */
			$template = $model::create( $this->modelAttributesFromRequest( $request->validated() ) );
		} catch ( QueryException $e ) {
			if ( $this->isUniqueViolation( $e ) ) {
				return response()->json( [
					'message' => 'A template with this slug already exists for the active theme.',
					'errors'  => [ 'slug' => [ 'Slug must be unique within the theme.' ] ],
				], Response::HTTP_CONFLICT );
			}

			throw $e;
		}

		// Re-resolve through H5 so the response reflects the merged
		// theme-file + DB-row view rather than the raw model state.
		$this->refreshResolver();

		$resolved = $this->resolver->find( (string) $template->slug );

		return response()->json(
			$resolved instanceof ResolvedTemplate
				? ( new TemplateAdapter() )->toArray( $resolved )
				: [ 'message' => 'Template created but could not be resolved.' ],
			Response::HTTP_CREATED,
		);
	}

	/**
	 * PUT `/visual-editor/api/templates/{slug}` — update or upsert a
	 * DB-stored template. Mirrors cms-framework's PUT semantics: route
	 * slug is canonical; body slug, if present, must match.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateTemplateRequest $request, string $slug ): JsonResponse
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

		$model = self::CMS_TEMPLATE_FQCN;
		$theme = (string) ( $validated['theme'] ?? '' );

		if ( '' === $theme ) {
			return response()->json( [
				'message' => 'A theme is required to identify the template.',
				'errors'  => [ 'theme' => [ 'The theme field is required for site-editor updates.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$existing = $model::query()->where( 'theme', $theme )->where( 'slug', $slug )->first();

		if ( null === $existing ) {
			$attributes         = $this->modelAttributesFromRequest( $validated );
			$attributes['slug'] = $slug;

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

		// The DB write succeeded; a post-write resolver miss shouldn't
		// surface as 404 — that would imply the update failed. Return
		// 200 with a fallback message so the client knows to refetch.
		// Mirrors {@see store()}'s post-create fallback.
		if ( ! $resolved instanceof ResolvedTemplate ) {
			return response()->json( [ 'message' => 'Template updated but could not be resolved.' ] );
		}

		return response()->json( ( new TemplateAdapter() )->toArray( $resolved ) );
	}

	/**
	 * DELETE `/visual-editor/api/templates/{slug}?theme={theme}` — revert
	 * to theme by deleting the DB override scoped to `(theme, slug)`. The
	 * `theme` query parameter is required because cms-framework's unique
	 * index is `(theme, slug)` — deleting by slug alone would collapse
	 * across themes and silently revert overrides the request never named.
	 * Returns 204 on delete, 404 when no override matches the pair, 422
	 * when `theme` is missing.
	 *
	 * @since 1.0.0
	 */
	public function destroy( Request $request, string $slug ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		// Cast to string defensively — Laravel returns an array if the
		// client sends `?theme[]=...`, which would error on `trim()`.
		$theme = trim( (string) $request->query( 'theme', '' ) );

		if ( '' === $theme ) {
			return response()->json( [
				'message' => 'A theme is required to identify the template.',
				'errors'  => [ 'theme' => [ 'The theme query parameter is required for site-editor deletes.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$model = self::CMS_TEMPLATE_FQCN;

		$deleted = (int) $model::query()
			->where( 'theme', $theme )
			->where( 'slug', $slug )
			->delete();

		if ( 0 === $deleted ) {
			return response()->json( [ 'message' => 'No template override to revert.' ], Response::HTTP_NOT_FOUND );
		}

		$this->refreshResolver();

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * cms-framework integration probe. Returns true only when cms-framework
	 * is on the classpath AND its SiteEditor provider has booted (the
	 * resolver binding is the canonical sentinel for "module is wired up").
	 *
	 * @since 1.0.0
	 */
	protected function cmsFrameworkAvailable(): bool
	{
		if ( ! class_exists( self::CMS_TEMPLATE_FQCN ) ) {
			return false;
		}

		return app()->bound( self::CMS_RESOLVER_BINDING );
	}

	/**
	 * Standardized 404 envelope when cms-framework isn't integrated. Keeps
	 * the message consistent across all H6 controllers so the install gate
	 * (H7) can match a single sentinel string.
	 *
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
	 * Translate the WP-shape validated input into cms-framework `Template`
	 * model attributes. Flattens the `content.{raw,blocks}` envelope into
	 * the single `block_content` column cms-framework's H1 stores.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated
	 *
	 * @return array<string, mixed>
	 */
	protected function modelAttributesFromRequest( array $validated ): array
	{
		$attributes = [];

		foreach ( [ 'slug', 'title', 'description', 'status', 'theme', 'is_custom' ] as $field ) {
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
	 * Detect a unique-constraint violation. Mirrors cms-framework's own
	 * detection so visual-editor surfaces the same 409 path regardless of
	 * which DB driver bubbles the error.
	 *
	 * MySQL/MariaDB report SQLSTATE 23000 + driver code 1062; PostgreSQL
	 * reports 23505; SQLite raises 23000 with 'UNIQUE constraint failed'
	 * in the message.
	 *
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
	 * Force the H5 resolver to re-read its filter sources after a write so
	 * the response reflects the new state. Without this the same singleton
	 * resolver returns the boot-time snapshot for the rest of the request.
	 *
	 * @since 1.0.0
	 */
	protected function refreshResolver(): void
	{
		$this->resolver = new TemplateResolver( applyFilters( 'ap.visual-editor.templates', [] ) );

		app()->instance( TemplateResolver::class, $this->resolver );
	}
}
