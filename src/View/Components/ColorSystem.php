<?php

/**
 * Color System Component.
 *
 * A color picker with palette selection, custom hex input, and optional contrast checking.
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
use Throwable;

/**
 * Color System component with palette picker and contrast checking.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ColorSystem extends Component
{

	/**
	 * The default color palette.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_PALETTE = [
		'#000000', '#374151', '#6B7280', '#9CA3AF', '#D1D5DB', '#FFFFFF',
		'#EF4444', '#F97316', '#EAB308', '#22C55E', '#3B82F6', '#8B5CF6',
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
	 * @param string|null $label              Label text.
	 * @param string|null $value              Currently selected color (hex).
	 * @param array|null  $palette            Color palette array of hex values.
	 * @param bool        $showCustom         Whether to show custom color input.
	 * @param bool        $showContrast       Whether to show contrast indicator.
	 * @param string      $contrastBackground Background color for contrast checking.
	 * @param string|null $hint               Hint text.
	 * @param string|null $hintClass          CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public ?string $value = null,
		public ?array $palette = null,
		public bool $showCustom = true,
		public bool $showContrast = false,
		public string $contrastBackground = '#ffffff',
		public ?string $hint = null,
		public ?string $hintClass = 'fieldset-label',
	) {
		$this->uuid = 've-' . md5( serialize( $this ) ) . $id;

		if ( null === $this->palette ) {
			$this->palette = self::DEFAULT_PALETTE;
		}
	}

	/**
	 * Check if two colors have sufficient contrast.
	 *
	 * @since 1.0.0
	 *
	 * @param string $foreground Foreground color hex.
	 * @param string $background Background color hex.
	 *
	 * @return bool|null Null if accessibility package is not available.
	 */
	public function checkContrast( string $foreground, string $background ): ?bool
	{
		if ( function_exists( 'a11yCheckContrastColor' ) ) {
			try {
				return a11yCheckContrastColor( $foreground, $background );
			} catch ( Throwable $e ) {
				return null;
			}
		}

		return null;
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
		return view( 'visual-editor::components.color-system' );
	}
}
