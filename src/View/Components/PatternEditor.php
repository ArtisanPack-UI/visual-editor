<?php

/**
 * Pattern Editor Component.
 *
 * Provides a full block editing experience for patterns. Reuses the
 * same sub-components as the Editor (canvas, sidebar, toolbar, inserter,
 * inspector, layers) but is tailored for pattern editing with a
 * Pattern-specific sidebar tab.
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
use ArtisanPackUI\VisualEditor\View\Components\Concerns\BuildsBlockRegistryData;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Pattern Editor component for full block-based pattern editing.
 *
 * Mirrors the Template Part Editor component layout but with pattern-specific
 * features: a Pattern tab in the right sidebar with name, slug, category,
 * description, keywords, status, and synced/standard toggle.
 *
 * Usage:
 *   <x-ve-pattern-editor
 *       :initial-blocks="$blocks"
 *       :pattern-settings="$patternSettings"
 *   />
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class PatternEditor extends Component
{
	use BuildsBlockRegistryData;

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
	 * All block display names keyed by block type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $blockNames;

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
	 * Default inner blocks keyed by block type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	public array $defaultInnerBlocksMap;

	/**
	 * Icon renderer closure for block icons.
	 *
	 * @since 1.0.0
	 *
	 * @var Closure
	 */
	public Closure $iconRenderer;

	/**
	 * Pattern metadata for the settings panel.
	 *
	 * @since 1.0.0
	 *
	 * @var array{name: string, slug: string, status: string, isSynced: bool, category?: string|null, description?: string|null, keywords?: string|null, createdBy?: string, updatedAt?: string}
	 */
	public array $patternSettings;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null   $id                   Optional custom ID.
	 * @param array<mixed>  $initialBlocks        Block data to populate the editor.
	 * @param array<mixed>  $patterns             Pattern definitions for the pattern browser.
	 * @param array<mixed>  $blockTransforms      Transform mappings between block types.
	 * @param array<mixed>  $blockVariations      Variation definitions for blocks.
	 * @param bool          $autosave             Enable autosave.
	 * @param int           $autosaveInterval     Autosave interval in seconds.
	 * @param bool          $showSidebar          Show sidebar by default.
	 * @param string        $mode                 Editor mode (visual/code).
	 * @param Closure|null  $customIconRenderer   Optional custom icon renderer.
	 * @param string        $featuredImageUrl     Optional featured image URL for the Cover block placeholder.
	 * @param array<string, mixed> $initialMeta   Initial meta key-value pairs.
	 * @param array<string, mixed> $patternSettings Pattern metadata settings.
	 */
	public function __construct(
		public ?string $id = null,
		public array $initialBlocks = [],
		public array $patterns = [],
		public array $blockTransforms = [],
		public array $blockVariations = [],
		public bool $autosave = false,
		public int $autosaveInterval = 60,
		public bool $showSidebar = true,
		public string $mode = 'visual',
		?Closure $customIconRenderer = null,
		public string $featuredImageUrl = '',
		public array $initialMeta = [],
		array $patternSettings = [],
	) {
		$this->uuid = 've-pattern-editor-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		// Resolve featured image URL via hook if not explicitly provided.
		if ( '' === $this->featuredImageUrl ) {
			$this->featuredImageUrl = (string) veApplyFilters( 've.editor.featured_image_url', '' );
		}

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
		$this->buildBlockTransforms( $registry );

		$this->patternSettings = $patternSettings;
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
		return view( 'visual-editor::components.pattern-editor' );
	}
}
