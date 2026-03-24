<?php

/**
 * Style Preview Toolbar Component.
 *
 * A toolbar for the global styles preview panel with responsive
 * viewport switching and before/after comparison toggle.
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
 * Style Preview Toolbar component for the global styles preview panel.
 *
 * Provides device viewport switching and before/after comparison
 * controls for real-time style previewing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class StylePreviewToolbar extends Component
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
	 * @param string|null $id Optional custom ID.
	 */
	public function __construct(
		public ?string $id = null,
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
		return view( 'visual-editor::components.style-preview-toolbar' );
	}
}
