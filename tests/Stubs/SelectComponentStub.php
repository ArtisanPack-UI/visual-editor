<?php

declare( strict_types=1 );

/**
 * Select Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-select component
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
 * Select component stub.
 *
 * @since 1.4.0
 */
class SelectComponentStub extends Component
{
	/**
	 * The select label.
	 *
	 * @since 1.4.0
	 *
	 * @var string|null
	 */
	public ?string $label;

	/**
	 * The select options.
	 *
	 * @since 1.4.0
	 *
	 * @var array
	 */
	public array $options;

	/**
	 * The selected value.
	 *
	 * @since 1.4.0
	 *
	 * @var string|null
	 */
	public ?string $selected;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.4.0
	 *
	 * @param string|null $label       The select label.
	 * @param array       $options     The select options.
	 * @param string|null $optionValue The option value key.
	 * @param string|null $optionLabel The option label key.
	 * @param string|null $selected    The selected value.
	 */
	public function __construct(
		?string $label = null,
		array $options = [],
		?string $optionValue = null,
		?string $optionLabel = null,
		?string $selected = null,
	) {
		$this->label    = $label;
		$this->options  = $options;
		$this->selected = $selected;
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
		$options = '';

		foreach ( $this->options as $option ) {
			$val      = e( $option['id'] ?? '' );
			$name     = e( $option['name'] ?? '' );
			$selected = null !== $this->selected && (string) ( $option['id'] ?? '' ) === $this->selected ? ' selected' : '';
			$options .= '<option value="' . $val . '"' . $selected . '>' . $name . '</option>';
		}

		return '<div><label>' . $label . '</label><select class="select-stub">' . $options . '</select></div>';
	}
}
