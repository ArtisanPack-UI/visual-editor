<?php

/**
 * Background Control Component.
 *
 * Composite control for background settings including image,
 * size, position, and gradient.
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
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Background control component for the block inspector.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      2.0.0
 */
class BackgroundControl extends Component
{
	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Create a new component instance.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $value            The current background values.
	 * @param array       $activeControls   Which sub-controls to show.
	 * @param string|null $blockId          The block ID for dispatching updates.
	 * @param string|null $label            Accessible label.
	 * @param string|null $id               Optional custom ID.
	 */
	public function __construct(
		public array $value = [],
		public array $activeControls = [],
		public ?string $blockId = null,
		public ?string $label = null,
		public ?string $id = null,
	) {
		$this->uuid  = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
		$this->label = $label ?? __( 'visual-editor::ve.background' );
	}

	/**
	 * Check if a specific sub-control is active.
	 *
	 * @since 2.0.0
	 *
	 * @param string $control The control name.
	 *
	 * @return bool
	 */
	public function hasControl( string $control ): bool
	{
		return in_array( $control, $this->activeControls, true );
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
		return view( 'visual-editor::components.background-control' );
	}
}
