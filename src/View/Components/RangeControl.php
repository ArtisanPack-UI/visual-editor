<?php

/**
 * Range Control Component.
 *
 * A range slider synced with a number input and an optional reset button.
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
 * Range Control component with slider, number input, and reset.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class RangeControl extends Component
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
	 * @param string|null          $id           Optional custom ID.
	 * @param string|null          $label        Label text.
	 * @param float|int|string|null $value       Current value.
	 * @param float|int            $min          Minimum value.
	 * @param float|int            $max          Maximum value.
	 * @param float|int            $step         Step increment.
	 * @param float|int|string|null $defaultValue Default value for reset.
	 * @param bool                 $showInput    Whether to show the number input.
	 * @param bool                 $showReset    Whether to show the reset button.
	 * @param string|null          $hint         Hint text.
	 * @param string|null          $hintClass    CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string|int|float|null $value = null,
		public int|float $min = 0,
		public int|float $max = 100,
		public int|float $step = 1,
		public string|int|float|null $defaultValue = null,
		public bool $showInput = true,
		public bool $showReset = true,
		public ?string $hint = null,
		public ?string $hintClass = 'fieldset-label',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . $id;
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
		return view( 'visual-editor::components.range-control' );
	}
}
