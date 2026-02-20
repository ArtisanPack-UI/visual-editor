<?php

/**
 * Toolbar Button Component.
 *
 * An action button for toolbars with icon, label, active state,
 * and tooltip with shortcut hint.
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
 * Toolbar Button component for toolbar actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ToolbarButton extends Component
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
	 * @param string|null $icon     Icon name.
	 * @param bool        $active   Whether the button is in active/pressed state.
	 * @param bool        $disabled Whether the button is disabled.
	 * @param string|null $tooltip  Tooltip text.
	 * @param string|null $shortcut Keyboard shortcut display hint.
	 * @param string      $variant  Button variant: default, active, destructive.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public ?string $icon = null,
		public bool $active = false,
		public bool $disabled = false,
		public ?string $tooltip = null,
		public ?string $shortcut = null,
		public string $variant = 'default',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->variant, [ 'default', 'active', 'destructive' ], true ) ) {
			$this->variant = 'default';
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
		return view( 'visual-editor::components.toolbar-button' );
	}
}
