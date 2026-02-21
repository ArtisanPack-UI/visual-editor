<?php

/**
 * Animate Presence Component.
 *
 * A wrapper component that provides consistent, accessible animations
 * with prefers-reduced-motion support.
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
 * Animate Presence component for enter/leave animations with reduced motion support.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class AnimatePresence extends Component
{
	/**
	 * Available animation presets.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ANIMATION_PRESETS = [
		'fade',
		'slide-up',
		'slide-down',
		'slide-left',
		'slide-right',
		'scale',
		'collapse',
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
	 * @param string|null $id        Optional custom ID.
	 * @param string      $animation Animation preset name.
	 * @param int         $duration  Animation duration in milliseconds.
	 * @param string      $easing    CSS easing function.
	 * @param bool        $show      Whether content is visible.
	 */
	public function __construct(
		public ?string $id = null,
		public string $animation = 'fade',
		public int $duration = 200,
		public string $easing = 'ease-in-out',
		public bool $show = true,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->animation, self::ANIMATION_PRESETS, true ) ) {
			$this->animation = 'fade';
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
		return view( 'visual-editor::components.animate-presence' );
	}
}
