<?php

/**
 * Reads an iconRef `(set, name)` into the inline SVG markup we drop into
 * the rendered Icon Block.
 *
 * The resolver is constructed once per request from the icon-sets
 * registry (`ap.icons.register-icon-sets` filter result) and cached as a
 * singleton. Lookups are file-system reads against the registered set
 * paths — bundled FA Free SVGs live under `resources/icons/font-awesome/`
 * after `scripts/sync-fa-icons.mjs` runs.
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
 * `IconBlock` already normalizes the `(set, name)` pair to a tight
 * character class before it reaches `resolve()`, so joining them onto
 * the registered set path can't escape the set's directory. Keeping the
 * resolver dumb (string in, string out) lets tests drive it with a
 * fixture path map without touching the icons package.
 *
 * The path map can be supplied either eagerly (an `array` for tests +
 * fixtures) or lazily (a `Closure` that returns the array). The lazy
 * form is what production uses: the icon-sets registry is built from
 * the `ap.icons.register-icon-sets` filter, and that filter's
 * callbacks are registered across multiple providers' `boot()` phases.
 * Running the filter at singleton-construction time would race against
 * those registrations — `IconBlock` is constructed early in `boot()`,
 * before every provider's boot has finished — so we defer until the
 * first `resolve()` call, which only ever fires at request time.
 */
final class IconSvgResolver
{
	/**
	 * @var array<string, string>|null
	 */
	private ?array $resolvedPaths;

	/**
	 * @var Closure|null
	 */
	private $loader;

	/**
	 * @param  array<string, string>|Closure|null $source  Path map, or a
	 *         `Closure(): array<string, string>` evaluated on first resolve.
	 */
	public function __construct( array|Closure|null $source = null )
	{
		if ( $source instanceof Closure ) {
			$this->loader         = $source;
			$this->resolvedPaths  = null;
		} else {
			$this->resolvedPaths = is_array( $source ) ? $source : [];
			$this->loader        = null;
		}
	}

	/**
	 * Return the raw SVG markup for `(set, name)`, or `null` if no
	 * registered set serves the requested icon.
	 *
	 * Inputs are re-validated at the service boundary instead of trusting
	 * callers' normalization — `IconBlock::normalizeIconRef()` is the
	 * primary gate, but the resolver is a public service that other
	 * callers (admin upload preview in Phase 6, future REST endpoints)
	 * can reach, and a path-traversal escape here would expose the host
	 * file system. Set/name allowlist mirrors the icons-registry folder
	 * shape; `..` is explicitly rejected so a name that satisfies the
	 * regex but encodes a parent-directory hop (e.g. `a..b`) can't
	 * resolve to a file outside the set's directory.
	 */
	public function resolve( string $set, string $name ): ?string
	{
		if ( ! $this->isValidSet( $set ) || ! $this->isValidName( $name ) ) {
			return null;
		}

		$paths = $this->setPaths();
		if ( ! isset( $paths[ $set ] ) ) {
			return null;
		}

		$file = $paths[ $set ] . DIRECTORY_SEPARATOR . $name . '.svg';
		if ( ! is_file( $file ) ) {
			return null;
		}

		$contents = @file_get_contents( $file );

		return false === $contents ? null : $contents;
	}

	private function isValidSet( string $set ): bool
	{
		return 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $set );
	}

	private function isValidName( string $name ): bool
	{
		if ( str_contains( $name, '..' ) ) {
			return false;
		}

		return 1 === preg_match( '/^[a-z0-9][a-z0-9_.-]*$/i', $name );
	}

	/**
	 * @return array<string, string>
	 */
	public function setPaths(): array
	{
		if ( null === $this->resolvedPaths ) {
			$loaded              = null !== $this->loader ? ( $this->loader )() : [];
			$this->resolvedPaths = is_array( $loaded ) ? $loaded : [];
		}

		return $this->resolvedPaths;
	}
}
