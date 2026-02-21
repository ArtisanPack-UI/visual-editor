<?php

/**
 * Block Inserter Category Component.
 *
 * A category section header and filter within the block inserter.
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
 * Block Inserter Category component for categorized block listing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class BlockInserterCategory extends Component
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
	 * @param string|null $id    Optional custom ID.
	 * @param string      $name  Category slug.
	 * @param string|null $label Display label. Defaults to translation.
	 * @param string|null $icon  Optional category icon name.
	 * @param int         $count Number of blocks in this category.
	 */
	public function __construct(
		public ?string $id = null,
		public string $name = '',
		public ?string $label = null,
		public ?string $icon = null,
		public int $count = 0,
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
		return view( 'visual-editor::components.block-inserter-category' );
	}
}
