<?php

/**
 * Popover Component.
 *
 * A popover that anchors to elements with smart positioning,
 * auto-flip, auto-shift, and focus management.
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
 * Popover component with smart positioning and accessibility.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class Popover extends Component
{
	/**
	 * Available placement options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const PLACEMENTS = [
		'top',
		'top-start',
		'top-end',
		'bottom',
		'bottom-start',
		'bottom-end',
		'left',
		'left-start',
		'left-end',
		'right',
		'right-start',
		'right-end',
	];

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
	 * @param string|null $id                 Optional custom ID.
	 * @param string      $placement          Preferred placement.
	 * @param int         $offset             Offset distance in pixels.
	 * @param bool        $flip               Whether to auto-flip when near edge.
	 * @param bool        $shift              Whether to auto-shift to stay in viewport.
	 * @param bool        $arrow              Whether to show an arrow.
	 * @param string      $triggerOn          Trigger type: click, hover, or manual.
	 * @param bool        $closeOnClickOutside Whether to close on click outside.
	 * @param bool        $closeOnEscape      Whether to close on Escape key.
	 * @param bool        $trapFocus          Whether to trap focus within popover.
	 * @param string      $animation          Animation preset.
	 * @param string|null $width              CSS width for the popover.
	 * @param string|null $ariaLabel          Accessible label for the popover dialog.
	 */
	public function __construct(
		public ?string $id = null,
		public string $placement = 'bottom',
		public int $offset = 8,
		public bool $flip = true,
		public bool $shift = true,
		public bool $arrow = false,
		public string $triggerOn = 'click',
		public bool $closeOnClickOutside = true,
		public bool $closeOnEscape = true,
		public bool $trapFocus = false,
		public string $animation = 'fade',
		public ?string $width = null,
		public ?string $ariaLabel = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->placement, self::PLACEMENTS, true ) ) {
			$this->placement = 'bottom';
		}

		if ( ! in_array( $this->triggerOn, [ 'click', 'hover', 'manual' ], true ) ) {
			$this->triggerOn = 'click';
		}
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
		return view( 'visual-editor::components.popover' );
	}
}
