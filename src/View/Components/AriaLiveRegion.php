<?php

/**
 * ARIA Live Region Component.
 *
 * Provides a global announcement system for screen readers using
 * ARIA live regions with polite and assertive priorities.
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
 * ARIA Live Region component for screen reader announcements.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class AriaLiveRegion extends Component
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
	 * @param string|null $id         Optional custom ID.
	 * @param string      $priority   Default priority level (polite or assertive).
	 * @param int         $clearAfter Milliseconds before clearing the message (0 = never).
	 * @param int         $debounce   Milliseconds to debounce rapid announcements.
	 */
	public function __construct(
		public ?string $id = null,
		public string $priority = 'polite',
		public int $clearAfter = 5000,
		public int $debounce = 100,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->priority, [ 'polite', 'assertive' ], true ) ) {
			$this->priority = 'polite';
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
		return view( 'visual-editor::components.aria-live-region' );
	}
}
