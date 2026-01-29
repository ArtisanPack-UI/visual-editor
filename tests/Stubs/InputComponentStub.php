<?php

declare( strict_types=1 );

/**
 * Input Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-input component
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
 * Input component stub.
 *
 * @since 1.0.0
 */
class InputComponentStub extends Component
{
	/**
	 * The input type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * The input label.
	 *
	 * @since 1.4.0
	 *
	 * @var string|null
	 */
	public ?string $label;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $type  The input type.
	 * @param string|null $label The input label.
	 */
	public function __construct( string $type = 'text', ?string $label = null )
	{
		$this->type  = $type;
		$this->label = $label;
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
		$label = null !== $this->label ? '<label>' . e( $this->label ) . '</label>' : '';

		return '<div>' . $label . '<input type="' . $this->type . '" class="input-stub" /></div>';
	}
}
