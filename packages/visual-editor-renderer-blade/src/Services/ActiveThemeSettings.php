<?php

/**
 * The active theme's resolved `settings`, read once per request.
 *
 * Resolving them loads the theme and its global styles, so blocks that need a
 * setting share this instead of resolving it per block (M-16). Bound `scoped`
 * in the service provider, so long-running workers (Octane, queues) start each
 * request clean.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;
use Throwable;

class ActiveThemeSettings
{
	/**
	 * The memoized settings. Only meaningful once {@see $loaded} is true.
	 *
	 * @var array<string, mixed>|null
	 */
	protected ?array $settings = null;

	/**
	 * Whether {@see $settings} has been resolved this request.
	 */
	protected bool $loaded = false;

	/**
	 * The settings, or null with no active theme or when resolving them fails.
	 *
	 * @since 1.20.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function get(): ?array
	{
		if ( ! $this->loaded ) {
			$this->loaded = true;

			try {
				$this->settings = PresetRegistry::activeThemeSettings();
			} catch ( Throwable ) {
				$this->settings = null;
			}
		}

		return $this->settings;
	}
}
