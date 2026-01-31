<?php

declare( strict_types=1 );

/**
 * Badge Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-badge component
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
 * Badge component stub.
 *
 * @since 1.0.0
 */
class BadgeComponentStub extends Component
{
	/**
	 * The badge value.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $value;

	/**
	 * The badge color.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $color;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $value The badge value.
	 * @param string|null $color The badge color.
	 */
	public function __construct( ?string $value = null, ?string $color = null )
	{
		$this->value = $value;
		$this->color = $color;
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
		return '<span class="badge-stub">' . e( $this->value ?? '' ) . '</span>';
	}
}
