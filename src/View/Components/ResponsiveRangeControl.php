<?php

/**
 * Responsive Range Control Component.
 *
 * A range control with a global/responsive toggle. In global mode,
 * a single slider sets the value for all breakpoints. In responsive
 * mode, separate sliders appear for desktop, tablet, and mobile.
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
 * Responsive Range Control with global/responsive toggle and range sliders.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ResponsiveRangeControl extends Component
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
	 * @param string|null          $id    Optional custom ID.
	 * @param string|null          $label Label text.
	 * @param array<string, mixed> $value Values with mode, global, desktop, tablet, mobile.
	 * @param float|int            $min   Minimum value.
	 * @param float|int            $max   Maximum value.
	 * @param float|int            $step  Step increment.
	 * @param string|null          $hint  Hint text.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public array $value = [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ],
		public int|float $min = 0,
		public int|float $max = 100,
		public int|float $step = 1,
		public ?string $hint = null,
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
		return view( 'visual-editor::components.responsive-range-control' );
	}
}
