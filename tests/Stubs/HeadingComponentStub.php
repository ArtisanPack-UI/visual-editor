<?php

declare( strict_types=1 );

/**
 * Heading Component Stub for Testing.
 *
 * Provides a simple stub for the artisanpack-heading component
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
 * Heading component stub.
 *
 * @since 1.0.0
 */
class HeadingComponentStub extends Component
{
	/**
	 * The heading level.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $level;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $level    The heading level.
	 * @param string|null $size     The heading size.
	 * @param bool|null   $semibold Whether to use semibold.
	 * @param bool|null   $bold     Whether to use bold.
	 */
	public function __construct(
		string $level = '1',
		?string $size = null,
		?bool $semibold = false,
		?bool $bold = false,
	) {
		$this->level = $level;
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
		$level = e( $this->level );
		return '<h' . $level . ' class="heading-stub"></h' . $level . '>';
	}
}
