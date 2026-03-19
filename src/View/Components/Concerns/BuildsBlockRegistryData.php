<?php

/**
 * Builds Block Registry Data Trait.
 *
 * Extracts shared block registry initialization logic used by both
 * the Editor and TemplateEditor components. Each method populates
 * public properties on the consuming class from the BlockRegistry.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components\Concerns
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components\Concerns;

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\View\Components\Icon;
use Closure;

/**
 * Trait for building block registry data arrays.
 *
 * Classes using this trait must declare the following public properties:
 * - $initialBlocks, $patterns, $blockTransforms, $blockVariations
 * - $iconRenderer (Closure)
 * - $inserterBlocks, $renderedBlocks, $defaultBlockTemplates, $blockMetadata
 * - $inspectorBlockNames, $inspectorBlockDescriptions, $inspectorBlockTypes
 * - $toolbarBlockIcons, $transformableBlocks, $blockNames, $blockAlignSupports
 * - $customToolbarHtml, $editorShortcuts, $patternsWithPreviews
 * - $defaultInnerBlocksMap
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components\Concerns
 *
 * @since      1.0.0
 */
trait BuildsBlockRegistryData
{
	/**
	 * Create the default icon renderer.
	 *
	 * Uses inline SVGs to avoid dependency on livewire-ui-components
	 * in the package views (which causes test failures).
	 *
	 * @since 1.0.0
	 *
	 * @return Closure
	 */
	protected function defaultIconRenderer(): Closure
	{
		return function ( string $iconName ): string {
			$fallback = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>';

			if ( '_default' === $iconName ) {
				return $fallback;
			}

			if ( 'heading' === $iconName ) {
				return '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-size="16" font-weight="700" font-family="system-ui, sans-serif">H</text></svg>';
			}

			$svgMap = Icon::getSvgMap();

			if ( isset( $svgMap[ $iconName ] ) ) {
				return str_replace( '<svg ', '<svg class="w-5 h-5" ', $svgMap[ $iconName ] );
			}

			// Try without common prefixes (o-, s-, fa-).
			$stripped = (string) preg_replace( '/^(o-|s-|fa-)/', '', $iconName );
			if ( $stripped !== $iconName && isset( $svgMap[ $stripped ] ) ) {
				return str_replace( '<svg ', '<svg class="w-5 h-5" ', $svgMap[ $stripped ] );
			}

			return $fallback;
		};
	}

	/**
	 * Build the inserter blocks from the registry.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildInserterBlocks( BlockRegistry $registry ): void
	{
		$this->inserterBlocks = collect( $registry->all() )
			->filter( fn ( $block ) => $block->isPublic() )
			->map( fn ( $block ) => [
				'name'               => $block->getType(),
				'label'              => $block->getName(),
				'icon'               => $block->getIcon(),
				'category'           => $block->getCategory(),
				'description'        => $block->getDescription(),
				'keywords'           => $block->getKeywords(),
				'defaultInnerBlocks' => $block->getDefaultInnerBlocks(),
			] )
			->values()
			->all();

		$this->defaultInnerBlocksMap = collect( $registry->all() )
			->filter( fn ( $block ) => [] !== $block->getDefaultInnerBlocks() )
			->mapWithKeys( fn ( $block ) => [ $block->getType() => $block->getDefaultInnerBlocks() ] )
			->all();
	}

	/**
	 * Pre-render initial blocks for the canvas.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildRenderedBlocks( BlockRegistry $registry ): void
	{
		$this->renderedBlocks = [];

		foreach ( $this->initialBlocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['id'], $block['type'] ) || ! is_string( $block['id'] ) || ! is_string( $block['type'] ) ) {
				continue;
			}

			$blockType = $registry->get( $block['type'] );
			if ( $blockType ) {
				$this->renderedBlocks[ $block['id'] ] = $blockType->renderEditor(
					$block['attributes'] ?? [],
					$block['styles'] ?? [],
				);
			}
		}
	}

	/**
	 * Pre-render default block templates for each registered type.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildDefaultBlockTemplates( BlockRegistry $registry ): void
	{
		$this->defaultBlockTemplates = [];

		foreach ( $registry->all() as $type => $block ) {
			$defaultContent = [];
			foreach ( $block->getContentSchema() as $key => $field ) {
				$defaultContent[ $key ] = $field['default'] ?? '';
			}

			$this->defaultBlockTemplates[ $type ] = $block->renderEditor( $defaultContent, [] );
		}
	}

	/**
	 * Build block metadata for the JavaScript renderer registry.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildBlockMetadata( BlockRegistry $registry ): void
	{
		$this->blockMetadata = $registry->toArray();
	}

	/**
	 * Build inspector data (block names, descriptions, types).
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildInspectorData( BlockRegistry $registry ): void
	{
		$this->inspectorBlockNames        = [];
		$this->inspectorBlockDescriptions = [];
		$this->inspectorBlockTypes        = [];

		foreach ( $registry->all() as $type => $block ) {
			$this->inspectorBlockNames[ $type ]        = $block->getName();
			$this->inspectorBlockDescriptions[ $type ] = $block->getDescription();
			$this->inspectorBlockTypes[]               = $type;
		}
	}

	/**
	 * Build toolbar icons and transformable block data.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildToolbarData( BlockRegistry $registry ): void
	{
		$this->toolbarBlockIcons   = [];
		$this->transformableBlocks = [];
		$this->blockNames          = [];
		$iconRenderer              = $this->iconRenderer;

		foreach ( $registry->all() as $type => $block ) {
			$this->toolbarBlockIcons[ $type ] = $iconRenderer( $block->getIcon() );
			$this->blockNames[ $type ]        = $block->getName();

			if ( $block->isPublic() ) {
				$this->transformableBlocks[ $type ] = $block->getName();
			}
		}
	}

	/**
	 * Build block alignment support data.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildAlignmentData( BlockRegistry $registry ): void
	{
		$this->blockAlignSupports = [];

		foreach ( $registry->all() as $type => $block ) {
			$alignments = $block->getSupportedAlignments();
			if ( ! empty( $alignments ) ) {
				$this->blockAlignSupports[ $type ] = $alignments;
			}
		}
	}

	/**
	 * Build custom toolbar HTML from blocks.
	 *
	 * Custom inspector HTML is rendered directly by the inspector-controls
	 * component via renderInspector(), so it does not need pre-rendering here.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildCustomPanels( BlockRegistry $registry ): void
	{
		$this->customToolbarHtml = [];

		foreach ( $registry->all() as $type => $block ) {
			if ( $block->hasCustomToolbar() ) {
				$this->customToolbarHtml[ $type ] = $block->renderToolbar();
			}
		}
	}

	/**
	 * Build the default keyboard shortcuts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function buildEditorShortcuts(): void
	{
		$this->editorShortcuts = [
			[ 'name' => 'undo', 'keys' => 'mod+z', 'description' => __( 'visual-editor::ve.undo_action' ), 'category' => 'global' ],
			[ 'name' => 'redo', 'keys' => 'mod+shift+z', 'description' => __( 'visual-editor::ve.redo_action' ), 'category' => 'global' ],
			[ 'name' => 'save', 'keys' => 'mod+s', 'description' => __( 'visual-editor::ve.save' ), 'category' => 'global' ],
			[ 'name' => 'delete-block', 'keys' => 'mod+shift+d', 'description' => __( 'visual-editor::ve.delete_block' ), 'category' => 'block' ],
			[ 'name' => 'duplicate-block', 'keys' => 'mod+d', 'description' => __( 'visual-editor::ve.duplicate_block' ), 'category' => 'block' ],
			[ 'name' => 'move-up', 'keys' => 'mod+shift+up', 'description' => __( 'visual-editor::ve.move_up' ), 'category' => 'block' ],
			[ 'name' => 'move-down', 'keys' => 'mod+shift+down', 'description' => __( 'visual-editor::ve.move_down' ), 'category' => 'block' ],
			[ 'name' => 'select-all', 'keys' => 'mod+a', 'description' => __( 'visual-editor::ve.select_all' ), 'category' => 'selection' ],
			[ 'name' => 'deselect', 'keys' => 'escape', 'description' => __( 'visual-editor::ve.deselect' ), 'category' => 'selection' ],
			[ 'name' => 'toggle-inserter', 'keys' => 'mod+/', 'description' => __( 'visual-editor::ve.toggle_inserter' ), 'category' => 'navigation' ],
		];
	}

	/**
	 * Build block transforms from the registry.
	 *
	 * Collects transform mappings defined by each block's getTransforms()
	 * method and merges them with any externally provided transforms.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildBlockTransforms( BlockRegistry $registry ): void
	{
		$registryTransforms = [];

		foreach ( $registry->all() as $type => $block ) {
			$transforms = $block->getTransforms();
			if ( ! empty( $transforms ) ) {
				$registryTransforms[ $type ] = $transforms;
			}
		}

		$this->blockTransforms = array_merge( $registryTransforms, $this->blockTransforms );
	}

	/**
	 * Build patterns with preview images.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function buildPatternsWithPreviews(): void
	{
		$this->patternsWithPreviews = array_map( function ( $pattern ): array {
			if ( ! is_array( $pattern ) ) {
				return [];
			}

			if ( ! isset( $pattern['preview'] ) ) {
				$name               = e( $pattern['name'] ?? '' );
				$pattern['preview'] = 'data:image/svg+xml,' . rawurlencode(
					'<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200">'
					. '<rect width="400" height="200" fill="#e2e8f0"/>'
					. '<text x="200" y="105" text-anchor="middle" font-family="system-ui,sans-serif" font-size="16" fill="#64748b">' . $name . '</text>'
					. '</svg>',
				);
			}

			return $pattern;
		}, $this->patterns );
	}
}
