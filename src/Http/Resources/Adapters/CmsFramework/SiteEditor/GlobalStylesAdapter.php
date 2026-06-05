<?php

/**
 * Global-styles adapter — WP `__unstableBase` REST shape.
 *
 * Singleton (one record per active theme): the merged `settings` + `styles`
 * object the editor's `core-data` shim consumes via the `__unstableBase`
 * entity. Variations are an extension of the WP shape — they carry the
 * theme's declared `styles.variations` list so the inspector can render a
 * variation picker without a second request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor;

use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedGlobalStyles;

class GlobalStylesAdapter
{
	/**
	 * Singleton sentinel id used when no DB row backs the active theme's
	 * global styles yet — the shim needs a stable identifier so its
	 * cache key doesn't churn between the initial theme-defaults read
	 * and the post-customization DB-backed read.
	 */
	protected const SINGLETON_ID = '__base__';

	/**
	 * Single-record `__unstableBase` envelope.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     id: int|string,
	 *     theme: string,
	 *     settings: array<string, mixed>,
	 *     styles: array<string, mixed>,
	 *     variations: array<int, array<string, mixed>>
	 * }
	 */
	public function toArray( ResolvedGlobalStyles $globalStyles ): array
	{
		// cms-framework's `ResolvedGlobalStyles::wpId()` returns `0` (not
		// `null`) when no DB row backs the active theme; treat both as
		// "use the sentinel" so the shim's cache key stays stable across
		// the theme-defaults → DB-customized transition.
		$wpId = $globalStyles->wpId;

		return [
			'id'         => null !== $wpId && $wpId > 0 ? $wpId : self::SINGLETON_ID,
			'theme'      => $globalStyles->theme,
			'settings'   => $globalStyles->settings,
			'styles'     => $globalStyles->styles,
			'variations' => $globalStyles->variations,
		];
	}
}
