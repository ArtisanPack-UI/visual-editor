<?php

declare( strict_types=1 );

/**
 * Colorpicker Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-colorpicker component
 * from the livewire-ui-components package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.8.0
 */

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Colorpicker component stub.
 *
 * @since 1.8.0
 */
class ColorpickerComponentStub extends Component
{
	/**
	 * The colorpicker label.
	 *
	 * @since 1.8.0
	 *
	 * @var string|null
	 */
	public ?string $label;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.8.0
	 *
	 * @param string|null $label The colorpicker label.
	 */
	public function __construct( ?string $label = null )
	{
		$this->label = $label;
	}

	/**
	 * Get the view / contents that represent the component.
	 *
	 * @since 1.8.0
	 *
	 * @return string
	 */
	public function render(): string
	{
		$label = null !== $this->label ? '<label>' . e( $this->label ) . '</label>' : '';

		return '<div>' . $label . '<input type="color" class="colorpicker-stub" /></div>';
	}
}
