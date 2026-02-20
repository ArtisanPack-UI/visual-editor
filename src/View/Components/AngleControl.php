<?php

/**
 * Angle Control Component.
 *
 * A circular angle picker for rotations and gradients with a synced number input.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Angle Control component with circular picker and number input.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class AngleControl extends Component
{
	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id    Optional custom ID.
	 * @param string|null $label Label text.
	 * @param float|int   $value Current angle value.
	 * @param float|int   $min   Minimum value.
	 * @param float|int   $max   Maximum value.
	 * @param float|int   $step  Step increment.
	 * @param string|null $hint  Hint text.
	 * @param string|null $hintClass CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public int|float $value = 0,
		public int|float $min = 0,
		public int|float $max = 360,
		public int|float $step = 1,
		public ?string $hint = null,
		public ?string $hintClass = 'fieldset-label',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.angle-control' );
	}
}
