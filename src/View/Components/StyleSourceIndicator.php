<?php

/**
 * Style Source Indicator Component.
 *
 * Displays where a block's style value originates in the cascade
 * (global, template, or block override) and provides a "reset to
 * default" button that reverts the block value to the inherited value.
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
 * Style source indicator component for the inspector cascade UI.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class StyleSourceIndicator extends Component
{
	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field   The attribute name to track in the block's style.
	 * @param string $blockId The block ID ('dynamic' for selection-based).
	 */
	public function __construct(
		public string $field = '',
		public string $blockId = 'dynamic',
	) {
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
		return view( 'visual-editor::components.style-source-indicator' );
	}
}
