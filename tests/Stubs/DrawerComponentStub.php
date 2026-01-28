<?php

declare( strict_types=1 );

/**
 * Drawer Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-drawer component
 * from the livewire-ui-components package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.0.0
 */

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Drawer component stub.
 *
 * @since 1.0.0
 */
class DrawerComponentStub extends Component
{
	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $title          The drawer title.
	 * @param string|null $subtitle       The drawer subtitle.
	 * @param bool|null   $right          Whether the drawer is on the right.
	 * @param bool|null   $separator      Whether to show a separator.
	 * @param bool|null   $withCloseButton Whether to show a close button.
	 * @param bool|null   $closeOnEscape  Whether to close on escape.
	 */
	public function __construct(
		?string $title = null,
		?string $subtitle = null,
		?bool $right = false,
		?bool $separator = false,
		?bool $withCloseButton = false,
		?bool $closeOnEscape = false,
	) {
	}

	/**
	 * Get the view / contents that represent the component.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function render(): string
	{
		return '<div class="drawer-stub">{{ $slot }}@isset($actions){{ $actions }}@endisset</div>';
	}
}
