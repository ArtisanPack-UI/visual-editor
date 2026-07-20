<?php

/**
 * JSON-backed registry of host-uploaded icon sets.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). Persists set
 * metadata to `storage/app/artisanpack/visual-editor/icons/sets.json`
 * so the service provider can re-register uploaded sets against the
 * `ap.icons.registerIconSets` filter on every boot without re-walking
 * directories or re-validating zip uploads.
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

/**
 * The registry is the single source of truth for "which uploaded sets
 * does the editor know about". The uploader writes through it after a
 * successful sanitization pass; the service provider reads from it at
 * boot to wire the icon-set registration filter; the management
 * controller reads + mutates it to back the settings screen.
 *
 * Persistence is a plain JSON manifest rather than a DB row because the
 * SVG files themselves live on disk under the same base directory —
 * keeping the metadata next to the files means a host can rsync the
 * whole `icons/` tree between environments and bring the sets along
 * with it without a separate DB dump.
 */
final class UploadedIconSetRegistry
{
	/**
	 * Lowercase alphanumeric + dash + underscore, 2–32 characters. Mirrors
	 * the public prefix surface — the directory name is the prefix, and a
	 * looser pattern would let an upload write under an arbitrary path
	 * relative to the storage base.
	 */
	public const PREFIX_PATTERN = '/^[a-z0-9][a-z0-9_-]{1,31}$/';

	/**
	 * Hard cap on label length — the picker chip strip wraps long labels
	 * awkwardly and a 5 KB label would blow up the JSON manifest with no
	 * user value.
	 */
	public const LABEL_MAX_LENGTH = 64;

	private const MANIFEST_FILENAME = 'sets.json';

	public function __construct( private readonly string $baseDir )
	{
	}

	/**
	 * The absolute directory under which uploaded sets are persisted.
	 * Used by the uploader to compute target paths and by the boot-time
	 * registration step.
	 */
	public function baseDir(): string
	{
		return $this->baseDir;
	}

	/**
	 * Resolve the absolute directory for a single set.
	 *
	 * Validates the prefix shape before joining so a caller can't push a
	 * `../`-laced prefix through the registry into the storage tree.
	 */
	public function pathFor( string $prefix ): string
	{
		if ( ! self::isValidPrefix( $prefix ) ) {
			throw new RuntimeException( "Invalid icon-set prefix: {$prefix}" );
		}

		return $this->baseDir . DIRECTORY_SEPARATOR . $prefix;
	}

	/**
	 * @return list<UploadedIconSet>
	 */
	public function all(): array
	{
		$manifest = $this->loadManifest();

		$out = [];
		foreach ( $manifest['sets'] ?? [] as $row ) {
			try {
				$out[] = UploadedIconSet::fromArray( $row );
			} catch ( \InvalidArgumentException $e ) {
				// A corrupt row should not take the whole registry
				// down — boot is supposed to be resilient to a
				// hand-edited manifest, and the settings screen is
				// where the admin reconciles the conflict.
				continue;
			}
		}

		return $out;
	}

	public function find( string $prefix ): ?UploadedIconSet
	{
		foreach ( $this->all() as $set ) {
			if ( $set->prefix === $prefix ) {
				return $set;
			}
		}

		return null;
	}

	public function has( string $prefix ): bool
	{
		return null !== $this->find( $prefix );
	}

	/**
	 * Persist a new set entry. Caller is responsible for writing the SVGs
	 * to the set directory before calling — the registry only tracks
	 * metadata, not the on-disk SVG layout.
	 */
	public function register( UploadedIconSet $set ): void
	{
		if ( ! self::isValidPrefix( $set->prefix ) ) {
			throw new RuntimeException( "Invalid icon-set prefix: {$set->prefix}" );
		}

		$manifest         = $this->loadManifest();
		$sets             = $manifest['sets'] ?? [];
		$replaced         = false;

		foreach ( $sets as $index => $row ) {
			if ( (string) ( $row['prefix'] ?? '' ) === $set->prefix ) {
				$sets[ $index ] = $set->toArray();
				$replaced       = true;
				break;
			}
		}

		if ( ! $replaced ) {
			$sets[] = $set->toArray();
		}

		$manifest['sets'] = array_values( $sets );
		$this->writeManifest( $manifest );
	}

	/**
	 * Update the human-facing label of an existing set without touching
	 * the prefix or the on-disk SVGs.
	 */
	public function rename( string $prefix, string $label ): UploadedIconSet
	{
		$existing = $this->find( $prefix );
		if ( null === $existing ) {
			throw new RuntimeException( "Icon set not found: {$prefix}" );
		}

		$trimmed = trim( $label );
		if ( '' === $trimmed ) {
			throw new RuntimeException( 'Icon set label cannot be empty.' );
		}

		if ( mb_strlen( $trimmed ) > self::LABEL_MAX_LENGTH ) {
			throw new RuntimeException( 'Icon set label is too long.' );
		}

		$updated = new UploadedIconSet( $existing->prefix, $trimmed, $existing->createdAt );
		$this->register( $updated );

		return $updated;
	}

	/**
	 * Drop a set from the manifest. The caller is responsible for
	 * removing the corresponding on-disk SVG directory — the registry
	 * intentionally does not touch the filesystem so the storage layout
	 * stays the uploader's concern.
	 */
	public function forget( string $prefix ): void
	{
		$manifest = $this->loadManifest();
		$sets     = $manifest['sets'] ?? [];

		$filtered = array_values( array_filter(
			$sets,
			static fn ( array $row ): bool => (string) ( $row['prefix'] ?? '' ) !== $prefix,
		) );

		if ( count( $filtered ) === count( $sets ) ) {
			return;
		}

		$manifest['sets'] = $filtered;
		$this->writeManifest( $manifest );
	}

	public static function isValidPrefix( string $prefix ): bool
	{
		return 1 === preg_match( self::PREFIX_PATTERN, $prefix );
	}

	/**
	 * @return array{sets: list<array<string, mixed>>}
	 */
	private function loadManifest(): array
	{
		$path = $this->manifestPath();
		if ( ! is_file( $path ) ) {
			return [ 'sets' => [] ];
		}

		$contents = file_get_contents( $path );
		if ( false === $contents || '' === $contents ) {
			return [ 'sets' => [] ];
		}

		$decoded = json_decode( $contents, true );
		if ( ! is_array( $decoded ) ) {
			return [ 'sets' => [] ];
		}

		$sets = is_array( $decoded['sets'] ?? null ) ? array_values( $decoded['sets'] ) : [];

		return [ 'sets' => $sets ];
	}

	/**
	 * @param  array{sets: list<array<string, mixed>>} $manifest
	 */
	private function writeManifest( array $manifest ): void
	{
		if ( ! is_dir( $this->baseDir ) ) {
			if ( ! mkdir( $this->baseDir, 0o755, true ) && ! is_dir( $this->baseDir ) ) {
				throw new RuntimeException( "Failed to create icon-set base directory: {$this->baseDir}" );
			}
		}

		$encoded = json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Failed to encode icon-set manifest.' );
		}

		if ( false === file_put_contents( $this->manifestPath(), $encoded . "\n", LOCK_EX ) ) {
			throw new RuntimeException( 'Failed to write icon-set manifest.' );
		}
	}

	private function manifestPath(): string
	{
		return $this->baseDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
	}
}
