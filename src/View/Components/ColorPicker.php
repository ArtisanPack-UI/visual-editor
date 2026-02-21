<?php

/**
 * Color Picker Component.
 *
 * A WordPress-style color picker with saturation/brightness canvas, hue slider,
 * optional alpha slider, format toggle, and hex input. Pure Alpine.js implementation.
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
 * Color Picker component with canvas, hue slider, and format options.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ColorPicker extends Component
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
	 * @param string|null $id               Optional custom ID.
	 * @param string      $value            Initial color value (hex).
	 * @param bool        $showAlpha        Whether to show the alpha/opacity slider.
	 * @param bool        $showFormatToggle Whether to show the Hex/RGB/HSL format selector.
	 * @param bool        $showCopyButton   Whether to show the copy-to-clipboard button.
	 * @param string      $width            Width of the component.
	 * @param string|null $hint             Hint text.
	 * @param string|null $hintClass        CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public string $value = '#000000',
		public bool $showAlpha = false,
		public bool $showFormatToggle = true,
		public bool $showCopyButton = true,
		public string $width = '100%',
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
		return view( 'visual-editor::components.color-picker' );
	}
}
