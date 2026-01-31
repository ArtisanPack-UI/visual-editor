<?php

declare( strict_types=1 );

/**
 * Icon Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-icon component
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
 * Icon component stub.
 *
 * @since 1.0.0
 */
class IconComponentStub extends Component
{
	/**
	 * The icon name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The icon name.
	 */
	public function __construct( string $name = '' )
	{
		$this->name = $name;
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
		return '<span class="icon-stub">' . e( $this->name ) . '</span>';
	}
}
