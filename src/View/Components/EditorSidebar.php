<?php

/**
 * Editor Sidebar Component.
 *
 * The right-hand sidebar shell that hosts the block inserter panel
 * and block inspector panels with block/document tabs.
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
 * Editor Sidebar component for settings and inspector panels.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class EditorSidebar extends Component
{
	/**
	 * Valid tab options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const TABS = [
		'block',
		'document',
	];

	/**
	 * Valid block sub-tab options.
	 *
	 * @since 1.1.0
	 *
	 * @var array<int, string>
	 */
	public const BLOCK_SUB_TABS = [
		'settings',
		'styles',
		'advanced',
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
	 * @param string|null $id                Optional custom ID.
	 * @param string|null $label             Accessible label. Defaults to translation.
	 * @param string      $activeTab         Initially active tab: block or document.
	 * @param bool        $showTabs          Whether to show block/document tab switcher.
	 * @param string      $activeBlockSubTab Initially active block sub-tab: settings, styles, or advanced.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string $activeTab = 'block',
		public bool $showTabs = true,
		public string $activeBlockSubTab = 'settings',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->activeTab, self::TABS, true ) ) {
			$this->activeTab = 'block';
		}

		if ( ! in_array( $this->activeBlockSubTab, self::BLOCK_SUB_TABS, true ) ) {
			$this->activeBlockSubTab = 'settings';
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
		return view( 'visual-editor::components.editor-sidebar' );
	}
}
