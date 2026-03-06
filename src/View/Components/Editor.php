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
			if ( '_default' === $iconName ) {
				return '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>';
			}

			if ( 'heading' === $iconName ) {
				return '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-size="16" font-weight="700" font-family="system-ui, sans-serif">H</text></svg>';
			}

			$iconMap = $this->getIconMap();

			if ( isset( $iconMap[ $iconName ] ) ) {
				return $iconMap[ $iconName ];
			}

			return '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>';
		};
	}

	/**
	 * Get the inline SVG icon map for common block icons.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function getIconMap(): array
	{
		return [
			'document-text'     => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
			'photo'             => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
			'list-bullet'       => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
			'code-bracket'      => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" /></svg>',
			'chat-bubble-left'  => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>',
			'cursor-arrow-rays' => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59" /></svg>',
			'link'              => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>',
			'minus'             => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>',
			'arrows-up-down'    => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" /></svg>',
			'view-columns'      => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z" /></svg>',
			'rectangle-group'   => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" /></svg>',
			'squares-2x2'       => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>',
			'film'              => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0 1 18 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 0 1 6 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M19.125 12h1.5m0 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h1.5m14.25 0h1.5" /></svg>',
			'document'          => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
			'speaker-wave'      => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>',
			'squares-plus'      => '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>',
		];
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
		$this->patternsWithPreviews = array_map( function ( array $pattern ): array {
			if ( ! isset( $pattern['preview'] ) ) {
				$pattern['preview'] = 'https://placehold.co/400x200/e2e8f0/64748b?text=' . urlencode( $pattern['name'] ?? '' );
			}

			return $pattern;
		}, $this->patterns );
	}
}
