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
use ArtisanPackUI\VisualEditor\Fonts\Providers\CustomUploadProvider;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use ArtisanPackUI\VisualEditor\Fonts\Support\VariableFontMetadataParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FontInstaller
{
	/**
	 * Font-container magic-byte signatures accepted for a custom upload: the
	 * single-font SFNT flavors, WOFF, and WOFF2 — the containers the upload
	 * request permits. Guarded before self-hosting so a file that merely carries
	 * a font extension cannot be written as one. Font collections (`ttcf`) are
	 * intentionally excluded: they are not an allowed upload extension and would
	 * not map to a single-face `@font-face` file.
	 *
	 * @var array<int, string>
	 */
	protected const FONT_SIGNATURES = [ "\x00\x01\x00\x00", 'OTTO', 'true', 'typ1', 'wOFF', 'wOF2' ];

	public function __construct(
		protected FontSourceRegistry $registry,
		protected FontFileWriter $fileWriter,
		protected FontsCssGenerator $cssGenerator,
		protected VariableFontMetadataParser $metadataParser = new VariableFontMetadataParser(),
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
		// Canonicalize the identifiers the registry resolves loosely: `get()`
		// trims the key, but this method persists it and derives the storage
		// path from it, so `install(' google ')` and `install('google')` must
		// not diverge into two rows over the same files.
		$providerKey = trim( $providerKey );
		$slug        = trim( $slug );

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

		// Serialize concurrent installs of the same family. Without this, two
		// installs can both see a face's file as new; if one commits and the
		// other later rolls back, the loser would delete the file the winner's
		// committed FontFace now references.
		$font = Cache::lock( $this->installLockKey( $providerKey, $slug ), 30 )->block(
			15,
			fn (): Font => $this->writeAndPersist( $provider, $providerKey, $slug, $family, $requested )
		);

		$this->regenerate();

		return $font->load( 'faces' );
	}

	/**
	 * Install a custom-uploaded font family, self-hosting each uploaded face and
	 * recording any variable-axis metadata read from the files themselves.
	 *
	 * Uploads do not go through the catalog {@see install()} path — there is no
	 * remote origin to fetch a face from. Each face's bytes are already in hand,
	 * so axes come from {@see VariableFontMetadataParser} run over the file rather
	 * than from a provider's `getFamily()`. A face whose file carries an `fvar`
	 * table marks the whole family variable and stores its axis ranges on the
	 * {@see \ArtisanPackUI\VisualEditor\Fonts\Models\FontFace}; a static (or
	 * unparseable) file installs cleanly with no axes.
	 *
	 * Re-uploading a family merges faces exactly as {@see install()} does, and a
	 * family already marked variable is never downgraded by a later static
	 * upload.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $family  The display family name; its slug is the storage key.
	 * @param  array<int, array{contents: string, weight?: int|string, style?: string}>  $faces
	 *         The uploaded faces, each carrying the raw file `contents` and an
	 *         optional `weight` and `style`. The stored format is derived from the
	 *         file's own signature.
	 *
	 * @return Font The installed (or merged) font, with its faces loaded.
	 */
	public function installUpload( string $family, array $faces ): Font
	{
		$family = trim( $family );

		if ( '' === $family ) {
			throw new FontInstallationException( 'A custom font upload requires a family name.' );
		}

		$providerKey = CustomUploadProvider::KEY;
		$provider    = $this->registry->get( $providerKey );

		if ( null === $provider ) {
			throw new FontInstallationException(
				'Custom font uploads are not enabled.'
			);
		}

		$slug = Str::slug( $family );

		if ( '' === $slug ) {
			throw new FontInstallationException( sprintf(
				'The family name "%s" does not produce a usable font slug.',
				$family
			) );
		}

		$prepared = $this->prepareUploadedFaces( $faces );

		if ( [] === $prepared ) {
			throw new FontInstallationException(
				'At least one valid font file must be uploaded to install a custom font.'
			);
		}

		$font = Cache::lock( $this->installLockKey( $providerKey, $slug ), 30 )->block(
			15,
			fn (): Font => $this->writeAndPersistUploads( $providerKey, $family, $slug, $prepared )
		);

		$this->regenerate();

		return $font->load( 'faces' );
	}

	/**
	 * Fetch and self-host the requested faces, then record the font and its
	 * faces in a transaction. On any failure only the files newly written by
	 * this call are removed and the rows roll back.
	 *
	 * @since 1.7.0
	 *
	 * @param  \ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider  $provider
	 * @param  array<string, mixed>                                      $family
	 * @param  array<int, array{0: int, 1: string}>                      $requested
	 */
	protected function writeAndPersist( $provider, string $providerKey, string $slug, array $family, array $requested ): Font
	{
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

			return DB::transaction( function () use ( $providerKey, $slug, $family, $writtenFaces ): Font {
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
	}

	/**
	 * Normalize the uploaded-face payload: validate each file's font signature,
	 * parse its variable-axis metadata, resolve its weight/style/format, and
	 * de-duplicate by weight+style (last upload wins).
	 *
	 * A file whose bytes are not a recognized font container is dropped rather
	 * than aborting the whole upload.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, array{contents?: string, weight?: int|string, style?: string}>  $faces
	 *
	 * @return array<int, array{weight: int, style: string, format: string, contents: string, is_variable: bool, axes: array<string, mixed>|null}>
	 */
	protected function prepareUploadedFaces( array $faces ): array
	{
		$prepared = [];

		foreach ( $faces as $face ) {
			if ( ! is_array( $face ) ) {
				continue;
			}

			$contents = $face['contents'] ?? null;

			if ( ! is_string( $contents ) || '' === $contents || ! $this->isFontSignature( $contents ) ) {
				continue;
			}

			$weight = $face['weight'] ?? 400;

			if ( ! is_numeric( $weight ) ) {
				$weight = 400;
			}

			$weight = (int) $weight;
			$style  = strtolower( trim( (string) ( $face['style'] ?? 'normal' ) ) );
			$style  = 'italic' === $style ? 'italic' : 'normal';
			$format = $this->uploadFormat( $contents );

			$metadata = $this->metadataParser->parse( $contents );

			$prepared[ $weight . ':' . $style ] = [
				'weight'      => $weight,
				'style'       => $style,
				'format'      => $format,
				'contents'    => $contents,
				'is_variable' => (bool) $metadata['is_variable'],
				'axes'        => $metadata['is_variable'] ? $metadata['axes'] : null,
			];
		}

		return array_values( $prepared );
	}

	/**
	 * Self-host the prepared upload faces, then record the font and its faces in a
	 * transaction. On any failure only the files newly written by this call are
	 * removed and the rows roll back, mirroring {@see writeAndPersist()}.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, array{weight: int, style: string, format: string, contents: string, is_variable: bool, axes: array<string, mixed>|null}>  $prepared
	 */
	protected function writeAndPersistUploads( string $providerKey, string $family, string $slug, array $prepared ): Font
	{
		$rollbackPaths = [];
		$restoreFiles  = [];
		$writtenFaces  = [];
		$isVariable    = false;

		try {
			foreach ( $prepared as $face ) {
				$path  = $this->fileWriter->pathFor( $providerKey, $slug, $face['weight'], $face['style'], $face['format'] );
				$isNew = ! $this->fileWriter->exists( $path );

				// An upload can replace an existing face with different bytes, so
				// snapshot the current file before overwriting it. On rollback the
				// snapshot is restored — unlike the catalog install, whose
				// re-fetched faces are byte-identical and safe to leave in place.
				if ( ! $isNew ) {
					$original = $this->fileWriter->get( $path );

					if ( is_string( $original ) ) {
						$restoreFiles[ $path ] = $original;
					}
				}

				$this->fileWriter->write( $providerKey, $slug, $face['weight'], $face['style'], $face['format'], $face['contents'] );

				if ( $isNew ) {
					$rollbackPaths[] = $path;
				}

				$isVariable = $isVariable || $face['is_variable'];

				$writtenFaces[] = [
					'weight'    => $face['weight'],
					'style'     => $face['style'],
					'format'    => $face['format'],
					'path'      => $path,
					'file_size' => strlen( $face['contents'] ),
					'axes'      => $face['axes'],
				];
			}

			return DB::transaction( function () use ( $providerKey, $family, $slug, $writtenFaces, $isVariable ): Font {
				$font = Font::query()->firstOrNew( [
					'provider' => $providerKey,
					'slug'     => $slug,
				] );

				$font->family = $family;
				// Never downgrade a family a previous upload already established as
				// variable; a new variable face upgrades a static one.
				$font->is_variable  = (bool) $font->is_variable || $isVariable;
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
			$this->restoreOverwrittenFiles( $providerKey, $slug, $prepared, $restoreFiles );

			if ( $e instanceof FontInstallationException
				|| $e instanceof FontFileWriteException ) {
				throw $e;
			}

			throw new FontInstallationException(
				sprintf( 'Failed to install the uploaded font "%s": %s', $family, $e->getMessage() ),
				0,
				$e
			);
		}
	}

	/**
	 * Restore the pre-overwrite bytes of any existing face files this call
	 * replaced, so a failed re-upload leaves each existing face exactly as it
	 * was. Best-effort: a restore failure is logged rather than thrown, since the
	 * install is already failing and the committed rows still point at the path.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, array{weight: int, style: string, format: string, contents: string, is_variable: bool, axes: array<string, mixed>|null}>  $prepared
	 * @param  array<string, string>  $restoreFiles  Original bytes keyed by storage path.
	 */
	protected function restoreOverwrittenFiles( string $providerKey, string $slug, array $prepared, array $restoreFiles ): void
	{
		if ( [] === $restoreFiles ) {
			return;
		}

		foreach ( $prepared as $face ) {
			$path = $this->fileWriter->pathFor( $providerKey, $slug, $face['weight'], $face['style'], $face['format'] );

			if ( ! isset( $restoreFiles[ $path ] ) ) {
				continue;
			}

			try {
				$this->fileWriter->write( $providerKey, $slug, $face['weight'], $face['style'], $face['format'], $restoreFiles[ $path ] );
			} catch ( Throwable $e ) {
				Log::error(
					'Failed to restore an overwritten font face after a failed custom upload.',
					[ 'path' => $path, 'exception' => $e ]
				);
			}
		}
	}

	/**
	 * Whether a file's leading bytes are a recognized font-container signature.
	 *
	 * @since 1.7.0
	 */
	protected function isFontSignature( string $contents ): bool
	{
		return in_array( substr( $contents, 0, 4 ), self::FONT_SIGNATURES, true );
	}

	/**
	 * Derive the stored file format for an uploaded face from its own signature
	 * rather than the client-supplied extension, so the on-disk file's format
	 * (and thus its `@font-face` `format()` token) always matches its real bytes
	 * even when the upload was misnamed.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $contents  The raw font-file bytes.
	 */
	protected function uploadFormat( string $contents ): string
	{
		return match ( substr( $contents, 0, 4 ) ) {
			'wOF2'  => 'woff2',
			'wOFF'  => 'woff',
			'OTTO'  => 'otf',
			default => 'ttf',
		};
	}

	/**
	 * Uninstall a single font: delete its rows (faces cascade), remove its
	 * files, and rebuild the bundle.
	 *
	 * @since 1.7.0
	 */
	public function uninstall( Font $font ): void
	{
		$filesByDisk = $this->faceFilesByDisk( $font->faces );

		DB::transaction( static function () use ( $font ): void {
			$font->delete();
		} );

		// Regenerate before removing files so the bundle already reflects the
		// removal even if file cleanup fails; a failed delete then leaves only
		// an orphaned file (logged), never a stylesheet referencing it.
		$this->regenerate();
		$this->deleteFiles( $filesByDisk );
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
		$ids         = [];
		$filesByDisk = [];

		foreach ( $fonts as $font ) {
			$model = $font instanceof Font ? $font : Font::query()->with( 'faces' )->find( $font );

			if ( null === $model ) {
				continue;
			}

			$ids[] = $model->id;

			foreach ( $model->faces as $face ) {
				$filesByDisk[ (string) $face->disk ][] = (string) $face->path;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		if ( [] === $ids ) {
			return 0;
		}

		DB::transaction( static function () use ( $ids ): void {
			Font::query()->whereIn( 'id', $ids )->delete();
		} );

		$this->regenerate();
		$this->deleteFiles( $filesByDisk );

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
	 * The cache-lock key that serializes installs of one provider family.
	 *
	 * @since 1.7.0
	 */
	protected function installLockKey( string $providerKey, string $slug ): string
	{
		return 'visual-editor.fonts.install.' . $providerKey . '.' . $slug;
	}

	/**
	 * Group a font's face files by the disk each was persisted to, so cleanup
	 * targets the disk recorded at install time rather than the currently
	 * configured one.
	 *
	 * @since 1.7.0
	 *
	 * @param  Collection<int, \ArtisanPackUI\VisualEditor\Fonts\Models\FontFace>  $faces
	 *
	 * @return array<string, array<int, string>>
	 */
	protected function faceFilesByDisk( Collection $faces ): array
	{
		$grouped = [];

		foreach ( $faces as $face ) {
			$grouped[ (string) $face->disk ][] = (string) $face->path;
		}

		return $grouped;
	}

	/**
	 * Delete grouped face files, each from its own disk. A storage failure is
	 * logged rather than thrown: the rows and bundle are already consistent, so
	 * an undeletable file is a leak to reconcile, not a failed uninstall.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, array<int, string>>  $filesByDisk
	 */
	protected function deleteFiles( array $filesByDisk ): void
	{
		foreach ( $filesByDisk as $disk => $paths ) {
			try {
				$this->fileWriter->delete( $paths, $disk );
			} catch ( Throwable $e ) {
				Log::error(
					'Failed to delete Font Library face files during uninstall.',
					[ 'disk' => $disk, 'exception' => $e ]
				);
			}
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
