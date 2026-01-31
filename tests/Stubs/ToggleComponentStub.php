<?php

declare( strict_types=1 );

/**
 * Toggle Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-toggle component
 * from the livewire-ui-components package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.4.0
 */

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Toggle component stub.
 *
 * @since 1.4.0
 */
class ToggleComponentStub extends Component
{
	/**
	 * The toggle label.
	 *
	 * @since 1.4.0
	 *
	 * @var string|null
	 */
	public ?string $label;

	/**
	 * Whether the toggle is checked.
	 *
	 * @since 1.4.0
	 *
	 * @var bool
	 */
	public bool $checked;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.4.0
	 *
	 * @param string|null $label   The toggle label.
	 * @param bool        $checked Whether the toggle is checked.
	 */
	public function __construct( ?string $label = null, bool $checked = false )
	{
		$this->label   = $label;
		$this->checked = $checked;
	}

	/**
	 * Get the view / contents that represent the component.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	public function render(): string
	{
		$label   = e( $this->label ?? '' );
		$checked = $this->checked ? ' checked' : '';

		return '<div><label>' . $label . '</label><input type="checkbox" class="toggle-stub"' . $checked . ' /></div>';
	}
}
