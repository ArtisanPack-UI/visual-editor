<?php

/**
 * Font file writer.
 *
 * Persists a provider's fetched face bytes to the configured storage disk and
 * removes them again on uninstall or rollback. Paths are deterministic —
 * `{base}/{provider}/{slug}/{weight}-{style}.{format}` — so re-fetching a face
 * overwrites its own file in place rather than orphaning the previous one, and
 * the installer can compute a face's path before writing to decide what a
 * rollback must clean up.
 *
 * The disk name and base path default to `config('artisanpack.visual-editor.fonts.disk')`
 * and `…fonts.path`, resolved lazily so tests can fake the disk after the
 * writer is constructed.
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class FontFileWriter
{
	/**
	 * @param  string|null  $disk      The storage disk, or null to read config at call time.
	 * @param  string|null  $basePath  The base path under the disk, or null to read config.
	 */
	public function __construct(
		protected ?string $disk = null,
		protected ?string $basePath = null,
	) {
	}

	/**
	 * The storage disk faces are written to.
	 *
	 * @since 1.7.0
	 */
	public function diskName(): string
	{
		return $this->disk ?? (string) config( 'artisanpack.visual-editor.fonts.disk', 'public' );
	}

	/**
	 * Compute the deterministic storage path for a single face.
	 *
	 * The provider key and family slug ride in from the registry and the
	 * catalog, so both are re-slugged before they become path segments — a
	 * hostile slug can never climb out of the base directory.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $provider  The provider registry key.
	 * @param  string  $slug      The family slug.
	 * @param  int     $weight    The face weight.
	 * @param  string  $style     The face style (`normal` or `italic`).
	 * @param  string  $format    The file format/extension (e.g. `woff2`).
	 */
	public function pathFor( string $provider, string $slug, int $weight, string $style, string $format ): string
	{
		return sprintf(
			'%s/%s/%s/%d-%s.%s',
			$this->basePath(),
			Str::slug( $provider ),
			Str::slug( $slug ),
			$weight,
			Str::slug( $style ),
			ltrim( Str::slug( $format ), '.' ),
		);
	}

	/**
	 * Whether a face file already exists on the disk.
	 *
	 * @since 1.7.0
	 */
	public function exists( string $path ): bool
	{
		return Storage::disk( $this->diskName() )->exists( $path );
	}

	/**
	 * Write a single face's bytes and return its stored path.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $provider  The provider registry key.
	 * @param  string  $slug      The family slug.
	 * @param  int     $weight    The face weight.
	 * @param  string  $style     The face style (`normal` or `italic`).
	 * @param  string  $format    The file format/extension (e.g. `woff2`).
	 * @param  string  $contents  The raw font-file bytes.
	 *
	 * @return string The path the face was written to, relative to the disk root.
	 */
	public function write( string $provider, string $slug, int $weight, string $style, string $format, string $contents ): string
	{
		$path = $this->pathFor( $provider, $slug, $weight, $style, $format );

		try {
			$written = Storage::disk( $this->diskName() )->put( $path, $contents );
		} catch ( Throwable $e ) {
			throw new FontFileWriteException(
				sprintf( 'Failed to write the font face to "%s".', $path ),
				0,
				$e
			);
		}

		if ( false === $written ) {
			throw new FontFileWriteException( sprintf(
				'Failed to write the font face to "%s".',
				$path
			) );
		}

		return $path;
	}

	/**
	 * Delete face files by path. Missing paths are ignored so a rollback or
	 * uninstall never fails on a file another step already removed.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, string>  $paths  The paths to delete.
	 */
	public function delete( array $paths ): void
	{
		$paths = array_values( array_filter(
			$paths,
			static fn ( $path ): bool => is_string( $path ) && '' !== $path
		) );

		if ( [] === $paths ) {
			return;
		}

		Storage::disk( $this->diskName() )->delete( $paths );
	}

	/**
	 * The base path under the disk, trimmed of surrounding slashes.
	 *
	 * @since 1.7.0
	 */
	protected function basePath(): string
	{
		$base = $this->basePath ?? (string) config( 'artisanpack.visual-editor.fonts.path', 'visual-editor/fonts' );

		return trim( $base, '/' );
	}
}
