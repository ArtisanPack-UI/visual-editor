<?php

/**
 * Font install/uninstall orchestrator.
 *
 * Drives the full self-hosting pipeline for a font family: resolve the
 * provider from the {@see FontSourceRegistry}, fetch each requested face's
 * bytes, write them to disk through {@see FontFileWriter}, record the
 * {@see Font} + {@see FontFace} rows, and rebuild the `fonts.css` bundle via
 * {@see FontsCssGenerator}.
 *
 * The pipeline is transactional. Face files are fetched and written before the
 * short DB transaction that records them; if either phase fails, every file
 * this call newly created is deleted and the DB rows roll back, so a partial
 * install never leaves stray files or half-registered fonts behind. Re-fetched
 * faces that already existed on disk are left in place on rollback — their
 * committed rows still reference them.
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

use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontFileWriteException;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontInstallationException;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;
use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FontInstaller
{
	public function __construct(
		protected FontSourceRegistry $registry,
		protected FontFileWriter $fileWriter,
		protected FontsCssGenerator $cssGenerator,
	) {
	}

	/**
	 * Install a family's selected faces from a provider, self-hosting each file
	 * and rebuilding the `fonts.css` bundle.
	 *
	 * Re-installing a family merges the new faces into the existing
	 * {@see Font}: faces already present are refreshed, new weights/styles are
	 * added, and untouched faces are left alone.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $providerKey  The provider registry key (`google`, `bunny`, …).
	 * @param  string  $slug         The provider-scoped family slug.
	 * @param  array<int, array{weight: int|string, style?: string}>  $faces  The faces to install.
	 *
	 * @return Font The installed (or merged) font, with its faces loaded.
	 */
	public function install( string $providerKey, string $slug, array $faces ): Font
	{
		$provider = $this->registry->get( $providerKey );

		if ( null === $provider ) {
			throw new FontInstallationException( sprintf(
				'No font source is registered under the key "%s".',
				$providerKey
			) );
		}

		if ( ! $provider->isSelfHostable() ) {
			throw new FontInstallationException( sprintf(
				'The "%s" font source is not self-hostable and cannot be installed.',
				$provider->label()
			) );
		}

		$family = $provider->getFamily( $slug );

		if ( null === $family ) {
			throw new FontInstallationException( sprintf(
				'The "%s" font source has no family for the slug "%s".',
				$provider->label(),
				$slug
			) );
		}

		$requested = $this->normalizeRequestedFaces( $faces );

		if ( [] === $requested ) {
			throw new FontInstallationException(
				'At least one face must be selected to install a font.'
			);
		}

		$rollbackPaths = [];
		$writtenFaces  = [];

		try {
			foreach ( $requested as $face ) {
				[ $weight, $style ] = $face;

				$path  = $this->fileWriter->pathFor( $providerKey, $slug, $weight, $style, 'woff2' );
				$isNew = ! $this->fileWriter->exists( $path );

				$bytes = $provider->fetchFace( $slug, (string) $weight, $style );
				$this->fileWriter->write( $providerKey, $slug, $weight, $style, 'woff2', $bytes );

				if ( $isNew ) {
					$rollbackPaths[] = $path;
				}

				$writtenFaces[] = [
					'weight'    => $weight,
					'style'     => $style,
					'format'    => 'woff2',
					'path'      => $path,
					'file_size' => strlen( $bytes ),
					'axes'      => ( $family['is_variable'] ?? false ) ? ( $family['axes'] ?? null ) : null,
				];
			}

			$font = DB::transaction( function () use ( $providerKey, $slug, $family, $writtenFaces ): Font {
				$font = Font::query()->firstOrNew( [
					'provider' => $providerKey,
					'slug'     => $slug,
				] );

				$font->family       = (string) $family['family'];
				$font->is_variable  = (bool) ( $family['is_variable'] ?? false );
				$font->license      = $family['license'] ?? null;
				$font->source_url   = $family['source_url'] ?? $font->source_url;
				$font->installed_at = $font->installed_at ?? now();
				$font->save();

				foreach ( $writtenFaces as $face ) {
					$font->faces()->updateOrCreate(
						[
							'weight' => $face['weight'],
							'style'  => $face['style'],
						],
						[
							'format'    => $face['format'],
							'disk'      => $this->fileWriter->diskName(),
							'path'      => $face['path'],
							'file_size' => $face['file_size'],
							'axes'      => $face['axes'],
						]
					);
				}

				return $font;
			} );
		} catch ( Throwable $e ) {
			$this->fileWriter->delete( $rollbackPaths );

			if ( $e instanceof FontInstallationException
				|| $e instanceof FontProviderException
				|| $e instanceof FontFileWriteException ) {
				throw $e;
			}

			throw new FontInstallationException(
				sprintf( 'Failed to install "%s": %s', $family['family'], $e->getMessage() ),
				0,
				$e
			);
		}

		$this->regenerate();

		return $font->load( 'faces' );
	}

	/**
	 * Uninstall a single font: delete its rows (faces cascade), remove its
	 * files, and rebuild the bundle.
	 *
	 * @since 1.7.0
	 */
	public function uninstall( Font $font ): void
	{
		$paths = $font->faces()->pluck( 'path' )->all();

		DB::transaction( static function () use ( $font ): void {
			$font->delete();
		} );

		$this->fileWriter->delete( $paths );
		$this->regenerate();
	}

	/**
	 * Uninstall several fonts at once, rebuilding the bundle a single time.
	 *
	 * Accepts {@see Font} models or their integer ids; unknown ids are skipped.
	 *
	 * @since 1.7.0
	 *
	 * @param  iterable<int, Font|int>  $fonts  The fonts (or ids) to uninstall.
	 *
	 * @return int The number of fonts removed.
	 */
	public function bulkUninstall( iterable $fonts ): int
	{
		$ids   = [];
		$paths = [];

		foreach ( $fonts as $font ) {
			$model = $font instanceof Font ? $font : Font::query()->with( 'faces' )->find( $font );

			if ( null === $model ) {
				continue;
			}

			$ids     = array_merge( $ids, [ $model->id ] );
			$paths   = array_merge( $paths, $model->faces->pluck( 'path' )->all() );
		}

		$ids = array_values( array_unique( $ids ) );

		if ( [] === $ids ) {
			return 0;
		}

		DB::transaction( static function () use ( $ids ): void {
			Font::query()->whereIn( 'id', $ids )->delete();
		} );

		$this->fileWriter->delete( $paths );
		$this->regenerate();

		return count( $ids );
	}

	/**
	 * Rebuild the `fonts.css` bundle after a committed install or uninstall.
	 *
	 * Regeneration runs after the DB rows and files are already persisted, so a
	 * failure here does not mean the mutation failed. The generator writes
	 * atomically, leaving the previous bundle intact; log the stale bundle and
	 * return rather than surfacing a false install/uninstall failure — the next
	 * mutation regenerates it.
	 *
	 * @since 1.7.0
	 */
	protected function regenerate(): void
	{
		try {
			$this->cssGenerator->generate();
		} catch ( Throwable $e ) {
			Log::error(
				'Failed to regenerate the Font Library fonts.css bundle after a font change.',
				[ 'exception' => $e ]
			);
		}
	}

	/**
	 * Normalize the requested-face payload into a de-duplicated list of
	 * `[ int $weight, string $style ]` pairs, dropping malformed entries.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, array{weight?: int|string, style?: string}>  $faces
	 *
	 * @return array<int, array{0: int, 1: string}>
	 */
	protected function normalizeRequestedFaces( array $faces ): array
	{
		$normalized = [];

		foreach ( $faces as $face ) {
			if ( ! is_array( $face ) || ! isset( $face['weight'] ) ) {
				continue;
			}

			$weight = $face['weight'];

			if ( ! is_numeric( $weight ) ) {
				continue;
			}

			$style = strtolower( trim( (string) ( $face['style'] ?? 'normal' ) ) );
			$style = 'italic' === $style ? 'italic' : 'normal';

			$key                = ( (int) $weight ) . ':' . $style;
			$normalized[ $key ] = [ (int) $weight, $style ];
		}

		return array_values( $normalized );
	}
}
