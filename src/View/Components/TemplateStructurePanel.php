<?php

/**
 * Template Structure Panel Component.
 *
 * Displays a hierarchical outline of the block tree in the
 * left sidebar, allowing users to navigate blocks in the
 * template editor by clicking to select them.
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
 * Template Structure Panel component for the left sidebar.
 *
 * Renders a tree-style outline showing the template's block
 * structure. Reads blocks from the Alpine editor store and
 * flattens them with depth info for indentation. Clicking
 * an item selects that block in the canvas.
 *
 * Usage:
 *   <x-ve-template-structure-panel :block-names="$blockNames" />
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class TemplateStructurePanel extends Component
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
	 * @param string|null             $id         Optional custom ID.
	 * @param string|null             $label      Accessible label. Defaults to translation.
	 * @param array<string, string>   $blockNames Block type display names keyed by type.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public array $blockNames = [],
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
		return view( 'visual-editor::components.template-structure-panel' );
	}
}
