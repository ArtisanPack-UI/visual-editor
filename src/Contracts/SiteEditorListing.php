<?php

/**
 * Site Editor Listing Interface.
 *
 * Contract that all swappable listing page components must implement.
 * This allows config-based class swaps to replace any listing page
 * while guaranteeing a consistent API for column definitions and rendering.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Contracts
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Contracts;

/**
 * Interface for site editor listing page components.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Contracts
 *
 * @since      1.0.0
 */
interface SiteEditorListing extends SiteEditorPage
{
	/**
	 * Get the column definitions for the listing table.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getColumns(): array;

	/**
	 * Get the row actions for a given item.
	 *
	 * Returns custom actions added via the `ve.listing.actions` filter hook.
	 * Default actions (duplicate, lock, delete) are built-in to the view.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item The listing row item.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getRowActions( object $item ): array;
}
