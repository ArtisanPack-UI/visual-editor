<?php

/**
 * Inspector Section Component.
 *
 * Wraps custom inspector controls and targets them to a specific
 * inspector tab (settings or styles).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Inspector section component for targeting specific inspector tabs.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      2.0.0
 */
class InspectorSection extends Component
{
	/**
	 * Valid target tabs.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, string>
	 */
	public const VALID_TARGETS = [ 'settings', 'styles' ];

	/**
	 * Create a new component instance.
	 *
	 * @since 2.0.0
	 *
	 * @param string $target The inspector tab to render in (settings or styles).
	 */
	public function __construct(
		public string $target = 'settings',
	) {
		if ( ! in_array( $this->target, self::VALID_TARGETS, true ) ) {
			$this->target = 'settings';
		}
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 2.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.inspector-section' );
	}
}
