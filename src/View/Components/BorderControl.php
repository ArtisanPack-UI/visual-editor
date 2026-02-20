<?php

/**
 * Border Control Component.
 *
 * A composite control for border editing including width, style, color, and per-side configuration.
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
 * Border Control component for complete border configuration.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class BorderControl extends Component
{

	/**
	 * The default border styles.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_STYLES = [ 'none', 'solid', 'dashed', 'dotted', 'double' ];

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
	 * @param string      $width      Border width value.
	 * @param string      $widthUnit  Border width unit.
	 * @param string      $style      Border style.
	 * @param string      $color      Border color (hex).
	 * @param array|null  $styles     Available border styles.
	 * @param bool        $perSide    Whether per-side mode is enabled.
	 * @param string      $radius     Border radius value.
	 * @param string      $radiusUnit Border radius unit.
	 * @param bool        $perCorner  Whether per-corner radius mode is enabled.
	 * @param array|null  $palette    Color palette for the color picker.
	 * @param string|null $hint       Hint text.
	 * @param string|null $hintClass  CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string $width = '1',
		public string $widthUnit = 'px',
		public string $style = 'solid',
		public string $color = '#000000',
		public ?array $styles = null,
		public bool $perSide = false,
		public string $radius = '0',
		public string $radiusUnit = 'px',
		public bool $perCorner = false,
		public ?array $palette = null,
		public ?string $hint = null,
		public ?string $hintClass = 'fieldset-label',
	) {
		$this->uuid = 've-' . md5( serialize( $this ) ) . $id;

		if ( null === $this->styles ) {
			$this->styles = self::DEFAULT_STYLES;
		}
	}

	/**
	 * Get the style options formatted for the select component.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{id: string, name: string}>
	 */
	public function styleOptions(): array
	{
		return array_map( function ( string $style ): array {
			return [
				'id'   => $style,
				'name' => ucfirst( $style ),
			];
		}, $this->styles );
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
		return view( 'visual-editor::components.border-control' );
	}
}
