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
		public ?string $tab = null,
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
	 * Get field names already covered by supports panels.
	 *
	 * Used to filter out style schema fields that would otherwise
	 * be rendered twice in the Styles tab.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, string>
	 */
	public function supportsCoveredFields(): array
	{
		$covered = [];

		foreach ( $this->supportsPanels() as $panel ) {
			$covered = array_merge( $covered, $this->collectFieldsFromControls( $panel['controls'] ?? [] ) );
		}

		return $covered;
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

	/**
	 * Recursively collect field names from a controls array.
	 *
	 * Traverses any nesting depth (row, group, or future container types)
	 * to find all field names covered by supports panels.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, array<string, mixed>> $controls The controls to traverse.
	 *
	 * @return array<int, string>
	 */
	protected function collectFieldsFromControls( array $controls ): array
	{
		$fields = [];

		foreach ( $controls as $control ) {
			if ( isset( $control['field'] ) ) {
				$fields[] = $control['field'];
			}

			if ( isset( $control['controls'] ) && is_array( $control['controls'] ) ) {
				$fields = array_merge( $fields, $this->collectFieldsFromControls( $control['controls'] ) );
			}
		}

		return $fields;
	}
}
