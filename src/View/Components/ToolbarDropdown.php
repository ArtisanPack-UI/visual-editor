<?php

/**
 * Toolbar Dropdown Component.
 *
 * A dropdown button for toolbars that opens a popover menu.
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
 * Toolbar Dropdown component for dropdown menus in toolbars.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ToolbarDropdown extends Component
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
	 * @param string|null $id       Optional custom ID.
	 * @param string|null $label    Button label text.
	 * @param string|null $icon     Icon name for the trigger button.
	 * @param string|null $tooltip  Tooltip text.
	 * @param bool        $disabled Whether the dropdown is disabled.
	 * @param string      $placement Popover placement.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public ?string $icon = null,
		public ?string $tooltip = null,
		public bool $disabled = false,
		public string $placement = 'bottom-start',
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
		return view( 'visual-editor::components.toolbar-dropdown' );
	}
}
