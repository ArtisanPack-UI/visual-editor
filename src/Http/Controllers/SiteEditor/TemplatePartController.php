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
	 * Honors the `area` query param (the navigator's Header / Footer /
	 * Sidebar / General filter chips). Without it the chips were
	 * cosmetic — every part showed under every chip (#438). Mirrors the
	 * `source` / `synced` filtering already in
	 * {@see PatternController::index()}.
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): JsonResponse
	{
		$parts = $this->resolver->all();

		$area = trim( (string) $request->query( 'area', '' ) );

		if ( '' !== $area ) {
			$parts = array_filter(
				$parts,
				static fn ( ResolvedTemplatePart $part ): bool => $part->area === $area,
			);
		}

		return response()->json( ( new TemplatePartAdapter() )->collection( $parts ) );
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

		$validated = $request->validated();

		// The site editor only ever resolves parts for the *active*
		// theme — cms-framework's resolver scopes its DB query to it. A
		// part created under any other theme is invisible to the
		// navigator and to save/load, so prefer the active theme here.
		// The request's `theme` (sourced from the mount point's stale
		// `data-theme` attribute) is only a fallback for standalone
		// installs with no ThemeManager bound. Mirrors the theme
		// inference applied to update()/destroy() in #438.
		//
		// `theme` is `sometimes` on the request (Keystone #55 —
		// Gutenberg's Create Overlay action POSTs without it), so we
		// have to guard the empty-string case before write.
		$validated['theme'] = $this->activeThemeSlug() ?? ( $validated['theme'] ?? '' );

		if ( '' === $validated['theme'] ) {
			return response()->json( [
				'message' => 'The theme field is required when no theme is active.',
				'errors'  => [ 'theme' => [ 'The theme field is required when no theme is active.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$model = self::CMS_TEMPLATE_PART_FQCN;

		// Idempotency for Gutenberg's Create Overlay action — re-clicking
		// the same nav block should NOT 409. Check for an existing
		// (theme, slug) row first and hand it back with 200 instead.
		// Mirrors WP core's REST behavior for re-creating identical
		// parts (Keystone #55).
		$existing = $model::query()
			->where( 'theme', $validated['theme'] )
			->where( 'slug', (string) ( $validated['slug'] ?? '' ) )
			->first();

		if ( null !== $existing ) {
			$this->refreshResolver();
			$resolved = $this->resolver->find( (string) $existing->slug );

			if ( $resolved instanceof ResolvedTemplatePart ) {
				return response()->json(
					( new TemplatePartAdapter() )->toArray( $resolved ),
					Response::HTTP_OK,
				);
			}
		}

		try {
			/** @var object $part */
			$part = $model::create( $this->modelAttributesFromRequest( $validated ) );
		} catch ( QueryException $e ) {
			if ( $this->isUniqueViolation( $e ) ) {
				// Race-safe idempotency. The pre-check above catches the
				// common case, but two concurrent Create Overlay clicks
				// (or any duplicate POST) can both clear the pre-check
				// and one of them collides on insert. Re-fetch the
				// existing row and return the same 200 envelope the
				// pre-check path would have surfaced (Keystone #55).
				$existing = $model::query()
					->where( 'theme', $validated['theme'] )
					->where( 'slug', (string) ( $validated['slug'] ?? '' ) )
					->first();

				if ( null !== $existing ) {
					$this->refreshResolver();
					$resolved = $this->resolver->find( (string) $existing->slug );

					if ( $resolved instanceof ResolvedTemplatePart ) {
						return response()->json(
							( new TemplatePartAdapter() )->toArray( $resolved ),
							Response::HTTP_OK,
						);
					}
				}

				return response()->json( [
					'message' => 'A template part with this slug already exists for the active theme.',
					'errors'  => [ 'slug' => [ 'Slug must be unique within the theme.' ] ],
				], Response::HTTP_CONFLICT );
			}

			throw $e;
		}

		$this->refreshResolver();

		$resolved = $this->resolver->find( (string) $part->slug );

		// The row was created but the resolver can't see it — a
		// server-side inconsistency, not a client error. Returning 201
		// with this body would hand the editor a record with no `id`,
		// which it then dereferences into `/template-parts/undefined`
		// (#438).
		if ( ! $resolved instanceof ResolvedTemplatePart ) {
			return response()->json(
				[ 'message' => 'Template part created but could not be resolved.' ],
				Response::HTTP_INTERNAL_SERVER_ERROR,
			);
		}

		return response()->json(
			( new TemplatePartAdapter() )->toArray( $resolved ),
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
		// part can be DB-overridden through a PUT. `0` is the file-only
		// sentinel and must fall through to the slug branch (#438).
		if ( ctype_digit( $slug ) && (int) $slug > 0 ) {
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
				$theme = (string) ( $this->activeThemeSlug() ?? '' );
			}

			if ( '' === $theme ) {
				return response()->json( [
					'message' => 'A theme is required to identify the template part.',
					'errors'  => [ 'theme' => [ 'The theme field is required for site-editor updates when no theme is active.' ] ],
				], Response::HTTP_UNPROCESSABLE_ENTITY );
			}

			// Persist the resolved theme back into `$validated` so
			// `modelAttributesFromRequest()` carries it into the create
			// payload. Mirrors the same fix in {@see TemplateController::update()}.
			$validated['theme'] = $theme;

			$existing = $model::query()->where( 'theme', $theme )->where( 'slug', $slug )->first();

			if ( null === $existing ) {
				// File→DB upsert: editor save payloads commonly omit
				// every field except `content`. Seed defaults from the
				// file-source resolved part (title, description, area,
				// etc.) before layering user-supplied changes on top,
				// so NOT NULL columns like `title` and `area` always
				// carry a value (#438).
				$fileDefaults = $this->fileSourceDefaults( $slug );

				$attributes = array_merge(
					$fileDefaults,
					$this->modelAttributesFromRequest( $validated ),
				);
				$attributes['slug']  = $slug;
				$attributes['theme'] = $theme;

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
		// apply. Slug path keeps the `?theme=` requirement. `0` is the
		// file-only sentinel and must fall through (#438).
		if ( ctype_digit( $slug ) && (int) $slug > 0 ) {
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
			$theme = (string) ( $this->activeThemeSlug() ?? '' );
		}

		if ( '' === $theme ) {
			return response()->json( [
				'message' => 'A theme is required to identify the template part.',
				'errors'  => [ 'theme' => [ 'The theme query parameter is required for site-editor deletes when no theme is active.' ] ],
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
		// `0` is the file-only sentinel and must fall through (#438).
		if ( ctype_digit( $input ) && (int) $input > 0 ) {
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
	 * Seed model attributes for a brand-new file→DB upsert from the
	 * file-source resolved part. Mirrors
	 * {@see TemplateController::fileSourceDefaults()} but additionally
	 * carries the `area` enum since template parts require it.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function fileSourceDefaults( string $slug ): array
	{
		$resolved = $this->resolver->find( $slug );

		if ( ! $resolved instanceof ResolvedTemplatePart ) {
			return [];
		}

		return [
			'title'       => $resolved->title,
			'description' => $resolved->description,
			'status'      => $resolved->status,
			'is_custom'   => $resolved->isCustom,
			'area'        => $resolved->area,
		];
	}

	/**
	 * Resolve the active theme slug through cms-framework's `ThemeManager`
	 * when available. Used as the fallback for slug-branch upserts and
	 * destroys when the request omits an explicit theme. Mirrors
	 * {@see TemplateController::activeThemeSlug()} and
	 * {@see GlobalStylesController::activeTheme()}.
	 *
	 * @since 1.0.0
	 */
	protected function activeThemeSlug(): ?string
	{
		$themeManagerFqcn = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

		if ( ! class_exists( $themeManagerFqcn ) || ! app()->bound( $themeManagerFqcn ) ) {
			return null;
		}

		$theme = app( $themeManagerFqcn )->getActiveTheme();

		if ( ! is_array( $theme ) || empty( $theme['slug'] ) || ! is_string( $theme['slug'] ) ) {
			return null;
		}

		return $theme['slug'];
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
		$merged = applyFilters( 'ap.visualEditor.templateParts', $static );
		$merged = is_array( $merged ) ? $merged : [];
		$merged = array_merge( $merged, $static );

		$this->resolver = new TemplatePartResolver( $merged );

		app()->instance( TemplatePartResolver::class, $this->resolver );
	}
}
