<?php

/**
 * Inspector Controls Component.
 *
 * Auto-generates inspector panels from block supports, schemas,
 * and custom inspector views. Renders three tabs: Settings, Styles, Advanced.
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

use ArtisanPackUI\VisualEditor\Inspector\SupportsPanelRegistry;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Inspector controls component that auto-generates panels.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      2.0.0
 */
class InspectorControls extends Component
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
	 * @param string|null $blockType The currently selected block type.
	 * @param string|null $blockId   The currently selected block ID.
	 * @param string|null $id        Optional custom ID.
	 */
	public function __construct(
		public ?string $blockType = null,
		public ?string $blockId = null,
		public ?string $id = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
	}

	/**
	 * Get the block instance if a type is selected.
	 *
	 * @since 2.0.0
	 *
	 * @return \ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface|null
	 */
	public function block(): ?\ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface
	{
		if ( null === $this->blockType ) {
			return null;
		}

		$registry = app( 'visual-editor.blocks' );

		return $registry->get( $this->blockType );
	}

	/**
	 * Get supports panels for the current block.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function supportsPanels(): array
	{
		$block = $this->block();

		if ( null === $block ) {
			return [];
		}

		$panelRegistry = app( SupportsPanelRegistry::class );

		return $panelRegistry->getPanelsForBlock( $block );
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
		return view( 'visual-editor::components.inspector-controls' );
	}
}
