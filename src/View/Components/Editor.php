<?php

/**
 * Editor Component.
 *
 * The high-level orchestration component that assembles the complete
 * visual editor experience. Composes all sub-components (layout, canvas,
 * sidebar, toolbar, inserter, inspector, layers) and wires them together
 * with block rendering, drag-and-drop, and the block renderer registry.
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

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Editor component providing the complete visual editor UI.
 *
 * Usage:
 *   <x-ve-editor
 *       :initial-blocks="$blocks"
 *       :patterns="$patterns"
 *       :autosave="true"
 *       :autosave-interval="30"
 *       document-status="draft"
 *   />
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class Editor extends Component
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
	 * Blocks available for the inserter panel.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $inserterBlocks;

	/**
	 * Pre-rendered block HTML keyed by block ID.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $renderedBlocks;

	/**
	 * Default block templates keyed by block type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $defaultBlockTemplates;

	/**
	 * Block metadata for the JS renderer registry.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $blockMetadata;

	/**
	 * Block names keyed by type for the inspector.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $inspectorBlockNames;

	/**
	 * Block descriptions keyed by type for the inspector.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $inspectorBlockDescriptions;

	/**
	 * List of all registered block types for inspector iteration.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public array $inspectorBlockTypes;

	/**
	 * Pre-rendered toolbar icons keyed by block type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $toolbarBlockIcons;

	/**
	 * Public block names for the transform dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $transformableBlocks;

	/**
	 * Block alignment support map keyed by block type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<int, string>>
	 */
	public array $blockAlignSupports;

	/**
	 * Pre-rendered custom toolbar HTML keyed by block type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $customToolbarHtml;

	/**
	 * Pre-rendered custom inspector HTML keyed by block type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $customInspectorHtml;

	/**
	 * Default keyboard shortcuts for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, string>>
	 */
	public array $editorShortcuts;

	/**
	 * Patterns with preview images.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $patternsWithPreviews;

	/**
	 * Icon renderer closure for block icons.
	 *
	 * @since 1.0.0
	 *
	 * @var Closure
	 */
	public Closure $iconRenderer;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null  $id               Optional custom ID.
	 * @param array<mixed> $initialBlocks    Block data to populate the editor.
	 * @param array<mixed> $patterns         Pattern definitions for the pattern browser.
	 * @param array<mixed> $blockTransforms  Transform mappings between block types.
	 * @param array<mixed> $blockVariations  Variation definitions for blocks.
	 * @param bool         $autosave         Enable autosave.
	 * @param int          $autosaveInterval Autosave interval in seconds.
	 * @param string       $documentStatus   Initial document status.
	 * @param bool         $showSidebar      Show sidebar by default.
	 * @param string       $mode             Editor mode (visual/code).
	 * @param Closure|null $customIconRenderer Optional custom icon renderer.
	 */
	public function __construct(
		public ?string $id = null,
		public array $initialBlocks = [],
		public array $patterns = [],
		public array $blockTransforms = [],
		public array $blockVariations = [],
		public bool $autosave = false,
		public int $autosaveInterval = 60,
		public string $documentStatus = 'draft',
		public bool $showSidebar = true,
		public string $mode = 'visual',
		?Closure $customIconRenderer = null,
	) {
		$this->uuid = 've-editor-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		/** @var BlockRegistry $registry */
		$registry = app( 'visual-editor.blocks' );

		$this->iconRenderer = $customIconRenderer ?? $this->defaultIconRenderer();

		$this->buildInserterBlocks( $registry );
		$this->buildRenderedBlocks( $registry );
		$this->buildDefaultBlockTemplates( $registry );
		$this->buildBlockMetadata( $registry );
		$this->buildInspectorData( $registry );
		$this->buildToolbarData( $registry );
		$this->buildAlignmentData( $registry );
		$this->buildCustomPanels( $registry );
		$this->buildEditorShortcuts();
		$this->buildPatternsWithPreviews();
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
		return view( 'visual-editor::components.editor' );
	}

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
				'name'        => $block->getType(),
				'label'       => $block->getName(),
				'icon'        => $block->getIcon(),
				'category'    => $block->getCategory(),
				'description' => $block->getDescription(),
				'keywords'    => $block->getKeywords(),
			] )
			->values()
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
		$dynamicBlockTypes    = array_keys( $registry->getDynamicBlocks() );

		foreach ( $this->initialBlocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['id'], $block['type'] ) || ! is_string( $block['id'] ) || ! is_string( $block['type'] ) ) {
				continue;
			}

			if ( in_array( $block['type'], $dynamicBlockTypes, true ) ) {
				continue;
			}

			$blockType = $registry->get( $block['type'] );
			if ( $blockType ) {
				$this->renderedBlocks[ $block['id'] ] = $blockType->renderEditor(
					$block['attributes'] ?? [],
					[],
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
		$dynamicBlockTypes           = array_keys( $registry->getDynamicBlocks() );

		foreach ( $registry->all() as $type => $block ) {
			if ( in_array( $type, $dynamicBlockTypes, true ) ) {
				continue;
			}

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
		$iconRenderer              = $this->iconRenderer;

		foreach ( $registry->all() as $type => $block ) {
			$this->toolbarBlockIcons[ $type ] = $iconRenderer( $block->getIcon() );

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
	 * Build custom toolbar and inspector HTML from blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry instance.
	 *
	 * @return void
	 */
	protected function buildCustomPanels( BlockRegistry $registry ): void
	{
		$this->customToolbarHtml   = [];
		$this->customInspectorHtml = [];

		foreach ( $registry->all() as $type => $block ) {
			if ( $block->hasCustomToolbar() ) {
				$this->customToolbarHtml[ $type ] = $block->renderToolbar();
			}

			if ( $block->hasCustomInspector() ) {
				$this->customInspectorHtml[ $type ] = $block->renderInspector();
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
