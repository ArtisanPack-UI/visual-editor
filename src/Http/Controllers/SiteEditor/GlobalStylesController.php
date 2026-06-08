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
use Illuminate\Database\QueryException;
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
	 * GET `/global-styles/base` — pristine theme defaults, surfaced as
	 * the read-only baseline the inspector compares against. Returns
	 * the active theme's `theme.json` `settings` + `styles` + declared
	 * `styles.variations` *without* any DB-row override applied. The
	 * front-end can compute a "modified vs theme" diff by comparing
	 * against this payload.
	 *
	 * Reads through cms-framework's `ThemeManager::getActiveTheme()`
	 * because the H5 resolver only exposes the merged (file → DB) view
	 * via `get()`. Returns empty defaults when cms-framework isn't
	 * integrated; matches the standalone-install fallback elsewhere in
	 * the H6 surface.
	 *
	 * @since 1.0.0
	 */
	public function base(): JsonResponse
	{
		$theme = $this->activeTheme();

		if ( null === $theme ) {
			return response()->json( [
				'id'         => self::SINGLETON_ID,
				'theme'      => '',
				'settings'   => new \stdClass(),
				'styles'     => new \stdClass(),
				'variations' => [],
			] );
		}

		$settings   = is_array( $theme['settings'] ?? null ) ? $theme['settings'] : [];
		$styles     = is_array( $theme['styles'] ?? null ) ? $theme['styles'] : [];
		$variations = is_array( $theme['styles']['variations'] ?? null ) ? array_values( $theme['styles']['variations'] ) : [];

		return response()->json( [
			'id'         => self::SINGLETON_ID,
			'theme'      => (string) ( $theme['slug'] ?? '' ),
			'settings'   => $settings,
			'styles'     => $styles,
			'variations' => $variations,
		] );
	}

	/**
	 * Resolve the active theme manifest through cms-framework's
	 * `ThemeManager` when available. Returns null when cms-framework
	 * isn't integrated or no theme is active.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>|null
	 */
	protected function activeTheme(): ?array
	{
		$themeManagerFqcn = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

		if ( ! class_exists( $themeManagerFqcn ) || ! app()->bound( $themeManagerFqcn ) ) {
			return null;
		}

		$theme = app( $themeManagerFqcn )->getActiveTheme();

		if ( ! is_array( $theme ) || empty( $theme['slug'] ) ) {
			return null;
		}

		return $theme;
	}

	/**
	 * GET `/global-styles/css` — full canvas stylesheet for the active
	 * theme. Concatenates two sources in order:
	 *
	 *   1. cms-framework's `GlobalStylesEmitter::emit()` — compiled CSS
	 *      from theme.json `settings` + `styles`, merged with any DB
	 *      override the user authored through the Styles section.
	 *   2. The theme's hand-authored `themes/{slug}/style.css` — the
	 *      same stylesheet the public front-end loads via `<link rel>`.
	 *
	 * Order matters: the emitter declares `--wp--preset--*` custom
	 * properties on `:root`; the hand-authored sheet can consume those
	 * tokens AND override emitter rules (button border-radius / padding
	 * that the emitter doesn't compile yet, footer list resets, etc.).
	 *
	 * The site-editor canvas appends the full response to its
	 * `BlockEditorProvider` `settings.styles` array so the iframe surface
	 * matches the public front-end's branding 1:1 — closes the parity
	 * gap that Keystone #47 surfaced. Front-end consumes the emitter
	 * via the renderer-blade Blade components and loads its own
	 * `<link rel="stylesheet">` to `style.css`; the canvas reaches
	 * parity by getting both bundled into this one fetch.
	 *
	 * Returns an empty `text/css` body when cms-framework is not
	 * installed; the canvas treats that the same as "no theme styles"
	 * and falls back to the package's `DEFAULT_CANVAS_STYLES`.
	 *
	 * @since 1.0.0
	 */
	public function css(): Response
	{
		$emitterFqcn = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Emission\\GlobalStylesEmitter';

		$emitted = '';

		if ( class_exists( $emitterFqcn ) && app()->bound( $emitterFqcn ) ) {
			$result  = app( $emitterFqcn )->emit();
			$emitted = is_string( $result ) ? $result : '';
		}

		$themeCss = $this->readThemeStylesheet();

		$body = '' === $themeCss
			? $emitted
			: rtrim( $emitted ) . "\n\n/* === theme stylesheet === */\n" . $themeCss;

		return response( $body, Response::HTTP_OK, [ 'Content-Type' => 'text/css; charset=utf-8' ] );
	}

	/**
	 * Read the active theme's hand-authored `style.css` from disk.
	 * Returns an empty string when no theme is active, when the file
	 * doesn't exist, or when the resolved path escapes the configured
	 * themes directory (path-traversal guard — the theme slug rides in
	 * from the DB / `theme.json`, but cheap to verify).
	 *
	 * @since 1.0.0
	 */
	protected function readThemeStylesheet(): string
	{
		$theme = $this->activeTheme();

		if ( null === $theme ) {
			return '';
		}

		$slug = (string) ( $theme['slug'] ?? '' );

		if ( '' === $slug || ! preg_match( '/^[A-Za-z0-9_-]+$/', $slug ) ) {
			return '';
		}

		$configured = (string) config( 'cms.themes.directory', 'themes' );
		// Honor absolute paths verbatim; otherwise resolve relative to
		// the app's base. cms-framework's `ThemeManager` follows the same
		// convention, so themes resolve to the same directory on disk
		// whether the host app keeps `themes/` inside the project root
		// or points at an external mount.
		$themesBase = ( '' !== $configured && ( '/' === $configured[0] || preg_match( '#^[A-Za-z]:[\\\\/]#', $configured ) ) )
			? $configured
			: base_path( $configured );
		$stylesheet = $themesBase . '/' . $slug . '/style.css';

		$resolved   = realpath( $stylesheet );
		$baseReal   = realpath( $themesBase );

		if ( false === $resolved || false === $baseReal || ! str_starts_with( $resolved, $baseReal . DIRECTORY_SEPARATOR ) ) {
			return '';
		}

		$contents = @file_get_contents( $resolved );

		return is_string( $contents ) ? $contents : '';
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
			// race could see two callers both find "no row" via
			// `firstOrNew` and both try to insert. Catch the unique
			// violation, reload the now-existing row, re-apply the
			// edits, and save — deterministic recovery so neither
			// caller sees a 500.
			$record        = $model::firstOrNew( [ 'theme' => $theme ] );
			$record->theme = $theme;

			$this->applyValidatedAttributes( $record, $validated );

			try {
				$record->save();
			} catch ( QueryException $e ) {
				if ( ! $this->isUniqueViolation( $e ) ) {
					throw $e;
				}

				// See {@see TemplateController::update()} for the race-recovery
				// rationale. Rethrow the original exception when the
				// post-violation lookup still misses.
				$record = $model::query()->where( 'theme', $theme )->first();

				if ( null === $record ) {
					throw $e;
				}

				$this->applyValidatedAttributes( $record, $validated );
				$record->save();
			}
		} else {
			// Numeric id — scope by both id AND theme so a malformed
			// request can't update one theme's row through another
			// theme's body. cms-framework's unique index guarantees one
			// row per theme so the (id, theme) pair is sufficient.
			$record = $model::query()
				->whereKey( $id )
				->where( 'theme', $theme )
				->first();

			if ( null === $record ) {
				return response()->json( [ 'message' => 'Global styles not found.' ], Response::HTTP_NOT_FOUND );
			}

			$this->applyValidatedAttributes( $record, $validated );
			$record->save();
		}

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
	 * Force the resolver singleton to re-read after a write so subsequent
	 * lookups in the same request reflect the new state.
	 *
	 * @since 1.0.0
	 */
	protected function refreshResolver(): void
	{
		$static = config( 'artisanpack.visual-editor.site-editor.global-styles' );
		$static = is_array( $static ) ? $static : null;
		$merged = applyFilters( 'ap.visual-editor.global-styles', $static );
		$merged = is_array( $merged ) ? $merged : $static;

		$this->resolver = new GlobalStylesResolver( $merged );

		app()->instance( GlobalStylesResolver::class, $this->resolver );
	}
}
