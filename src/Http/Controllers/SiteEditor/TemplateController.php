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
	 * The route segment is named `{slug}` for parity with WP REST, but
	 * H6 ({@see TemplateAdapter::toArray()}) sets the response's `id`
	 * to `wpId ?? slug`, and the editor's `addEntities` registers
	 * `wp_template` with `key: 'id'`. So the URL parameter is whatever
	 * the adapter put into `id` — a numeric DB id when the template
	 * has a DB override, the slug for theme-only templates.
	 * {@see findTemplateByIdOrSlug()} handles both forms.
	 *
	 * @since 1.0.0
	 */
	public function show( string $slug ): JsonResponse
	{
		$resolved = $this->findTemplateByIdOrSlug( $slug );

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

		$model = self::CMS_TEMPLATE_FQCN;

		// H7 (#432). Numeric URL parameter → look up the row directly
		// by primary key (the row already knows its theme + slug). Slug
		// path keeps the existing `(theme, slug)` upsert behavior so
		// theme-only templates can be DB-overridden through a PUT.
		if ( ctype_digit( $slug ) ) {
			$existing = $model::query()->find( (int) $slug );

			if ( null === $existing ) {
				return response()->json(
					[ 'message' => 'Template not found.' ],
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
					if ( ! $this->isUniqueViolation( $e ) ) {
						throw $e;
					}

					// Race recovery: another request inserted between the
					// existence check and our create. Re-fetch the now-existing
					// row and re-apply the edits. If the lookup still misses
					// (genuine contradiction — unique violation said the row
					// exists, but our scoped query can't find it; e.g. the
					// other writer used a different theme), rethrow the
					// original exception rather than masking it as 404.
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

		$model = self::CMS_TEMPLATE_FQCN;

		// H7 (#432). Numeric URL parameter → primary-key delete (row
		// owns its theme already; no `?theme=` collision risk). Slug
		// path keeps the `?theme=` requirement so a multi-theme
		// override doesn't get collateral-deleted.
		if ( ctype_digit( $slug ) ) {
			$existing = $model::query()->find( (int) $slug );

			if ( null === $existing ) {
				return response()->json(
					[ 'message' => 'No template override to revert.' ],
					Response::HTTP_NOT_FOUND,
				);
			}

			$existing->delete();

			$this->refreshResolver();

			return response()->json( null, Response::HTTP_NO_CONTENT );
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
	/**
	 * Resolve a template through the H5 resolver by either DB id or
	 * slug. Numeric inputs scan {@see TemplateResolver::all()} for an
	 * entry whose `wpId` matches; non-numeric inputs fall through to
	 * the resolver's slug-keyed lookup.
	 *
	 * @since 1.0.0
	 */
	protected function findTemplateByIdOrSlug( string $input ): ?ResolvedTemplate
	{
		if ( ctype_digit( $input ) ) {
			$id = (int) $input;

			foreach ( $this->resolver->all() as $candidate ) {
				if ( $candidate instanceof ResolvedTemplate && $candidate->wpId === $id ) {
					return $candidate;
				}
			}

			return null;
		}

		$resolved = $this->resolver->find( $input );

		return $resolved instanceof ResolvedTemplate ? $resolved : null;
	}

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

		// See {@see TemplatePartController::modelAttributesFromRequest()}
		// for the rationale on why partial updates without `content.blocks`
		// must leave existing blocks intact.
		if ( isset( $validated['content']['blocks'] ) && is_array( $validated['content']['blocks'] ) ) {
			$attributes['block_content'] = array_values( $validated['content']['blocks'] );
		}

		return $attributes;
	}

	/**
	 * Detect a unique-constraint violation on a {@see QueryException}.
	 *
	 * PostgreSQL specifically reports SQLSTATE 23505 for unique
	 * violations. SQLSTATE 23000 is the SQL standard "integrity
	 * constraint violation" which covers FK / check / unique /
	 * not-null — too broad to assume unique. MySQL/MariaDB (driver
	 * code 1062) and SQLite both surface 23000 with a driver-specific
	 * message we can pattern-match against instead.
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
	 * Force the H5 resolver to re-read its filter sources after a write so
	 * the response reflects the new state. Without this the same singleton
	 * resolver returns the boot-time snapshot for the rest of the request.
	 *
	 * Mirrors the merge order used in
	 * {@see VisualEditorServiceProvider::registerSiteEditorResolvers()}:
	 * static config is the seed for `applyFilters`, then re-merged on top
	 * so app-level config wins on key collision.
	 *
	 * @since 1.0.0
	 */
	protected function refreshResolver(): void
	{
		$static = (array) config( 'artisanpack.visual-editor.site-editor.templates', [] );
		$merged = applyFilters( 'ap.visual-editor.templates', $static );
		$merged = is_array( $merged ) ? $merged : [];
		$merged = array_merge( $merged, $static );

		$this->resolver = new TemplateResolver( $merged );

		app()->instance( TemplateResolver::class, $this->resolver );
	}
}
