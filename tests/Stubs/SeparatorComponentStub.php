<?php

declare( strict_types=1 );

/**
 * Separator Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-separator component
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
 * Separator component stub.
 *
 * @since 1.0.0
 */
class SeparatorComponentStub extends Component
{
	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param bool|null   $vertical Whether the separator is vertical.
	 * @param string|null $color    The separator color.
	 */
	public function __construct( ?bool $vertical = false, ?string $color = null )
	{
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
		return '<span class="separator-stub">|</span>';
	}
}
