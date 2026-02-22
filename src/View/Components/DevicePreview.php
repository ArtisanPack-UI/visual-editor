<?php

/**
 * Device Preview Component.
 *
 * Wraps the editor canvas with a responsive preview container
 * that constrains width for tablet and mobile viewports,
 * with optional zoom controls.
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
 * Device Preview component with responsive width and zoom.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DevicePreview extends Component
{
	/**
	 * Valid device options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DEVICES = [
		'desktop',
		'tablet',
		'mobile',
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
	 * @param string|null $id               Optional custom ID.
	 * @param string      $device           Initial device: desktop, tablet, or mobile.
	 * @param int         $tabletWidth      Tablet preview width in pixels.
	 * @param int         $mobileWidth      Mobile preview width in pixels.
	 * @param bool        $showZoomControls Whether to show zoom buttons.
	 * @param int         $defaultZoom      Initial zoom percentage.
	 * @param int         $minZoom          Minimum zoom percentage.
	 * @param int         $maxZoom          Maximum zoom percentage.
	 */
	public function __construct(
		public ?string $id = null,
		public string $device = 'desktop',
		public int $tabletWidth = 768,
		public int $mobileWidth = 375,
		public bool $showZoomControls = true,
		public int $defaultZoom = 100,
		public int $minZoom = 50,
		public int $maxZoom = 150,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->device, self::DEVICES, true ) ) {
			$this->device = 'desktop';
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
		return view( 'visual-editor::components.device-preview' );
	}
}
