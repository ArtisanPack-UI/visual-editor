<?php

/**
 * Site-editor global-styles resolver.
 *
 * Singleton — one resolved object per active theme — distinct from the
 * map-shaped sibling resolvers. cms-framework's H3 GlobalStylesResolver
 * registers via `ap.visual-editor.global-styles`; H6 reads it through
 * the `__unstableBase` REST adapter.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Resolution;

class GlobalStylesResolver
{
	/**
	 * @since 1.0.0
	 */
	protected const FILTER_NAME = 'ap.visual-editor.global-styles';

	/**
	 * Raw filter input, deferred until first read.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>|null
	 */
	protected ?array $rawEntry;

	/**
	 * Cache of the normalized typed value object, or null when none was
	 * provided. We use {@see self::$resolved} instead of relying on
	 * {@see self::$cached} being null because null is a valid resolved value
	 * (the standalone-install case).
	 *
	 * @since 1.0.0
	 */
	protected ?ResolvedGlobalStyles $cached = null;

	/**
	 * @since 1.0.0
	 */
	protected bool $resolved = false;

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>|null  $entry  Merged filter result. `null`
	 *                                            when no contributor registered
	 *                                            and no static config is set.
	 */
	public function __construct( ?array $entry = null )
	{
		$this->rawEntry = $entry;
	}

	/**
	 * Returns the resolved global-styles object, or null when none was
	 * registered.
	 *
	 * @since 1.0.0
	 */
	public function get(): ?ResolvedGlobalStyles
	{
		if ( ! $this->resolved ) {
			$this->cached   = ( null !== $this->rawEntry && [] !== $this->rawEntry )
				? ResolvedGlobalStyles::fromArray( $this->rawEntry )
				: null;
			$this->resolved = true;
		}

		return $this->cached;
	}

	/**
	 * Returns the raw merged filter input. Useful for tests and consumers
	 * that want to forward the unparsed shape downstream.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function raw(): ?array
	{
		return $this->rawEntry;
	}
}
