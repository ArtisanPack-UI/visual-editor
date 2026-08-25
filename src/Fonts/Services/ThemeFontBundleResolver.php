<?php

/**
 * Theme font bundle resolver.
 *
 * Applies a theme's declared font bundle when the theme is activated. A theme
 * declares the fonts it depends on in a top-level `fonts` block of its
 * `theme.json` manifest — a list of `{provider, family, faces[]}` entries that
 * reference global Font Library families rather than shipping the files
 * themselves. On activation this resolver walks that block and, for each
 * declared font:
 *
 *   - links the existing library {@see Font} into a {@see ThemeFontBundle} row
 *     when the family is already installed, or
 *   - installs it through the appropriate provider first when it is missing —
 *     but only when network installs are confirmed (the `$installMissing`
 *     flag), since a fetch reaches out to a remote provider.
 *
 * Bundles are theme-scoped: they are persisted in `ve_theme_font_bundles` for
 * lookup and re-synced from the manifest on every activation, so re-activating
 * a theme is idempotent. Deleting a theme's bundle rows never touches the
 * global library — the schema cascades font → bundle, never bundle → font — so
 * switching or uninstalling a theme leaves every installed family intact for
 * the other themes that reference it.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Services;

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\ThemeFontBundle;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ThemeFontBundleResolver
{
	public function __construct(
		protected FontInstaller $installer,
		protected FontSourceRegistry $registry,
	) {
	}

	/**
	 * Read the `fonts` block from a `theme.json`-shape manifest and normalize it
	 * into a de-duplicated list of font-bundle declarations.
	 *
	 * Each returned declaration carries a lowercase `provider` key, the display
	 * `family`, its provider-scoped `slug` (the manifest's own `slug` when set,
	 * otherwise the slugified family), and a normalized `faces` list. Malformed
	 * entries — missing a provider or family, or not an array — are dropped, and
	 * a declaration that omits usable faces defaults to a single `400`/`normal`
	 * face so the bundle still records something installable. Entries are keyed
	 * by `provider:slug`, so a manifest that lists the same family twice keeps
	 * the last one.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, mixed>  $manifest  The decoded `theme.json` manifest.
	 *
	 * @return array<int, array{provider: string, family: string, slug: string, faces: array<int, array{weight: int, style: string}>}>
	 */
	public function parse( array $manifest ): array
	{
		$fonts = $manifest['fonts'] ?? null;

		if ( ! is_array( $fonts ) ) {
			return [];
		}

		$declarations = [];

		foreach ( $fonts as $entry ) {
			$declaration = $this->normalizeDeclaration( $entry );

			if ( null !== $declaration ) {
				$declarations[ $declaration['provider'] . ':' . $declaration['slug'] ] = $declaration;
			}
		}

		return array_values( $declarations );
	}

	/**
	 * Report which of a manifest's declared fonts are already in the library and
	 * which are missing, without fetching anything.
	 *
	 * The Font Library UI uses this to decide whether theme activation needs an
	 * admin confirmation before it reaches out to a provider: a non-empty
	 * `missing` list means {@see resolve()} would perform network installs.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, mixed>  $manifest  The decoded `theme.json` manifest.
	 *
	 * @return array{present: array<int, array<string, mixed>>, missing: array<int, array<string, mixed>>}
	 */
	public function plan( array $manifest ): array
	{
		$present = [];
		$missing = [];

		foreach ( $this->parse( $manifest ) as $declaration ) {
			if ( null !== $this->findLibraryFont( $declaration ) ) {
				$present[] = $declaration;
			} else {
				$missing[] = $declaration;
			}
		}

		return [ 'present' => $present, 'missing' => $missing ];
	}

	/**
	 * Apply a theme's font bundle from its manifest.
	 *
	 * The theme's existing bundle rows are cleared first and rebuilt from the
	 * manifest so re-activation stays idempotent; because the delete only
	 * touches `ve_theme_font_bundles`, the global library is left untouched.
	 * Each declared font is then linked when already installed, or installed and
	 * linked when missing and `$installMissing` is `true`. A missing font is
	 * skipped (recorded, not linked) when installs are not confirmed, and a
	 * provider failure on one font is logged and recorded without aborting the
	 * rest of the activation.
	 *
	 * @since 1.7.0
	 *
	 * @param  string                $themeSlug       The activating theme's slug.
	 * @param  array<string, mixed>  $manifest        The decoded `theme.json` manifest.
	 * @param  bool                  $installMissing  Whether to fetch missing families from
	 *                                                their provider (admin-confirmed network install).
	 *
	 * @return array{linked: array<int, array<string, mixed>>, installed: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>, failed: array<int, array{declaration: array<string, mixed>, error: string}>}
	 */
	public function resolve( string $themeSlug, array $manifest, bool $installMissing = false ): array
	{
		$themeSlug    = trim( $themeSlug );
		$declarations = $this->parse( $manifest );

		$result = [ 'linked' => [], 'installed' => [], 'skipped' => [], 'failed' => [] ];

		if ( '' === $themeSlug ) {
			return $result;
		}

		// Re-sync from the manifest: drop this theme's existing bundle rows so a
		// removed declaration does not linger. The cascade runs font → bundle,
		// so this never deletes a library Font other themes still reference.
		ThemeFontBundle::query()->where( 'theme_slug', $themeSlug )->delete();

		foreach ( $declarations as $declaration ) {
			$font = $this->findLibraryFont( $declaration );

			if ( null !== $font ) {
				$this->persistBundle( $themeSlug, $font, $declaration['faces'] );
				$result['linked'][] = $declaration;
				continue;
			}

			if ( ! $installMissing ) {
				$result['skipped'][] = $declaration;
				continue;
			}

			try {
				$font = $this->installer->install(
					$declaration['provider'],
					$declaration['slug'],
					$declaration['faces']
				);
			} catch ( Throwable $e ) {
				Log::warning( 'Failed to install a theme font bundle entry during activation.', [
					'theme'     => $themeSlug,
					'provider'  => $declaration['provider'],
					'slug'      => $declaration['slug'],
					'exception' => $e,
				] );

				$result['failed'][] = [ 'declaration' => $declaration, 'error' => $e->getMessage() ];
				continue;
			}

			$this->persistBundle( $themeSlug, $font, $declaration['faces'] );
			$result['installed'][] = $declaration;
		}

		return $result;
	}

	/**
	 * Remove a theme's font bundle rows, leaving every referenced library font
	 * in place. Used when a theme is uninstalled or deactivated.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $themeSlug  The theme whose bundles to forget.
	 *
	 * @return int The number of bundle rows removed.
	 */
	public function forgetTheme( string $themeSlug ): int
	{
		$themeSlug = trim( $themeSlug );

		if ( '' === $themeSlug ) {
			return 0;
		}

		return ThemeFontBundle::query()->where( 'theme_slug', $themeSlug )->delete();
	}

	/**
	 * Resolve the library {@see Font} a declaration references, or null when the
	 * family is not installed.
	 *
	 * @since 1.7.0
	 *
	 * @param  array{provider: string, slug: string}  $declaration
	 */
	protected function findLibraryFont( array $declaration ): ?Font
	{
		return Font::query()
			->where( 'provider', $declaration['provider'] )
			->where( 'slug', $declaration['slug'] )
			->first();
	}

	/**
	 * Record (or refresh) the theme's dependency on a library font, storing the
	 * faces the theme declared.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, array{weight: int, style: string}>  $faces
	 */
	protected function persistBundle( string $themeSlug, Font $font, array $faces ): ThemeFontBundle
	{
		return ThemeFontBundle::query()->updateOrCreate(
			[ 'theme_slug' => $themeSlug, 'font_id' => $font->id ],
			[ 'faces' => $faces ],
		);
	}

	/**
	 * Normalize one raw manifest `fonts` entry into a declaration, or null when
	 * it is unusable.
	 *
	 * @since 1.7.0
	 *
	 * @param  mixed  $entry
	 *
	 * @return array{provider: string, family: string, slug: string, faces: array<int, array{weight: int, style: string}>}|null
	 */
	protected function normalizeDeclaration( mixed $entry ): ?array
	{
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$provider = strtolower( trim( (string) ( $entry['provider'] ?? '' ) ) );
		$family   = trim( (string) ( $entry['family'] ?? '' ) );

		if ( '' === $provider || '' === $family ) {
			return null;
		}

		$slug = trim( (string) ( $entry['slug'] ?? '' ) );

		if ( '' === $slug ) {
			$slug = Str::slug( $family );
		}

		if ( '' === $slug ) {
			return null;
		}

		return [
			'provider' => $provider,
			'family'   => $family,
			'slug'     => $slug,
			'faces'    => $this->normalizeFaces( $entry['faces'] ?? [] ),
		];
	}

	/**
	 * Normalize a declaration's `faces` list into de-duplicated
	 * `{weight, style}` pairs, dropping malformed entries. A declaration that
	 * lists no usable face falls back to a single `400`/`normal` face so the
	 * bundle still references an installable variant.
	 *
	 * @since 1.7.0
	 *
	 * @param  mixed  $faces
	 *
	 * @return array<int, array{weight: int, style: string}>
	 */
	protected function normalizeFaces( mixed $faces ): array
	{
		if ( ! is_array( $faces ) ) {
			$faces = [];
		}

		$normalized = [];

		foreach ( $faces as $face ) {
			if ( ! is_array( $face ) || ! isset( $face['weight'] ) || ! is_numeric( $face['weight'] ) ) {
				continue;
			}

			$style = strtolower( trim( (string) ( $face['style'] ?? 'normal' ) ) );
			$style = 'italic' === $style ? 'italic' : 'normal';

			$normalized[ ( (int) $face['weight'] ) . ':' . $style ] = [
				'weight' => (int) $face['weight'],
				'style'  => $style,
			];
		}

		if ( [] === $normalized ) {
			return [ [ 'weight' => 400, 'style' => 'normal' ] ];
		}

		return array_values( $normalized );
	}
}
