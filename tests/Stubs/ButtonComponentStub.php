<?php

declare( strict_types=1 );

/**
 * Button Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-button component
 * from the livewire-ui-components package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.0.0
 */

namespace Tests\Stubs;

use Closure;
use Illuminate\View\Component;

/**
 * Button component stub.
 *
 * @since 1.0.0
 */
class ButtonComponentStub extends Component
{
	/**
	 * The button label.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $label;

	/**
	 * The button icon.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $icon;

	/**
	 * The button variant.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $variant;

	/**
	 * The button color.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $color;

	/**
	 * The button size.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $size;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $label   The button label.
	 * @param string|null $icon    The button icon.
	 * @param string|null $variant The button variant.
	 * @param string|null $color   The button color.
	 * @param string|null $size    The button size.
	 * @param string|null $spinner The spinner target.
	 * @param string|null $tooltip The tooltip text.
	 */
	public function __construct(
		?string $label = null,
		?string $icon = null,
		?string $variant = null,
		?string $color = null,
		?string $size = null,
		?string $spinner = null,
		?string $tooltip = null,
	) {
		$this->label   = $label;
		$this->icon    = $icon;
		$this->variant = $variant;
		$this->color   = $color;
		$this->size    = $size;
	}

	/**
	 * Get the view / contents that represent the component.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function render(): \Illuminate\View\View|Closure|string
	{
		return function ( array $data ) {
			$content = $data['label'] ?? $data['slot'] ?? '';

			return '<button class="btn-stub">' . e( $content ) . '</button>';
		};
	}
}
