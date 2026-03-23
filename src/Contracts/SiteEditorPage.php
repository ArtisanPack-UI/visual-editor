<?php

/**
 * Site Editor Page Interface.
 *
 * Contract that all swappable site editor page components must implement.
 * This allows config-based class swaps to replace any site editor page
 * while guaranteeing a consistent API.
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

use Illuminate\Contracts\View\View;

/**
 * Interface for site editor page components.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Contracts
 *
 * @since      1.0.0
 */
interface SiteEditorPage
{
	/**
	 * Render the page component.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View;
}
