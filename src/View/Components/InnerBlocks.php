<?php

/**
 * Inner Blocks Component.
 *
 * Renders a container for nested child blocks within a parent block.
 * Supports edit mode (with placeholder and drop zone) and save mode
 * (clean output of rendered inner blocks).
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
 * Inner Blocks component for nested block rendering.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      2.0.0
 */
class InnerBlocks extends Component
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
	 * @param string|null            $id            Optional custom ID.
	 * @param array<string>|null     $allowedBlocks Block types allowed as children (null = all).
	 * @param string                 $orientation   Layout orientation: vertical or horizontal.
	 * @param string|null            $placeholder   Placeholder text when no inner blocks exist.
	 * @param string|null            $parentId      The parent block ID for editor interaction.
	 * @param array<int, string>     $innerBlocks    Pre-rendered inner block HTML strings.
	 * @param bool                   $editing        Whether the block is in edit mode.
	 */
	public function __construct(
		public ?string $id = null,
		public ?array $allowedBlocks = null,
		public string $orientation = 'vertical',
		public ?string $placeholder = null,
		public ?string $parentId = null,
		public array $innerBlocks = [],
		public bool $editing = false,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->orientation, [ 'vertical', 'horizontal' ], true ) ) {
			$this->orientation = 'vertical';
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
		return view( 'visual-editor::components.inner-blocks' );
	}
}
