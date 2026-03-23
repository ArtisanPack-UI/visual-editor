<?php

/**
 * Template Editor Component.
 *
 * Provides a full block editing experience for templates. Reuses the
 * same sub-components as the Editor (canvas, sidebar, toolbar, inserter,
 * inspector, layers) but is tailored for template editing with a
 * template switcher and structure panel.
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
 * Template Editor component for full block-based template editing.
 *
 * Mirrors the Editor component layout but with template-specific
 * features: a template switcher in the toolbar, a Structure tab
 * in the left sidebar (instead of Layers), and no Document tab
 * in the right sidebar.
 *
 * Usage:
 *   <x-ve-template-editor
 *       :initial-blocks="$blocks"
 *       :templates="$templates"
 *       current-template-slug="default-page"
 *   />
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class TemplateEditor extends Component
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
	 * Global base style values for override indicators.
	 *
	 * When provided, the template style editors operate in override mode
	 * showing inherited vs overridden values relative to these base values.
	 *
	 * @since 1.0.0
	 *
	 * @var array{palette: array, typography: array, spacing: array}
	 */
	public array $globalBaseStyles;

	/**
	 * Current template part assignments keyed by area.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, int|null>
	 */
	public array $templatePartAssignments;

	/**
	 * Template metadata for the settings panel.
	 *
	 * @since 1.0.0
	 *
	 * @var array{name: string, slug: string, type: string, contentType: string|null}
	 */
	public array $templateSettings;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null   $id                       Optional custom ID.
	 * @param array<mixed>  $initialBlocks            Block data to populate the editor.
	 * @param array<mixed>  $patterns                 Pattern definitions for the pattern browser.
	 * @param array<mixed>  $blockTransforms          Transform mappings between block types.
	 * @param array<mixed>  $blockVariations          Variation definitions for blocks.
	 * @param bool          $autosave                 Enable autosave.
	 * @param int           $autosaveInterval         Autosave interval in seconds.
	 * @param bool          $showSidebar              Show sidebar by default.
	 * @param string        $mode                     Editor mode (visual/code).
	 * @param Closure|null  $customIconRenderer       Optional custom icon renderer.
	 * @param string        $featuredImageUrl         Optional featured image URL for the Cover block placeholder.
	 * @param array<string, mixed> $initialMeta       Initial meta key-value pairs.
	 * @param array<int, array<string, string>> $templates  Available templates for the switcher.
	 * @param string        $currentTemplateSlug      The currently active template slug.
	 * @param array<string, array> $globalBaseStyles   Global base styles for override mode.
	 * @param array<string, int|null> $templatePartAssignments  Current part assignments.
	 * @param array<string, mixed> $templateSettings  Template metadata settings.
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
		public array $templates = [],
		public string $currentTemplateSlug = '',
		array $globalBaseStyles = [],
		array $templatePartAssignments = [],
		array $templateSettings = [],
	) {
		$this->uuid = 've-tpl-editor-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

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

		// Resolve global base styles for the override indicators.
		// Merge provided values with defaults so partial arrays are safe.
		$defaults = [
			'palette'    => app( 'visual-editor.color-palette' )->toStoreFormat(),
			'typography' => app( 'visual-editor.typography-presets' )->toStoreFormat(),
			'spacing'    => app( 'visual-editor.spacing-scale' )->toStoreFormat(),
		];

		$this->globalBaseStyles = array_replace_recursive( $defaults, $globalBaseStyles );

		$this->templatePartAssignments = $templatePartAssignments;
		$this->templateSettings        = $templateSettings;
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
		return view( 'visual-editor::components.template-editor' );
	}
}
