<?php

/**
 * Menu adapter — WP `wp_navigation` REST shape.
 *
 * Keyed by theme-declared location (`primary`, `footer`, ...) since menus
 * in V1 are addressable by location rather than free-form id. The location
 * key serves as the WP REST `slug`; the menu's display name maps to
 * `title.{raw,rendered}`.
 *
 * Items ship inline as a sibling field rather than being merged into a
 * serialized `content.raw` block tree — the inspector sidebar reads them
 * directly to avoid a parse round-trip on every render. The `core/navigation`
 * block resolver still consumes them through cms-framework's H4 resolver
 * at render time, so the front-end never sees this REST shape.
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

use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedMenu;

class MenuAdapter
{
	/**
	 * Single-record `wp_navigation` envelope.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     id: int|string,
	 *     slug: string,
	 *     type: string,
	 *     status: string,
	 *     title: array{rendered: string, raw: string},
	 *     items: array<int, array<string, mixed>>
	 * }
	 */
	public function toArray( ResolvedMenu $menu ): array
	{
		return [
			'id'     => $menu->wpId ?? $menu->location,
			'slug'   => $menu->location,
			'type'   => 'wp_navigation',
			'status' => 'publish',
			'title'  => [
				'rendered' => $menu->name,
				'raw'      => $menu->name,
			],
			'items'  => $menu->items,
		];
	}

	/**
	 * Index envelope — flat list of single-record envelopes. See
	 * {@see TemplateAdapter::collection()} for the rationale on skipping
	 * pagination at this stage.
	 *
	 * @since 1.0.0
	 *
	 * @param  iterable<ResolvedMenu>  $menus
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function collection( iterable $menus ): array
	{
		$out = [];

		foreach ( $menus as $menu ) {
			$out[] = $this->toArray( $menu );
		}

		return $out;
	}
}
