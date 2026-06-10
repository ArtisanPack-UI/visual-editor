<?php

/**
 * In-memory catalog of registered icons, backed by the bundled
 * `index.json` metadata manifest written by `scripts/sync-fa-icons.mjs`.
 *
 * Phase 4 of the Icon Block feature (#494, issue #555). The picker UI
 * needs a server-side search surface so the editor never has to ship
 * thousands of icon names and aliases to the browser; the catalog
 * lazy-loads the manifest on first query and serves filtered, paginated
 * results to `IconSearchController` and `IconSetsController`.
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

use Closure;

/**
 * The catalog is intentionally dumb: it reads one JSON file (or accepts a
 * `Closure` that returns the array form for tests) and provides a search
 * + listing surface on top of it. No DB, no Scout — the picker is
 * read-only against a static manifest, and the manifest already carries
 * the search terms each upstream FA entry ships with.
 */
final class IconCatalog
{
	/**
	 * Hard cap on the number of icons returned by a single search call.
	 * The picker pages through results client-side; this cap exists so a
	 * pathological `per_page` query can't fan out into a 10k-row JSON
	 * response.
	 */
	public const MAX_PER_PAGE = 60;

	/**
	 * Default page size when the caller does not specify one.
	 */
	public const DEFAULT_PER_PAGE = 30;

	/**
	 * @var string|Closure|null
	 */
	private $source;

	/**
	 * @var array{
	 *     version?: string,
	 *     sets: list<array{prefix: string, label: string, source?: string}>,
	 *     icons: list<array{name: string, set: string, label: string, terms: list<string>}>
	 * }|null
	 */
	private ?array $manifest = null;

	/**
	 * @param  string|Closure|null $source  Absolute path to an `index.json`,
	 *         a `Closure(): array` that returns the manifest shape, or null
	 *         to fall back to the bundled FA Free manifest.
	 */
	public function __construct( string|Closure|null $source = null )
	{
		$this->source = $source;
	}

	/**
	 * Return the registered icon sets, in declared order. Each entry is
	 * shaped `{prefix, label}` for direct rendering as set-family chips
	 * in the picker UI.
	 *
	 * @return list<array{prefix: string, label: string}>
	 */
	public function sets(): array
	{
		$manifest = $this->load();
		$out      = [];
		foreach ( $manifest['sets'] as $set ) {
			$prefix = (string) ( $set['prefix'] ?? '' );
			$label  = (string) ( $set['label'] ?? $prefix );
			if ( '' === $prefix ) {
				continue;
			}
			$out[] = [ 'prefix' => $prefix, 'label' => $label ];
		}

		return $out;
	}

	/**
	 * Search the catalog for icons matching `$query`, optionally
	 * restricted to a single set prefix. Results are paginated 1-indexed
	 * by `$page` with `$perPage` items per page (clamped to
	 * `MAX_PER_PAGE`).
	 *
	 * The empty-query branch returns the first page of every registered
	 * icon — the picker uses this for the initial grid render before the
	 * user types anything.
	 *
	 * @return array{
	 *     total: int,
	 *     page: int,
	 *     per_page: int,
	 *     data: list<array{name: string, set: string, label: string}>
	 * }
	 */
	public function search( string $query, ?string $set = null, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE ): array
	{
		$manifest = $this->load();
		$needle   = mb_strtolower( trim( $query ) );
		$setSlug  = null !== $set ? trim( $set ) : '';

		$matches = [];
		foreach ( $manifest['icons'] as $icon ) {
			if ( '' !== $setSlug && $icon['set'] !== $setSlug ) {
				continue;
			}

			if ( '' !== $needle && ! $this->iconMatches( $icon, $needle ) ) {
				continue;
			}

			$matches[] = [
				'name'  => (string) $icon['name'],
				'set'   => (string) $icon['set'],
				'label' => (string) ( $icon['label'] ?? $icon['name'] ),
			];
		}

		$total   = count( $matches );
		$perPage = max( 1, min( self::MAX_PER_PAGE, $perPage ) );
		$page    = max( 1, $page );
		$offset  = ( $page - 1 ) * $perPage;
		$slice   = array_slice( $matches, $offset, $perPage );

		return [
			'total'    => $total,
			'page'     => $page,
			'per_page' => $perPage,
			'data'     => array_values( $slice ),
		];
	}

	/**
	 * Test whether a manifest row matches the lowercased needle. The
	 * `label` and `name` fields are checked first because they're the
	 * most common author-typed match; `terms` cover the upstream FA
	 * alias list.
	 *
	 * @param  array{name: string, label: string, terms: list<string>} $icon
	 */
	private function iconMatches( array $icon, string $needle ): bool
	{
		if ( str_contains( mb_strtolower( (string) $icon['name'] ), $needle ) ) {
			return true;
		}
		if ( str_contains( mb_strtolower( (string) ( $icon['label'] ?? '' ) ), $needle ) ) {
			return true;
		}
		foreach ( $icon['terms'] ?? [] as $term ) {
			if ( str_contains( mb_strtolower( (string) $term ), $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Lazy-load + memoize the manifest. Resolution order:
	 *
	 *   1. Closure source — invoked once, expected to return the array
	 *      shape directly (used by Pest fixtures).
	 *   2. String source — treated as an absolute path to a JSON file.
	 *   3. `null` source — fall back to the bundled FA Free manifest.
	 *
	 * Any missing-file or malformed-JSON case produces an empty manifest
	 * so the picker degrades to "no results" rather than throwing inside
	 * the request lifecycle.
	 *
	 * @return array{
	 *     version?: string,
	 *     sets: list<array{prefix: string, label: string, source?: string}>,
	 *     icons: list<array{name: string, set: string, label: string, terms: list<string>}>
	 * }
	 */
	private function load(): array
	{
		if ( null !== $this->manifest ) {
			return $this->manifest;
		}

		$raw = $this->readSource();

		if ( ! is_array( $raw ) ) {
			$this->manifest = [ 'sets' => [], 'icons' => [] ];

			return $this->manifest;
		}

		$sets  = is_array( $raw['sets'] ?? null ) ? array_values( $raw['sets'] ) : [];
		$icons = is_array( $raw['icons'] ?? null ) ? array_values( $raw['icons'] ) : [];

		$this->manifest = [
			'version' => isset( $raw['version'] ) ? (string) $raw['version'] : '',
			'sets'    => $sets,
			'icons'   => $icons,
		];

		return $this->manifest;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function readSource(): ?array
	{
		if ( $this->source instanceof Closure ) {
			$value = ( $this->source )();

			return is_array( $value ) ? $value : null;
		}

		$path = is_string( $this->source ) && '' !== $this->source
			? $this->source
			: __DIR__ . '/../../../resources/icons/font-awesome/index.json';

		if ( ! is_file( $path ) ) {
			return null;
		}

		$contents = @file_get_contents( $path );
		if ( false === $contents ) {
			return null;
		}

		$decoded = json_decode( $contents, true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
