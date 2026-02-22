<?php

/**
 * Block Toolbar Component.
 *
 * A floating toolbar that appears above the currently selected block,
 * with block-specific controls and common actions (move, delete, etc.).
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
 * Block Toolbar component for per-block actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class BlockToolbar extends Component
{
	/**
	 * Valid placement options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const PLACEMENTS = [
		'top',
		'bottom',
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
	 * @param string|null $label            Accessible label. Defaults to translation.
	 * @param string|null $blockType        Current block type for slot-fill naming.
	 * @param bool        $showMoveControls Whether to show move up/down buttons.
	 * @param bool        $showMoreOptions  Whether to show the more options dropdown.
	 * @param string      $placement        Placement relative to block: top or bottom.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public ?string $blockType = null,
		public bool $showMoveControls = true,
		public bool $showMoreOptions = true,
		public string $placement = 'top',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->placement, self::PLACEMENTS, true ) ) {
			$this->placement = 'top';
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
		return view( 'visual-editor::components.block-toolbar' );
	}
}
