<?php

/**
 * Focus Trap Component.
 *
 * Traps keyboard focus within a container for modals, popovers,
 * and other overlay UI. Supports focus restoration and auto-focus.
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
 * Focus Trap component for constraining keyboard navigation within a region.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class FocusTrap extends Component
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
	 * @param string|null $id           Optional custom ID.
	 * @param bool        $active       Whether the focus trap is active.
	 * @param bool        $restoreFocus Whether to restore focus on deactivation.
	 * @param bool        $autoFocus    Whether to auto-focus first element on activation.
	 * @param bool        $inert        Whether to make elements outside the trap inert.
	 * @param string|null $initialFocus CSS selector for the element to focus on activation.
	 */
	public function __construct(
		public ?string $id = null,
		public bool $active = true,
		public bool $restoreFocus = true,
		public bool $autoFocus = true,
		public bool $inert = false,
		public ?string $initialFocus = null,
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
		return view( 'visual-editor::components.focus-trap' );
	}
}
