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

use ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\Concerns\ReadsSiteEditorRouteInput;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\StoreTemplatePartRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\UpdateTemplatePartRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\TemplatePartAdapter;
use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use ArtisanPackUI\VisualEditor\SiteEditor\Previews\PatternPreviewCache;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\VisualEditor\Support\BlockContentGuard;
use ArtisanPackUI\VisualEditor\Support\ThemeBlockMarkup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class TemplatePartController extends Controller
{
	use ReadsSiteEditorRouteInput;

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
	 * Also honors `theme` + `slug` — the core-data shim's composite-id
	 * fallback (`wp_template_part` records are keyed by `theme//slug`)
	 * calls this endpoint with both params to resolve a single record,
	 * then reads `records[0]` from the response (#674). Without the
	 * filter the shim silently picked an arbitrary part, so
	 * `<!-- wp:template-part {"slug":"header","theme":"foo"} /-->`
	 * references inside templates mounted the wrong entity — or none —
	 * and the canvas showed a blank strip where the header/footer
	 * should have been.
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): JsonResponse
	{
		$parts = $this->resolver->all();

		$area = $this->stringQuery( $request, 'area' );

		if ( null === $area ) {
			return $this->invalidStringQuery( 'area' );
		}

		if ( '' !== $area ) {
			$parts = array_filter(
				$parts,
				static fn ( ResolvedTemplatePart $part ): bool => $part->area === $area,
			);
		}

		$theme = $this->stringQuery( $request, 'theme' );

		if ( null === $theme ) {
			return $this->invalidStringQuery( 'theme' );
		}

		if ( '' !== $theme ) {
			$parts = array_filter(
				$parts,
				static fn ( ResolvedTemplatePart $part ): bool => $part->theme === $theme,
			);
		}

		$slug = $this->stringQuery( $request, 'slug' );

		if ( null === $slug ) {
			return $this->invalidStringQuery( 'slug' );
		}

		if ( '' !== $slug ) {
			$parts = array_filter(
				$parts,
				static fn ( ResolvedTemplatePart $part ): bool => $part->slug === $slug,
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
	 * @since 1.20.0 Accepts a `<theme>~<slug>` id (#1094).
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

		// #832 — previews embedding this template part render stale otherwise.
		app( PatternPreviewCache::class )->flush();

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
	 * @since 1.20.0 Accepts a `<theme>~<slug>` id (#1094).
	 */
	public function update( UpdateTemplatePartRequest $request, string $slug ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$validated = $request->validated();

		$model = self::CMS_TEMPLATE_PART_FQCN;

		$requestTheme = (string) ( $validated['theme'] ?? '' );

		// A `<theme>~<slug>` id addresses a file-only record by slug, so
		// it takes the `(theme, slug)` upsert path in its own theme (#1094).
		$composite = $this->splitCompositeId( $slug );

		if ( null !== $composite ) {
			if ( '' !== $requestTheme && $requestTheme !== $composite['theme'] ) {
				return $this->compositeThemeMismatch();
			}

			$slug         = $composite['slug'];
			$requestTheme = $composite['theme'];

			// Only a record that theme has, as a row or as the active
			// theme's file, so another theme's id can't copy the active
			// theme's file into it (#1074).
			if ( ! $this->slugNamesTemplatePart( $slug, $requestTheme ) ) {
				return response()->json(
					[ 'message' => 'Template part not found.' ],
					Response::HTTP_NOT_FOUND,
				);
			}
		}

		$scopeTheme = '' !== $requestTheme ? $requestTheme : (string) ( $this->activeThemeSlug() ?? '' );

		// H7 (#432). Numeric URL parameter → primary-key update on
		// the row that already knows its `(theme, slug, area)`. Slug
		// path keeps the existing upsert behavior so a theme-only
		// part can be DB-overridden through a PUT. `0` is the file-only
		// sentinel and must fall through to the slug branch (#438).
		// All-digit input such as `404` can be a slug too (#1074): a row
		// id in the target theme wins, then a `(theme, slug)` match, and
		// an id in another theme only when the body names no theme.
		$existing = null === $composite && $this->couldBeRowId( $slug )
			? $this->numericUpdateTarget( $slug, $requestTheme, $scopeTheme )
			: false;

		if ( null === $existing ) {
			return response()->json(
				[ 'message' => 'Template part not found.' ],
				Response::HTTP_NOT_FOUND,
			);
		}

		if ( $existing instanceof Model ) {
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

			$existing->update( $this->modelAttributesFromRequest( $validated, $existing->block_content ) );

			$resolverKey = (string) $existing->slug;
		} else {
			if ( array_key_exists( 'slug', $validated ) && $validated['slug'] !== $slug ) {
				return response()->json( [
					'message' => 'Body slug does not match URL slug.',
					'errors'  => [ 'slug' => [ 'Slug in the request body must match the URL slug.' ] ],
				], Response::HTTP_UNPROCESSABLE_ENTITY );
			}

			unset( $validated['slug'] );

			$theme = $scopeTheme;

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
				$fileDefaults = $this->fileSourceDefaults( $slug, $theme );

				// Only the theme's own file seeds the row, so with no file
				// for this theme the title has to come from the request.
				if ( [] === $fileDefaults && ! array_key_exists( 'title', $validated ) ) {
					return response()->json( [
						'message' => __( 'A title is required to create a template part for this theme.' ),
						'errors'  => [ 'title' => [ __( 'The title field is required when the theme has no file for this slug.' ) ] ],
					], Response::HTTP_UNPROCESSABLE_ENTITY );
				}

				$attributes = array_merge(
					$fileDefaults,
					$this->modelAttributesFromRequest( $validated, $this->fileSourceBlocks( $slug, $theme ) ),
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

					$existing->update( $this->modelAttributesFromRequest( $validated, $existing->block_content ) );
				}
			} else {
				$existing->update( $this->modelAttributesFromRequest( $validated, $existing->block_content ) );
			}

			$resolverKey = $slug;
		}

		// #832 — previews embedding this template part render stale otherwise.
		app( PatternPreviewCache::class )->flush();

		$this->refreshResolver();

		$resolved = $this->resolver->find( $resolverKey );

		// The DB write succeeded; a post-write resolver miss shouldn't
		// surface as 404. See {@see TemplateController::update()} for
		// the full rationale.
		//
		// The resolver only reads the active theme, so a write to another
		// theme's row would otherwise answer with the active theme's
		// record of the same slug. Report the row that was written
		// instead (#1094).
		if ( ! $resolved instanceof ResolvedTemplatePart || $resolved->theme !== (string) $existing->theme ) {
			return response()->json( [
				'message' => 'Template part updated but could not be resolved.',
				'id'      => $existing->getKey(),
				'slug'    => (string) $existing->slug,
				'theme'   => (string) $existing->theme,
			] );
		}

		return response()->json( ( new TemplatePartAdapter() )->toArray( $resolved ) );
	}

	/**
	 * DELETE `/visual-editor/api/template-parts/{slug}?theme={theme}` —
	 * revert to theme by deleting the DB override scoped to `(theme, slug)`.
	 * Theme is required because cms-framework's unique index is
	 * `(theme, slug)`; see {@see TemplateController::destroy()} for the
	 * full rationale. Returns 409 when a `deleting` listener cancels the
	 * delete.
	 *
	 * @since 1.0.0
	 * @since 1.20.0 The slug path deletes through the model, so it fires
	 *               `deleting` / `deleted` like the numeric path.
	 *               Accepts a `<theme>~<slug>` id (#1094).
	 */
	public function destroy( Request $request, string $slug ): JsonResponse
	{
		if ( ! $this->cmsFrameworkAvailable() ) {
			return $this->cmsFrameworkUnavailable();
		}

		$model = self::CMS_TEMPLATE_PART_FQCN;

		$requestTheme = $this->stringQuery( $request, 'theme' );

		if ( null === $requestTheme ) {
			return $this->invalidStringQuery( 'theme' );
		}

		// A `<theme>~<slug>` id names its theme and slug, so it takes the
		// `(theme, slug)` path, never the row-id one (#1094).
		$composite = $this->splitCompositeId( $slug );

		if ( null !== $composite ) {
			if ( '' !== $requestTheme && $requestTheme !== $composite['theme'] ) {
				return $this->compositeThemeMismatch();
			}

			$slug         = $composite['slug'];
			$requestTheme = $composite['theme'];
		}

		$theme = '' !== $requestTheme ? $requestTheme : (string) ( $this->activeThemeSlug() ?? '' );

		// H7 (#432). Numeric URL parameter → primary-key delete; the
		// row owns its theme. `0` is the file-only sentinel and must
		// fall through (#438). An all-digit value such as `404`
		// can also be a slug (#1074).
		if ( null === $composite && $this->couldBeRowId( $slug ) ) {
			$byId   = $model::query()->find( (int) $slug );
			$bySlug = '' !== $theme
				? $model::query()->where( 'theme', $theme )->where( 'slug', $slug )->first()
				: null;

			if ( null !== $byId && '' !== $theme && $theme !== (string) $byId->theme ) {
				$byIdInTheme = null;
			} else {
				$byIdInTheme = $byId;
			}

			// A named theme marks a slug client, so its slug wins. Otherwise
			// the editor sent a row id: one in the active theme wins, then
			// the active theme's slug, then an id in another theme.
			$existing = '' !== $requestTheme
				? $bySlug ?? $byIdInTheme
				: $byIdInTheme ?? $bySlug ?? $byId;

			if ( null === $existing ) {
				return response()->json(
					[ 'message' => 'No template-part override to revert.' ],
					Response::HTTP_NOT_FOUND,
				);
			}

			return $this->revertOverride( $existing );
		}

		if ( '' === $theme ) {
			return response()->json( [
				'message' => 'A theme is required to identify the template part.',
				'errors'  => [ 'theme' => [ 'The theme query parameter is required for site-editor deletes when no theme is active.' ] ],
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		$existing = $model::query()
			->where( 'theme', $theme )
			->where( 'slug', $slug )
			->first();

		if ( null === $existing ) {
			return response()->json( [ 'message' => 'No template-part override to revert.' ], Response::HTTP_NOT_FOUND );
		}

		return $this->revertOverride( $existing );
	}

	/**
	 * Delete an override through its model so `deleting` / `deleted` fire,
	 * then report the revert on the same action cms-framework's resolvers
	 * use. Returns 204, or 409 when a `deleting` listener cancels the delete.
	 *
	 * @since 1.20.0
	 *
	 * @param  Model  $override  The cms-framework `TemplatePart` row.
	 *
	 * @return JsonResponse
	 */
	protected function revertOverride( Model $override ): JsonResponse
	{
		if ( true !== $override->delete() ) {
			return response()->json( [ 'message' => __( 'Template part override could not be reverted.' ) ], Response::HTTP_CONFLICT );
		}

		// #832 — previews embedding this template part render stale otherwise.
		app( PatternPreviewCache::class )->flush();

		$this->refreshResolver();

		doAction( 'ap.cmsFramework.siteEditor.reverted', 'template-part', $override->getAttribute( 'slug' ), $override->getAttribute( 'theme' ) );

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * @since 1.0.0
	 */
	/**
	 * Resolve a part through the H5 resolver by either DB id or slug.
	 * Mirrors {@see TemplateController::findTemplateByIdOrSlug()};
	 * numeric inputs scan the resolver's `all()` for a matching
	 * `wpId`, and slugs, or a numeric input no `wpId` matches, fall
	 * through to the slug-keyed lookup (#1074).
	 *
	 * @since 1.0.0
	 * @since 1.20.0 A numeric input no `wpId` matches is tried as a slug,
	 *               and a `<theme>~<slug>` id resolves by slug (#1094).
	 */
	protected function findTemplatePartByIdOrSlug( string $input ): ?ResolvedTemplatePart
	{
		// A `<theme>~<slug>` id names a file-only record by slug, never
		// a row (#1094). The resolver only reads the active theme, so the
		// id's theme has to be that one.
		$composite = $this->splitCompositeId( $input );

		if ( null !== $composite ) {
			$resolved = $this->resolver->find( $composite['slug'] );

			return $resolved instanceof ResolvedTemplatePart && $resolved->theme === $composite['theme'] ? $resolved : null;
		}

		// `0` is the file-only sentinel `wpId` and never a DB id (#438).
		if ( $this->couldBeRowId( $input ) ) {
			$id = (int) $input;

			foreach ( $this->resolver->all() as $candidate ) {
				if ( $candidate instanceof ResolvedTemplatePart && $candidate->wpId === $id ) {
					return $candidate;
				}
			}
		}

		$resolved = $this->resolver->find( $input );

		return $resolved instanceof ResolvedTemplatePart ? $resolved : null;
	}

	/**
	 * Whether an all-digit URL parameter names a part by slug, either as
	 * a DB override in the given theme or as a theme-file part, so it
	 * isn't mistaken for a row id (#1074). Mirrors
	 * {@see TemplateController::slugNamesTemplate()}.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $slug   The URL parameter.
	 * @param  string  $theme  The theme the request targets, or `''` when none.
	 *
	 * @return bool
	 */
	protected function slugNamesTemplatePart( string $slug, string $theme ): bool
	{
		if ( '' === $theme ) {
			return false;
		}

		$model = self::CMS_TEMPLATE_PART_FQCN;

		if ( $model::query()->where( 'theme', $theme )->where( 'slug', $slug )->exists() ) {
			return true;
		}

		// The resolver only reads the active theme, so its entry names the
		// slug only when that's the theme the request targets.
		$resolved = $this->resolver->find( $slug );

		return $resolved instanceof ResolvedTemplatePart && $resolved->theme === $theme;
	}

	/**
	 * Pick what a PUT to an all-digit URL value updates (#1074).
	 *
	 * The editor addresses a row by its id, but `404` can also be a slug.
	 * A row with that id in the target theme wins. Otherwise a slug the
	 * theme knows takes the `(theme, slug)` upsert path. A row with that
	 * id in another theme is used only when the body names no theme.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $input         The all-digit URL parameter.
	 * @param  string  $requestTheme  The body's `theme`, or `''` when absent.
	 * @param  string  $scopeTheme    The body's theme, else the active theme, else `''`.
	 *
	 * @return false|Model|null The `TemplatePart` row to update, false for the slug path, or null for a 404.
	 */
	protected function numericUpdateTarget( string $input, string $requestTheme, string $scopeTheme ): Model|false|null
	{
		$model = self::CMS_TEMPLATE_PART_FQCN;

		$byId = $model::query()->find( (int) $input );

		if ( null !== $byId && ( '' === $scopeTheme || $scopeTheme === (string) $byId->theme ) ) {
			return $byId;
		}

		if ( $this->slugNamesTemplatePart( $input, $scopeTheme ) ) {
			return false;
		}

		return '' === $requestTheme ? $byId : null;
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
	 * @since 1.20.0 Takes the target theme.
	 *
	 * @param  string  $slug   The template part slug.
	 * @param  string  $theme  The theme being written.
	 *
	 * @return array<string, mixed>
	 */
	protected function fileSourceDefaults( string $slug, string $theme ): array
	{
		$resolved = $this->resolver->find( $slug );

		// The resolver only reads the active theme; another theme's
		// upsert must not inherit that theme's file (#1074).
		if ( ! $resolved instanceof ResolvedTemplatePart || $resolved->theme !== $theme ) {
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
	 * The theme file's block tree for a slug, so a file→DB upsert keeps
	 * the Custom CSS the theme ships (#881).
	 *
	 * @since 1.15.0
	 * @since 1.20.0 Takes the target theme.
	 *
	 * @param  string  $slug   The template part slug.
	 * @param  string  $theme  The theme being written.
	 *
	 * @return array<int, mixed>|null
	 */
	protected function fileSourceBlocks( string $slug, string $theme ): ?array
	{
		$resolved = $this->resolver->find( $slug );

		return $resolved instanceof ResolvedTemplatePart && $resolved->theme === $theme ? $resolved->blocks : null;
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
	 * @since 1.15.0 Takes the stored blocks for the Custom CSS guard (#881).
	 *
	 * @param  array<string, mixed>  $validated
	 * @param  mixed  $storedBlocks  The record's stored block tree, or null for a new record.
	 *
	 * @return array<string, mixed>
	 */
	protected function modelAttributesFromRequest( array $validated, mixed $storedBlocks = null ): array
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
		} elseif ( isset( $validated['content'] ) && is_string( $validated['content'] ) ) {
			// Gutenberg's own create path (e.g. `core/navigation`'s
			// Create Overlay) sends `content` as a serialized markup
			// string. `StoreTemplatePartRequest` has accepted that shape
			// since Keystone #55, but it was never persisted — new
			// overlays landed empty (#809). A string is a full
			// replacement, so parse it into the stored tree.
			$blocks = ThemeBlockMarkup::parseToEditorBlocks( $validated['content'] );

			// The object path's `content.blocks` is bounded by
			// `TemplateBlockTreeRule` in the form request; the request
			// only sees this shape as an opaque string, so bound the
			// parsed tree here (depth / node count) — 422 on failure.
			Validator::make(
				[ 'content' => $blocks ],
				[ 'content' => [ new TemplateBlockTreeRule() ] ],
			)->validate();

			$attributes['block_content'] = $blocks;
		}

		// #881, #1062 — Custom CSS and unfiltered markup need their abilities.
		if ( array_key_exists( 'block_content', $attributes ) ) {
			$attributes['block_content'] = app( BlockContentGuard::class )->filter( $attributes['block_content'], $storedBlocks );
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
	 * @see TemplateController::refreshResolver() for the static-config
	 *      merge rationale.
	 */
	protected function refreshResolver(): void
	{
		$static = (array) config( 'artisanpack.visual-editor.site-editor.template-parts', [] );
		$merged = applyFilters( 'ap.visualEditor.templateParts', $static );
		$merged = is_array( $merged ) ? $merged : [];
		$merged = array_replace( $merged, $static );

		$this->resolver = new TemplatePartResolver( $merged );

		app()->instance( TemplatePartResolver::class, $this->resolver );
	}
}
