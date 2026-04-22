<?php

/**
 * Repository that reads dev-app sample-content fixtures and writes
 * them to a storage disk for the B1 core-data shim to consume.
 *
 * The fixtures model the five site-editor entities that the shim
 * registers by default — `wp_template`, `wp_template_part`,
 * `wp_navigation`, `wp_block` (patterns), and `globalStyles`. Every
 * file is a single-record JSON document laid out under
 * `{fixturesDir}/{slug}/{name}.json`, where `slug` is the REST path
 * fragment (e.g. `templates`, `template-parts`).
 *
 * Storing the loaded fixtures on a disk rather than a database table
 * keeps Phase B2 decoupled from the Phase C migrations: each Phase C
 * entity ticket can take over by pointing its seeder at the same
 * fixtures directory and inserting rows into the real tables.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SampleContent;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class SampleContentRepository
{
	/**
	 * Map of REST URL fragment → (kind, name) that the B1 shim
	 * registers by default. The REST fragment doubles as the
	 * sub-directory under the fixtures root.
	 *
	 * @var array<string, array{kind: string, name: string}>
	 */
	public const ENTITY_MAP = [
		'templates'      => [ 'kind' => 'postType', 'name' => 'wp_template' ],
		'template-parts' => [ 'kind' => 'postType', 'name' => 'wp_template_part' ],
		'navigation'     => [ 'kind' => 'postType', 'name' => 'wp_navigation' ],
		'patterns'       => [ 'kind' => 'postType', 'name' => 'wp_block' ],
		'global-styles'  => [ 'kind' => 'root', 'name' => 'globalStyles' ],
	];

	/**
	 * Loads every fixture under `$fixturesDir` grouped by its REST
	 * URL fragment.
	 *
	 * The directory layout mirrors the package's REST surface:
	 *
	 *     tests/Fixtures/sample-content/
	 *     ├── templates/
	 *     │   ├── index.json
	 *     │   └── single.json
	 *     ├── template-parts/
	 *     │   └── header.json
	 *     ├── navigation/…
	 *     ├── patterns/…
	 *     └── global-styles/default.json
	 *
	 * Subdirectories that do not match {@see ENTITY_MAP} are ignored
	 * so host apps can park in-progress or draft fixtures alongside
	 * the canonical set without breaking the seeder.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $fixturesDir  Absolute path to the fixtures root.
	 *
	 * @return array<string, array<string, array<string, mixed>>> Map
	 *     of `urlFragment => [ basename => record ]`.
	 *
	 * @throws InvalidArgumentException If the directory is missing.
	 * @throws RuntimeException         If a fixture file cannot be read or
	 *                                  does not contain a JSON object.
	 */
	public function loadFixtures( string $fixturesDir ): array
	{
		if ( ! is_dir( $fixturesDir ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Sample-content fixtures directory not found: %s', $fixturesDir )
			);
		}

		$fixtures = [];

		foreach ( array_keys( self::ENTITY_MAP ) as $fragment ) {
			$entityDir = rtrim( $fixturesDir, DIRECTORY_SEPARATOR )
				. DIRECTORY_SEPARATOR . $fragment;

			if ( ! is_dir( $entityDir ) ) {
				$fixtures[ $fragment ] = [];

				continue;
			}

			$fixtures[ $fragment ] = $this->loadEntityDirectory( $entityDir );
		}

		return $fixtures;
	}

	/**
	 * Writes every loaded fixture to the target disk, one JSON file
	 * per record, under `visual-editor/sample-content/{kind}/{name}/{id}.json`.
	 *
	 * Re-running the method overwrites existing files with the same
	 * payload, which keeps the seeder idempotent without needing a
	 * hash-vs-hash diff — the target disk always reflects the fixtures
	 * directory byte-for-byte.
	 *
	 * @since 1.0.0
	 *
	 * @param  Filesystem                                                     $disk      Storage disk to write to.
	 * @param  array<string, array<string, array<string, mixed>>>             $fixtures  Fixtures grouped by URL fragment.
	 *
	 * @return array<string, int> Map of `urlFragment => recordCount`.
	 *
	 * @throws RuntimeException If any fixture is missing a usable primary key.
	 */
	public function writeToDisk( Filesystem $disk, array $fixtures ): array
	{
		$root   = 'visual-editor/sample-content';
		$plan   = [];
		$counts = [];

		// Validate + encode every record first so a malformed fixture
		// aborts the seed before any existing directory is deleted.
		// Otherwise a bad fixture partway through the loop would wipe
		// the previously-seeded sample content and leave nothing to
		// read back.
		foreach ( self::ENTITY_MAP as $fragment => $identity ) {
			$records = $fixtures[ $fragment ] ?? [];
			$counts[ $fragment ] = 0;

			$entityPath = sprintf(
				'%s/%s/%s',
				$root,
				$identity['kind'],
				$identity['name']
			);

			$plan[ $fragment ] = [
				'entityPath' => $entityPath,
				'writes'     => [],
			];

			foreach ( $records as $basename => $record ) {
				$id     = $this->recordIdFor( $fragment, (string) $basename, $record );
				$target = sprintf( '%s/%s.json', $entityPath, $id );

				$plan[ $fragment ]['writes'][] = [
					'id'      => $id,
					'target'  => $target,
					'payload' => $this->encode( $record ),
				];
			}
		}

		// With every fixture validated, it's safe to reset the entity
		// directories and commit the writes. `deleteDirectory` is
		// defined on the `Filesystem` contract, so every driver the
		// host app might configure (local, s3, memory, …) supports it.
		foreach ( $plan as $fragment => $entry ) {
			if ( $disk->exists( $entry['entityPath'] ) ) {
				$disk->deleteDirectory( $entry['entityPath'] );
			}

			foreach ( $entry['writes'] as $write ) {
				if ( ! $disk->put( $write['target'], $write['payload'] ) ) {
					throw new RuntimeException(
						sprintf(
							'Failed to write sample-content fixture %s (entity id %s).',
							$write['target'],
							$write['id']
						)
					);
				}

				$counts[ $fragment ]++;
			}
		}

		return $counts;
	}

	/**
	 * Reads a single previously-seeded record back from the disk.
	 * Exposed for feature tests and diagnostic tooling — the shim
	 * itself pulls records through HTTP, not this path.
	 *
	 * @since 1.0.0
	 *
	 * @param  Filesystem          $disk      Storage disk to read from.
	 * @param  string              $fragment  REST URL fragment (e.g. `templates`).
	 * @param  int|string          $id        Record primary key.
	 *
	 * @return array<string, mixed>|null The decoded record, or null if absent.
	 *
	 * @throws InvalidArgumentException If `$fragment` is not a known entity.
	 * @throws RuntimeException         If the stored payload cannot be decoded.
	 */
	public function readRecord( Filesystem $disk, string $fragment, int | string $id ): ?array
	{
		if ( ! array_key_exists( $fragment, self::ENTITY_MAP ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown sample-content entity fragment: %s', $fragment )
			);
		}

		$safeId = $this->assertSafeId( $id );

		$identity = self::ENTITY_MAP[ $fragment ];

		$path = sprintf(
			'visual-editor/sample-content/%s/%s/%s.json',
			$identity['kind'],
			$identity['name'],
			$safeId
		);

		if ( ! $disk->exists( $path ) ) {
			return null;
		}

		return $this->decode( $disk->get( $path ) ?? '', $path );
	}

	/**
	 * Resolves the default storage disk the command should write to.
	 * Prefers the host app's `local` disk when available so the seeded
	 * files land under `storage/app/visual-editor/sample-content/`.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $name  Optional disk name override.
	 */
	public function resolveDisk( ?string $name = null ): Filesystem
	{
		if ( null !== $name ) {
			return Storage::disk( $name );
		}

		return Storage::disk( config( 'filesystems.default', 'local' ) );
	}

	/**
	 * @param  string  $directory  Absolute path to an entity-type fixture dir.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function loadEntityDirectory( string $directory ): array
	{
		$records = [];

		$files = glob( rtrim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*.json' ) ?: [];

		sort( $files );

		foreach ( $files as $file ) {
			$basename = pathinfo( $file, PATHINFO_FILENAME );
			$contents = file_get_contents( $file );

			if ( false === $contents ) {
				throw new RuntimeException( sprintf( 'Unable to read fixture: %s', $file ) );
			}

			$records[ $basename ] = $this->decode( $contents, $file );
		}

		return $records;
	}

	/**
	 * Resolves the primary key for a fixture and guards against
	 * filename-unsafe values. String IDs flow straight into the disk
	 * path, so anything with a path separator or `..` segment could
	 * escape `visual-editor/sample-content/{kind}/{name}/` — even
	 * though the fixtures are dev-authored today, the `--path` option
	 * lets host apps point the seeder at arbitrary directories.
	 *
	 * @param  array<string, mixed>  $record
	 */
	protected function recordIdFor( string $fragment, string $basename, array $record ): int | string
	{
		$id = $record['id'] ?? null;

		if ( is_int( $id ) ) {
			return $id;
		}

		if ( is_string( $id ) && '' !== $id ) {
			if ( ! $this->isSafeIdSegment( $id ) ) {
				throw new RuntimeException(
					sprintf(
						'Sample-content fixture %s/%s.json has an unsafe id %s — ids must contain only letters, digits, dot, underscore, or hyphen, and no path-traversal segments.',
						$fragment,
						$basename,
						var_export( $id, true )
					)
				);
			}

			return $id;
		}

		throw new RuntimeException(
			sprintf(
				'Sample-content fixture %s/%s.json is missing a primary key.',
				$fragment,
				$basename
			)
		);
	}

	/**
	 * Validates that `$id` is safe to use as a single path segment
	 * and returns its string form. Non-integer string ids must match
	 * the allowed character set and may not contain any `..` segment.
	 *
	 * @throws InvalidArgumentException If the id is blank or unsafe.
	 */
	protected function assertSafeId( int | string $id ): string
	{
		if ( is_int( $id ) ) {
			return (string) $id;
		}

		if ( '' === $id || ! $this->isSafeIdSegment( $id ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Unsafe sample-content id %s — ids must contain only letters, digits, dot, underscore, or hyphen, and no path-traversal segments.',
					var_export( $id, true )
				)
			);
		}

		return $id;
	}

	/**
	 * Whether `$id` is a single filename-safe path segment: ASCII
	 * letters, digits, dot, underscore, hyphen; and no `..` anywhere
	 * (which would otherwise satisfy the character class).
	 */
	protected function isSafeIdSegment( string $id ): bool
	{
		return 1 === preg_match( '/^[A-Za-z0-9._-]+$/', $id )
			&& ! str_contains( $id, '..' );
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function decode( string $payload, string $source ): array
	{
		try {
			$decoded = json_decode( $payload, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			throw new RuntimeException(
				sprintf( 'Unable to decode sample-content fixture %s: %s', $source, $e->getMessage() ),
				0,
				$e
			);
		}

		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new RuntimeException(
				sprintf( 'Sample-content fixture %s must decode to a JSON object.', $source )
			);
		}

		return $decoded;
	}

	protected function encode( array $record ): string
	{
		try {
			return json_encode(
				$record,
				JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			) . "\n";
		} catch ( JsonException $e ) {
			throw new RuntimeException(
				sprintf( 'Unable to encode sample-content fixture: %s', $e->getMessage() ),
				0,
				$e
			);
		}
	}
}
