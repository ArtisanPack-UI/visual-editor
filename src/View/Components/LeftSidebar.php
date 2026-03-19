<?php

/**
 * Left Sidebar Component.
 *
 * Three-tab panel for Blocks, Patterns, and Layers.
 * Visibility is controlled by the editor store's showInserter property.
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
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Left Sidebar component with Blocks, Patterns, and Layers tabs.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class LeftSidebar extends Component
{
	/**
	 * Valid tab options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const TABS = [
		'blocks',
		'patterns',
		'layers',
	];

	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                        $id         Optional custom ID.
	 * @param string|null                        $label      Accessible label. Defaults to translation.
	 * @param string                             $activeTab  Initially active tab: blocks, patterns, or layers.
	 * @param string                             $width      CSS width for the sidebar.
	 * @param array<int, array<string, string>>  $customTabs Custom tab definitions to replace the defaults.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string $activeTab = 'blocks',
		public string $width = '280px',
		public array $customTabs = [],
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		// Validate activeTab against default tabs or custom tab slugs.
		$validSlugs = ! empty( $this->customTabs )
			? array_column( $this->customTabs, 'slug' )
			: self::TABS;

		if ( ! in_array( $this->activeTab, $validSlugs, true ) ) {
			$this->activeTab = $validSlugs[0] ?? 'blocks';
		}
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
		return view( 'visual-editor::components.left-sidebar' );
	}
}
