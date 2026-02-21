<?php

/**
 * Canvas Empty State Component.
 *
 * Displays a placeholder when the editor canvas has no blocks,
 * with a message and an inserter trigger button.
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
 * Canvas Empty State component shown when no blocks exist.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class CanvasEmptyState extends Component
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
	 * @param string|null $id          Optional custom ID.
	 * @param string|null $title       Heading text. Defaults to translation.
	 * @param string|null $description Body text. Defaults to translation.
	 * @param string|null $buttonLabel Button text. Defaults to translation.
	 * @param string|null $icon        Optional icon name for the button.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $title = null,
		public ?string $description = null,
		public ?string $buttonLabel = null,
		public ?string $icon = null,
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
		return view( 'visual-editor::components.canvas-empty-state' );
	}
}
