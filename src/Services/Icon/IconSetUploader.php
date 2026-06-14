<?php

/**
 * Admin icon-set zip-upload pipeline.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). Takes a zip uploaded
 * through the settings screen, extracts each entry, runs the SVG payload
 * through {@see SvgSanitizer}, writes survivors under the per-set
 * directory, and records the set in the {@see UploadedIconSetRegistry}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Icon;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

/**
 * The uploader is deliberately split from the controller layer so Pest
 * tests can drive it against real zip fixtures without booting the full
 * HTTP stack. It owns three responsibilities:
 *
 *   1. Open the zip safely (cap entry count + total uncompressed size to
 *      head off zip-bomb DoS against the dev box).
 *   2. Per entry: reject non-SVG extensions outright, sanitize the SVG
 *      payload, and write the survivors under
 *      `{baseDir}/{prefix}/{basename}.svg`.
 *   3. Persist the set's metadata through the registry once at least one
 *      icon landed — a zip that yields zero stored files is treated as a
 *      failed upload and no metadata is written.
 *
 * The uploader does NOT call into `ap.icons.register-icon-sets` itself —
 * that happens at app boot, walking whatever the registry persisted.
 * Doing it here would skip the registration on the next process / queue
 * worker until the application rebooted, which would make uploaded
 * icons "disappear" after a deploy.
 */
final class IconSetUploader
{
	/**
	 * Defensive caps against zip bombs. A typical FA Pro family is on
	 * the order of 2k–10k SVG files and the SVG payloads are a few KB
	 * each; the limits sit comfortably above legitimate uploads.
	 */
	private const MAX_ENTRY_COUNT      = 25_000;
	private const MAX_UNCOMPRESSED_SIZE = 256 * 1024 * 1024;
	private const MAX_FILENAME_LENGTH   = 128;

	/**
	 * Reserved set prefixes — these belong to the bundled FA Free sets
	 * shipped under `resources/icons/font-awesome/`. Letting an admin
	 * register a competing `fas` set would silently shadow the bundled
	 * icons on disk, which is the exact ambiguity the prefix-collision
	 * check is meant to surface.
	 */
	private const RESERVED_PREFIXES = [ 'fas', 'far', 'fab' ];

	public function __construct(
		private readonly UploadedIconSetRegistry $registry,
		private readonly SvgSanitizer $sanitizer,
	) {
	}

	/**
	 * Process the uploaded zip and persist the resulting set.
	 *
	 * @throws PrefixCollisionException When the requested prefix is already taken (bundled or uploaded).
	 * @throws RuntimeException         For unrecoverable filesystem / zip failures.
	 */
	public function upload( UploadedFile $zip, string $prefix, string $label ): IconSetUploadResult
	{
		$prefix = strtolower( trim( $prefix ) );
		$label  = trim( $label );

		$this->assertPrefixAvailable( $prefix );
		$this->assertLabelValid( $label );

		$archive = $this->openZip( $zip );

		try {
			$this->assertWithinLimits( $archive );

			$targetDir = $this->registry->pathFor( $prefix );
			$this->prepareTargetDirectory( $targetDir );

			$result = $this->extractEntries( $archive, $targetDir );
		} finally {
			$archive->close();
		}

		if ( [] === $result->stored ) {
			throw new RuntimeException( 'Upload contained no usable SVG icons.' );
		}

		$this->registry->register(
			new UploadedIconSet( $prefix, $label, gmdate( 'Y-m-d\TH:i:s\Z' ) ),
		);

		return $result;
	}

	/**
	 * Delete an uploaded set's metadata AND its on-disk SVGs in a single
	 * call. The settings screen exposes this as the "delete" action.
	 */
	public function delete( string $prefix ): void
	{
		$set = $this->registry->find( $prefix );
		if ( null === $set ) {
			return;
		}

		$this->registry->forget( $prefix );

		$dir = $this->registry->pathFor( $prefix );
		if ( is_dir( $dir ) ) {
			$this->removeDirectoryRecursively( $dir );
		}
	}

	/**
	 * Cross-check the requested prefix against bundled FA reservations
	 * and the persisted uploaded sets. Raised as a typed exception so
	 * the controller can map it to a 409 response with the offending
	 * prefix visible to the admin.
	 */
	private function assertPrefixAvailable( string $prefix ): void
	{
		if ( ! UploadedIconSetRegistry::isValidPrefix( $prefix ) ) {
			throw new RuntimeException(
				'Icon-set prefix must be 2–32 chars of lowercase letters, digits, dashes or underscores.',
			);
		}

		if ( in_array( $prefix, self::RESERVED_PREFIXES, true ) ) {
			throw new PrefixCollisionException( $prefix );
		}

		if ( $this->registry->has( $prefix ) ) {
			throw new PrefixCollisionException( $prefix );
		}
	}

	private function assertLabelValid( string $label ): void
	{
		if ( '' === $label ) {
			throw new RuntimeException( 'Icon-set label cannot be empty.' );
		}

		if ( mb_strlen( $label ) > UploadedIconSetRegistry::LABEL_MAX_LENGTH ) {
			throw new RuntimeException( 'Icon-set label is too long.' );
		}
	}

	private function openZip( UploadedFile $zip ): ZipArchive
	{
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new RuntimeException( 'PHP zip extension is required to upload icon sets.' );
		}

		$archive = new ZipArchive();
		$status  = $archive->open( $zip->getRealPath() ?: $zip->getPathname(), ZipArchive::RDONLY );

		if ( true !== $status ) {
			throw new RuntimeException( 'Uploaded file is not a valid zip archive.' );
		}

		return $archive;
	}

	private function assertWithinLimits( ZipArchive $archive ): void
	{
		if ( $archive->numFiles > self::MAX_ENTRY_COUNT ) {
			throw new RuntimeException(
				sprintf( 'Zip contains too many entries (%d > %d).', $archive->numFiles, self::MAX_ENTRY_COUNT ),
			);
		}

		$total = 0;
		for ( $i = 0; $i < $archive->numFiles; $i++ ) {
			$stat = $archive->statIndex( $i );
			if ( false === $stat ) {
				continue;
			}
			$total += (int) ( $stat['size'] ?? 0 );
			if ( $total > self::MAX_UNCOMPRESSED_SIZE ) {
				throw new RuntimeException( 'Zip uncompressed size exceeds the allowed limit.' );
			}
		}
	}

	private function prepareTargetDirectory( string $dir ): void
	{
		if ( is_dir( $dir ) ) {
			$this->removeDirectoryRecursively( $dir );
		}

		if ( ! mkdir( $dir, 0o755, true ) && ! is_dir( $dir ) ) {
			throw new RuntimeException( "Failed to create icon-set directory: {$dir}" );
		}
	}

	private function extractEntries( ZipArchive $archive, string $targetDir ): IconSetUploadResult
	{
		$stored  = [];
		$skipped = [];
		$failed  = [];

		for ( $i = 0; $i < $archive->numFiles; $i++ ) {
			$name = $archive->getNameIndex( $i );
			if ( false === $name || '' === $name ) {
				continue;
			}

			// Directory entries inside the zip are not icons — skip them
			// silently. They show up in zips created by Finder / Explorer
			// for every folder, and counting them as "skipped" would
			// pollute the per-file report.
			if ( str_ends_with( $name, '/' ) ) {
				continue;
			}

			$basename = $this->sanitizeBasename( $name );
			if ( null === $basename ) {
				$skipped[] = $name;
				continue;
			}

			if ( '.svg' !== strtolower( substr( $basename, -4 ) ) ) {
				$skipped[] = $basename;
				continue;
			}

			$payload = $archive->getFromIndex( $i );
			if ( false === $payload || '' === trim( $payload ) ) {
				$skipped[] = $basename;
				continue;
			}

			$result = $this->sanitizer->sanitize( $payload );
			if ( $result->isEmpty() ) {
				$failed[] = [
					'file'     => $basename,
					'warnings' => $result->warnings,
				];
				continue;
			}

			$targetPath = $targetDir . DIRECTORY_SEPARATOR . $basename;
			if ( false === file_put_contents( $targetPath, $result->sanitized ) ) {
				throw new RuntimeException( "Failed to write sanitized icon: {$basename}" );
			}

			$stored[] = $basename;
		}

		return new IconSetUploadResult( $stored, $skipped, $failed );
	}

	/**
	 * Reduce a zip entry's name to a safe SVG basename, or `null` if it
	 * would escape the set directory / look nothing like an SVG filename.
	 *
	 * Strips any directory prefix the zip carries (FA Pro zips nest icons
	 * three folders deep), rejects `..` segments, non-ASCII filenames and
	 * over-long names. The result is appended to the trusted target
	 * directory without further validation, so this method is the single
	 * choke-point for path traversal.
	 */
	private function sanitizeBasename( string $entry ): ?string
	{
		$basename = basename( str_replace( '\\', '/', $entry ) );
		if ( '' === $basename ) {
			return null;
		}

		if ( str_starts_with( $basename, '.' ) ) {
			return null;
		}

		if ( strlen( $basename ) > self::MAX_FILENAME_LENGTH ) {
			return null;
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9._-]+$/', $basename ) ) {
			return null;
		}

		return $basename;
	}

	private function removeDirectoryRecursively( string $dir ): void
	{
		$entries = scandir( $dir );
		if ( false === $entries ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				$this->removeDirectoryRecursively( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}
