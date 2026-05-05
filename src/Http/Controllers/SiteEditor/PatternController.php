<?php

/**
 * Pattern controller — H6 site-editor.
 *
 * Wraps cms-framework's patterns module in the WP REST `wp_block` shape.
 * Theme patterns are file-only and read-only; user patterns are
 * DB-stored and writable. cms-framework's `BlockPattern::setSlugAttribute`
 * auto-prefixes user-source slugs with `user/` at storage (plan 14
 * §5.6) — visual-editor accepts the user-facing slug at the URL and
 * lets cms-framework handle the prefix transparently.
 *
 * Supersedes the plan 11 Phase D `PatternController` that read/wrote
 * visual-editor's own `VisualEditorPattern` model.
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

use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\StorePatternRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\UpdatePatternRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\PatternAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class PatternController extends Controller
{
	protected const CMS_PATTERN_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\BlockPattern';

	protected const CMS_RESOLVER_BINDING = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\PatternResolver';

	/**
	 * Storage prefix cms-framework applies to user-source slugs. Mirrored
	 * here as a string so this controller compiles without cms-framework
	 * on the classpath (the FQCN is resolved through the gate at request
	 * time).
	 */
	protected const USER_SLUG_PREFIX = 'user/';

	/**
	 * @since 1.0.0
	 */
	public function __construct( protected PatternResolver $resolver )
	{
	}

	/**
	 * GET `/visual-editor/api/patterns` — list theme + user patterns.
	 *
	 * Optional filters:
	 * - `?source=user|theme`
	 * - `?synced=1|0`
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): JsonResponse
	{
		$patterns = $this->resolver->all();

		$source = trim( (string) $request->query( 'source', '' ) );
		if ( '' !== $source ) {
			$patterns = array_filter( $patterns, static fn ( ResolvedPattern $p ) => $p->source === $source );
		}

		if ( $request->has( 'synced' ) ) {
			$wantSynced = $request->boolean( 'synced' );
			$patterns   = array_filter( $patterns, static fn ( ResolvedPattern $p ) => $p->synced === $wantSynced );
		}

		return response()->json( ( new PatternAdapter() )->collection( $patterns ) );
	}

	/**
	 * GET `/visual-editor/api/patterns/{slug}` — single pattern.
	 *
	 * The route segment is named `{slug}` for parity with WP REST,
	 * but H6 ({@see PatternAdapter::toArray()}) sets the response's
	 * `id` to `wpId ?? slug`, and the editor's `addEntities` registers
	 * `wp_block` with `key: 'id'`. So the URL parameter is whatever the
	 * adapter put into `id` — a numeric DB id for user patterns, the
	 * slug for theme patterns. {@see findPatternByIdOrSlug()} handles
	 * both forms.
	 *
	 * @since 1.0.0
	 */
	public function show( string $slug ): JsonResponse
	{
		$resolved = $this->findPatternByIdOrSlug( $slug );

		if ( ! $resolved instanceof ResolvedPattern ) {
			return response()->json( [ 'message' => 'Pattern not found.' ], Response::HTTP_NOT_FOUND );
		}

		return response()->json( ( new PatternAdapter() )->toArray( $resolved ) );
	}

	/**
	 * POST `/visual-editor/api/patterns` — create a user pattern.
	 *
	 * @since 1.0.0
	 */
	public function store( StorePatternRequest $request ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$model      = self::CMS_PATTERN_FQCN;
		$attributes = $this->modelAttributesFromRequest( $request->validated() );

		// Source defaults to `user`; theme writes are explicitly disallowed
		// by `StorePatternRequest::STORE_SOURCES`.
		$attributes['source'] = $attributes['source'] ?? 'user';

		try {
			/** @var object $pattern */
			$pattern = $model::create( $attributes );
		} catch ( QueryException $e ) {
			if ( $this->isUniqueViolation( $e ) ) {
				return response()->json( [
					'message' => 'A pattern with this slug already exists.',
					'errors'  => [ 'slug' => [ 'Slug must be unique within the source.' ] ],
				], Response::HTTP_CONFLICT );
			}

			throw $e;
		}

		$this->refreshResolver();

		$resolved = $this->findPattern( (string) $pattern->slug );

		return response()->json(
			$resolved instanceof ResolvedPattern
				? ( new PatternAdapter() )->toArray( $resolved )
				: [ 'message' => 'Pattern created but could not be resolved.' ],
			Response::HTTP_CREATED,
		);
	}

	/**
	 * PUT `/visual-editor/api/patterns/{slug}` — update a user pattern.
	 *
	 * Theme patterns are file-only — attempting to PUT to a theme slug
	 * returns 404, matching cms-framework's "no row exists for this
	 * source" semantics.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdatePatternRequest $request, string $slug ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$validated = $request->validated();

		$existing = $this->findPatternModelByIdOrSlug( $slug );

		if ( null === $existing ) {
			return response()->json( [ 'message' => 'Pattern not found.' ], Response::HTTP_NOT_FOUND );
		}

		if ( array_key_exists( 'slug', $validated ) && $this->normalizeUserSlug( $validated['slug'] ) !== $this->normalizeUserSlug( (string) $existing->slug ) ) {
			return response()->json( [
				'message' => 'Body slug does not match URL slug.',
				'errors'  => [ 'slug' => [ 'Slug in the request body must match the URL slug.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		unset( $validated['slug'] );

		$existing->update( $this->modelAttributesFromRequest( $validated ) );

		$this->refreshResolver();

		$resolved = $this->findPatternByIdOrSlug( $slug );

		// The DB write succeeded; if the post-write resolver re-lookup
		// can't find the record (stale filter contributor, slug-prefix
		// race, etc.) the response shouldn't claim 404 — that would
		// imply the update failed. Return 200 with a fallback message
		// so the client knows to refetch. Mirrors {@see store()}'s
		// post-create fallback. Same pattern applied across the H6
		// Template / TemplatePart controllers.
		if ( ! $resolved instanceof ResolvedPattern ) {
			return response()->json( [ 'message' => 'Pattern updated but could not be resolved.' ] );
		}

		return response()->json( ( new PatternAdapter() )->toArray( $resolved ) );
	}

	/**
	 * DELETE `/visual-editor/api/patterns/{slug}` — delete a user pattern.
	 *
	 * @since 1.0.0
	 */
	public function destroy( string $slug ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$existing = $this->findPatternModelByIdOrSlug( $slug );

		if ( null === $existing ) {
			return response()->json( [ 'message' => 'Pattern not found.' ], Response::HTTP_NOT_FOUND );
		}

		$existing->delete();

		$this->refreshResolver();

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * Look up a pattern through the H5 resolver. Tries the slug as-given
	 * first (matches theme patterns), then prefixed (matches user patterns
	 * when the URL was the user-facing form).
	 *
	 * @since 1.0.0
	 */
	protected function findPattern( string $slug ): ?ResolvedPattern
	{
		$resolved = $this->resolver->find( $slug );

		if ( $resolved instanceof ResolvedPattern ) {
			return $resolved;
		}

		$prefixed = $this->ensureUserPrefix( $slug );
		if ( $prefixed === $slug ) {
			return null;
		}

		$resolved = $this->resolver->find( $prefixed );

		return $resolved instanceof ResolvedPattern ? $resolved : null;
	}

	/**
	 * Resolve a pattern by either its DB id or its slug.
	 *
	 * H6's adapter stamps the response's `id` as `wpId ?? slug`, so the
	 * URL parameter the editor sends back is one of:
	 *   - a numeric DB id (user-source patterns), or
	 *   - the slug (theme patterns whose `wpId` is null).
	 *
	 * Numeric ids dispatch through a single resolver scan keyed on
	 * {@see ResolvedPattern::$wpId}; non-numeric input falls through to
	 * {@see findPattern()} which already handles the user-prefix dance.
	 *
	 * @since 1.0.0
	 */
	protected function findPatternByIdOrSlug( string $input ): ?ResolvedPattern
	{
		// `0` is the sentinel `wpId` for theme-source patterns — never a
		// valid DB id. Treating it as one would silently match the first
		// theme pattern, masking #438. Fall through to slug lookup.
		if ( ctype_digit( $input ) && (int) $input > 0 ) {
			$id = (int) $input;

			foreach ( $this->resolver->all() as $candidate ) {
				if ( $candidate instanceof ResolvedPattern && $candidate->wpId === $id ) {
					return $candidate;
				}
			}

			return null;
		}

		return $this->findPattern( $input );
	}

	/**
	 * Resolve a pattern model row by either id or slug, for write
	 * paths (update / destroy) that need the underlying Eloquent
	 * record. Mirrors {@see findPatternByIdOrSlug()} but bypasses the
	 * resolver because writes target the DB row directly.
	 *
	 * Returns null when the row doesn't exist or when cms-framework
	 * isn't booted (callers gate on
	 * {@see cmsFrameworkAvailable()} before invoking).
	 *
	 * @since 1.0.0
	 */
	protected function findPatternModelByIdOrSlug( string $input ): ?object
	{
		$model = self::CMS_PATTERN_FQCN;

		// `0` cannot match any real DB row; skip the lookup and fall
		// through to slug resolution (#438).
		if ( ctype_digit( $input ) && (int) $input > 0 ) {
			/** @var object|null */
			return $model::query()->find( (int) $input );
		}

		$storedSlug = $this->ensureUserPrefix( $input );

		/** @var object|null */
		return $model::query()->where( 'slug', $storedSlug )->first();
	}

	/**
	 * Idempotently apply the `user/` storage prefix.
	 *
	 * @since 1.0.0
	 */
	protected function ensureUserPrefix( string $slug ): string
	{
		return str_starts_with( $slug, self::USER_SLUG_PREFIX )
			? $slug
			: self::USER_SLUG_PREFIX . $slug;
	}

	/**
	 * Strip a leading `user/` prefix (used when comparing body slug vs URL
	 * slug — clients may send either form).
	 *
	 * @since 1.0.0
	 */
	protected function normalizeUserSlug( string $slug ): string
	{
		return str_starts_with( $slug, self::USER_SLUG_PREFIX )
			? substr( $slug, strlen( self::USER_SLUG_PREFIX ) )
			: $slug;
	}

	/**
	 * @since 1.0.0
	 */
	protected function cmsFrameworkAvailable(): bool
	{
		if ( ! class_exists( self::CMS_PATTERN_FQCN ) ) {
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

		foreach ( [ 'slug', 'title', 'description', 'source', 'synced', 'theme' ] as $field ) {
			if ( array_key_exists( $field, $validated ) ) {
				$attributes[ $field ] = $validated[ $field ];
			}
		}

		if ( array_key_exists( 'categories', $validated ) ) {
			$attributes['categories'] = is_array( $validated['categories'] )
				? array_values( array_filter( $validated['categories'], 'is_string' ) )
				: [];
		}

		if ( array_key_exists( 'block_types', $validated ) ) {
			$attributes['block_types'] = is_array( $validated['block_types'] )
				? array_values( array_filter( $validated['block_types'], 'is_string' ) )
				: [];
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
	 * @since 1.0.0
	 *
	 * @see TemplateController::isUniqueViolation() for the rationale.
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
		$static = (array) config( 'artisanpack.visual-editor.site-editor.patterns', [] );
		$merged = applyFilters( 'ap.visual-editor.patterns', $static );
		$merged = is_array( $merged ) ? $merged : [];
		$merged = array_merge( $merged, $static );

		$this->resolver = new PatternResolver( $merged );

		app()->instance( PatternResolver::class, $this->resolver );
	}
}
