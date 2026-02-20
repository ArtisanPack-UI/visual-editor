<?php

/**
 * Font Size Picker Component.
 *
 * A preset size picker with optional custom input mode.
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
use Illuminate\View\Component;

/**
 * Font Size Picker component with preset sizes and custom input.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class FontSizePicker extends Component
{

	/**
	 * The default size presets.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public const DEFAULT_PRESETS = [
		'S'  => '0.875rem',
		'M'  => '1rem',
		'L'  => '1.25rem',
		'XL' => '1.5rem',
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
	 * @param string|null $id         Optional custom ID.
	 * @param string|null $label      Label text.
	 * @param string|null $value      Currently selected value.
	 * @param array|null  $presets    Preset sizes as label => value pairs.
	 * @param bool        $showCustom Whether to show custom input toggle.
	 * @param string      $unit       Default unit for custom input.
	 * @param array       $units      Available units for custom input.
	 * @param string|null $hint       Hint text.
	 * @param string|null $hintClass  CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public ?string $value = null,
		public ?array $presets = null,
		public bool $showCustom = true,
		public string $unit = 'rem',
		public array $units = [ 'px', 'em', 'rem' ],
		public ?string $hint = null,
		public ?string $hintClass = 'fieldset-label',
	) {
		$this->uuid = 've-' . md5( serialize( $this ) ) . $id;

		if ( null === $this->presets ) {
			$this->presets = self::DEFAULT_PRESETS;
		}
	}

	/**
	 * Get the active preset key based on the current value.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function activePreset(): ?string
	{
		if ( null === $this->value ) {
			return null;
		}

		$key = array_search( $this->value, $this->presets, true );

		return false !== $key ? (string) $key : null;
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
		return view( 'visual-editor::components.font-size-picker' );
	}
}
