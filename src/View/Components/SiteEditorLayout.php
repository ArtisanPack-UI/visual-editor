<?php

/**
 * Site Editor Layout Component.
 *
 * The persistent layout shell for the site editor, providing a left
 * sidebar with navigation and a main content area for each section.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Site Editor Layout component providing the persistent navigation shell.
 *
 * Usage:
 *   <x-ve-site-editor-layout
 *       :nav-items="$navItems"
 *       active-section="templates"
 *       section-title="Templates"
 *   >
 *       {{-- Section content --}}
 *   </x-ve-site-editor-layout>
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class SiteEditorLayout extends Component
{
	/**
	 * Navigation items for the sidebar.
	 *
	 * Each item should have: slug, label, icon (SVG path), url, and optionally count.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $navItems;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $navItems      Navigation items for the sidebar.
	 * @param string                           $activeSection Currently active section slug.
	 * @param string                           $sectionTitle  Title displayed in the sidebar header.
	 * @param string|null                      $actionUrl     URL for the context-specific action button.
	 * @param string|null                      $actionLabel   Label for the action button.
	 * @param string                           $sidebarWidth  CSS width for the sidebar.
	 */
	public function __construct(
		array $navItems = [],
		public string $activeSection = '',
		public string $sectionTitle = '',
		public ?string $actionUrl = null,
		public ?string $actionLabel = null,
		public string $sidebarWidth = '280px',
	) {
		$this->navItems = veApplyFilters( 've.site-editor.nav-items', $navItems );
	}

	/**
	 * Get the hub URL for the back navigation.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function hubUrl(): string
	{
		$prefix = (string) config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );
		$prefix = (string) veApplyFilters( 've.site-editor.route-prefix', $prefix );

		return url( $prefix );
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.site-editor-layout' );
	}
}
