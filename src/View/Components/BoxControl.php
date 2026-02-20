<?php

/**
 * Box Control Component.
 *
 * A four-sided input control (top/right/bottom/left) with a linked/unlinked toggle.
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
 * Box Control component for top/right/bottom/left spacing with link toggle.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class BoxControl extends Component
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
	 * @param string|null          $id     Optional custom ID.
	 * @param string|null          $label  Label text.
	 * @param float|int|string|null $top    Top value.
	 * @param float|int|string|null $right  Right value.
	 * @param float|int|string|null $bottom Bottom value.
	 * @param float|int|string|null $left   Left value.
	 * @param string               $unit   Current unit.
	 * @param array                $units  Available units.
	 * @param bool                 $linked Whether all sides are linked.
	 * @param float|int|string|null $min   Minimum value.
	 * @param float|int|string|null $max   Maximum value.
	 * @param float|int|string     $step   Step increment.
	 * @param string|null          $hint   Hint text.
	 * @param string|null          $hintClass CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string|int|float|null $top = null,
		public string|int|float|null $right = null,
		public string|int|float|null $bottom = null,
		public string|int|float|null $left = null,
		public string $unit = 'px',
		public array $units = [ 'px', 'em', 'rem', '%' ],
		public bool $linked = true,
		public string|int|float|null $min = null,
		public string|int|float|null $max = null,
		public string|int|float $step = 1,
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
		return view( 'visual-editor::components.box-control' );
	}
}
