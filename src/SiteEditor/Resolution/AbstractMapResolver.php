<?php

/**
 * Abstract map-shaped site-editor resolver.
 *
 * Shared scaffolding for the four map-style resolvers (templates,
 * template-parts, patterns, navigation). The fifth — global-styles —
 * is a singleton, so it has its own resolver shape.
 *
 * Validation is deferred until first read so a misconfigured filter
 * contributor surfaces an exception on the editor's first request, not
 * at boot — letting standalone visual-editor or cms-framework installs
 * boot cleanly even when contributors return garbage.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 *
 * @template TValue of object
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Resolution;

use ArtisanPackUI\VisualEditor\SiteEditor\Exceptions\SiteEditorRegistrationException;

abstract class AbstractMapResolver
{
	/**
	 * Raw filter input, deferred until first read.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	protected array $rawEntries;

	/**
	 * Cache of normalized typed value objects keyed by slug / location.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, TValue>|null
	 */
	protected ?array $cached = null;

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $entries  The merged filter result.
	 */
	public function __construct( array $entries = [] )
	{
		$this->rawEntries = $entries;
	}

	/**
	 * Returns all resolved entries, normalizing on first call.
	 *
	 * Throws {@see SiteEditorRegistrationException} if any entry has the
	 * wrong shape — surfaces invalid contributors at the first read instead
	 * of at boot.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, TValue>
	 */
	public function all(): array
	{
		if ( null === $this->cached ) {
			$this->cached = $this->normalizeEntries( $this->rawEntries );
		}

		return $this->cached;
	}

	/**
	 * Returns a single entry by key, or null when absent. Forces normalization.
	 *
	 * @since 1.0.0
	 *
	 * @return TValue|null
	 */
	public function find( string $key ): ?object
	{
		return $this->all()[ $key ] ?? null;
	}

	/**
	 * Returns the merged filter input verbatim. Useful for tests and for
	 * resolvers that want to re-emit raw entries to downstream resolvers
	 * without paying normalization.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function raw(): array
	{
		return $this->rawEntries;
	}

	/**
	 * Walk the raw entries, validating each through the per-type
	 * {@see static::normalizeEntry()} factory.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $entries
	 *
	 * @return array<string, TValue>
	 */
	protected function normalizeEntries( array $entries ): array
	{
		$out = [];

		foreach ( $entries as $key => $entry ) {
			if ( ! is_string( $key ) || '' === $key ) {
				throw SiteEditorRegistrationException::invalidFilterShape(
					static::filterName(),
					'a map keyed by non-empty string',
					'numeric or empty keys',
				);
			}

			if ( ! is_array( $entry ) ) {
				throw SiteEditorRegistrationException::invalidFilterShape(
					static::filterName(),
					"an array entry for '{$key}'",
					gettype( $entry ),
				);
			}

			// Stamp the map key onto the entry under the type-specific key
			// field so the value object can default to it when the entry
			// omits the field. The per-type subclass picks the right field.
			$out[ $key ] = static::normalizeEntry( $key, $entry );
		}

		return $out;
	}

	/**
	 * The filter slug this resolver consumes — used in exceptions.
	 *
	 * @since 1.0.0
	 */
	abstract protected static function filterName(): string;

	/**
	 * Convert a single raw filter entry into the resolver's typed value object.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $key
	 * @param  array<string, mixed>  $entry
	 *
	 * @return TValue
	 */
	abstract protected static function normalizeEntry( string $key, array $entry ): object;
}
