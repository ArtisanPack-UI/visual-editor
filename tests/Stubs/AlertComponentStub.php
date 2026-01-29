<?php

declare( strict_types=1 );

/**
 * Alert Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-alert component
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
 * Alert component stub.
 *
 * @since 1.0.0
 */
class AlertComponentStub extends Component
{
	/**
	 * The alert title.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $title;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $title The alert title.
	 * @param string|null $color The alert color.
	 * @param string|null $icon  The alert icon.
	 */
	public function __construct(
		?string $title = null,
		?string $color = null,
		?string $icon = null,
	) {
		$this->title = $title;
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
		return '<div class="alert-stub">' . ( $this->title ?? '' ) . '</div>';
	}
}
