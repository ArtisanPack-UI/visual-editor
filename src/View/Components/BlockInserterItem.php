<?php

/**
 * Block Inserter Item Component.
 *
 * A single block entry within the inserter list, showing icon,
 * name, and description. Supports click-to-insert and drag-to-insert.
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
 * Block Inserter Item component for individual block entries.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class BlockInserterItem extends Component
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
	 * @param string      $name        Block type name.
	 * @param string|null $label       Display label for the block.
	 * @param string|null $description Block description text.
	 * @param string|null $icon        Icon name or SVG reference.
	 * @param string      $category    Category slug this block belongs to.
	 * @param bool        $draggable   Whether this item can be dragged to the canvas.
	 */
	public function __construct(
		public ?string $id = null,
		public string $name = '',
		public ?string $label = null,
		public ?string $description = null,
		public ?string $icon = null,
		public string $category = 'text',
		public bool $draggable = true,
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
		return view( 'visual-editor::components.block-inserter-item' );
	}
}
