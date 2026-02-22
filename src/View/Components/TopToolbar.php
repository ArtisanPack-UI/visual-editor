<?php

/**
 * Top Toolbar Component.
 *
 * The main editor toolbar at the top of the editor layout, containing
 * inserter toggle, undo/redo, device preview, save/publish, and settings.
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
 * Top Toolbar component for the editor's main toolbar.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class TopToolbar extends Component
{
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
	 * @param string|null $id                   Optional custom ID.
	 * @param string|null $label                Accessible label. Defaults to translation.
	 * @param bool        $showInserterToggle   Whether to show the inserter toggle button.
	 * @param bool        $showUndoRedo         Whether to show undo/redo buttons.
	 * @param bool        $showDevicePreview    Whether to show device preview switcher.
	 * @param bool        $showSaveButton       Whether to show the save/publish button.
	 * @param bool        $showSettingsToggle   Whether to show the settings sidebar toggle.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public bool $showInserterToggle = true,
		public bool $showUndoRedo = true,
		public bool $showDevicePreview = true,
		public bool $showSaveButton = true,
		public bool $showSettingsToggle = true,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
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
		return view( 'visual-editor::components.top-toolbar' );
	}
}
