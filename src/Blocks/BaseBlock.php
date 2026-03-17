<?php

/**
 * Base Block Abstract Class.
 *
 * Provides default implementations for the BlockInterface methods.
 * All core blocks should extend this class. Supports co-located
 * block.json metadata files for WordPress-style block declarations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

use ArtisanPackUI\VisualEditor\Blocks\Concerns\HasBlockSupports;
use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;
use ReflectionClass;
use RuntimeException;

/**
 * Abstract base class for visual editor blocks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @since      1.0.0
 */
abstract class BaseBlock implements BlockInterface
{
	use HasBlockSupports;

	/**
	 * The block type identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $type = '';

	/**
	 * The human-readable block name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * The block description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * The block icon identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $icon = '';

	/**
	 * The block category.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $category = 'text';

	/**
	 * Searchable keywords for the block.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected array $keywords = [];

	/**
	 * The block schema version.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	protected int $version = 1;

	/**
	 * Loaded block.json metadata, or null if not yet loaded.
	 *
	 * @since 2.0.0
	 *
	 * @var array<string, mixed>|null
	 */
	protected ?array $metadata = null;

	/**
	 * Resolved block directory path.
	 *
	 * @since 2.0.0
	 *
	 * @var string|null
	 */
	protected ?string $blockDir = null;

	/**
	 * Cache of resolved block directories by class name.
	 *
	 * @since 2.0.0
	 *
	 * @var array<string, string>
	 */
	private static array $resolvedDirs = [];

	/**
	 * Cached manifest data from the production cache file.
	 *
	 * Uses false as the "not yet loaded" sentinel to distinguish
	 * from null (loaded but no manifest file found).
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, array{type: string, class: class-string, dir: string, metadata: array<string, mixed>}>|false|null
	 */
	private static array|null|false $cachedManifest = false;

	/**
	 * Create a new block instance.
	 *
	 * @since 2.0.0
	 */
	public function __construct()
	{
		$this->blockDir = $this->resolveBlockDirectory();
		$this->loadMetadata();
	}

	/**
	 * Get the block type identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getType(): string
	{
		return $this->metadata['type'] ?? $this->type;
	}

	/**
	 * Get the human-readable block name.
	 *
	 * Checks for a translation key first, then falls back to
	 * block.json metadata, then to the class property.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getName(): string
	{
		$type           = $this->getType();
		$translationKey = "visual-editor::ve.block_{$type}_name";
		$translated     = __( $translationKey );

		if ( $translated !== $translationKey ) {
			return $translated;
		}

		return $this->metadata['name'] ?? $this->name;
	}

	/**
	 * Get the block description.
	 *
	 * Checks for a translation key first, then falls back to
	 * block.json metadata, then to the class property.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		$type           = $this->getType();
		$translationKey = "visual-editor::ve.block_{$type}_description";
		$translated     = __( $translationKey );

		if ( $translated !== $translationKey ) {
			return $translated;
		}

		return $this->metadata['description'] ?? $this->description;
	}

	/**
	 * Get the block icon identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getIcon(): string
	{
		return $this->metadata['icon'] ?? $this->icon;
	}

	/**
	 * Get the block category.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getCategory(): string
	{
		return $this->metadata['category'] ?? $this->category;
	}

	/**
	 * Get searchable keywords for the block.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function getKeywords(): array
	{
		return $this->metadata['keywords'] ?? $this->keywords;
	}

	/**
	 * Get the block attributes from block.json.
	 *
	 * Returns the raw attribute declarations including type, source,
	 * and default for each attribute.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getAttributes(): array
	{
		return $this->metadata['attributes'] ?? [];
	}

	/**
	 * Get the advanced settings schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getAdvancedSchema(): array
	{
		$schema = [];

		if ( $this->supportsFeature( 'anchor' ) || $this->supportsFeature( 'htmlId' ) ) {
			$schema['anchor'] = [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.html_anchor' ),
				'placeholder' => __( 'visual-editor::ve.html_anchor_placeholder' ),
				'hint'        => __( 'visual-editor::ve.html_anchor_hint' ),
				'default'     => '',
			];
		}

		if ( $this->supportsFeature( 'className' ) ) {
			$schema['className'] = [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.additional_css_classes' ),
				'placeholder' => __( 'visual-editor::ve.additional_css_classes_placeholder' ),
				'default'     => '',
			];
		}

		return $schema;
	}

	/**
	 * Get default content values extracted from the content schema.
	 *
	 * Merges defaults from block.json attributes (source=content)
	 * with defaults from the content schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaultContent(): array
	{
		$schemaDefaults    = $this->extractDefaults( $this->getContentSchema() );
		$attributeDefaults = $this->extractAttributeDefaultsBySource( 'content' );

		return array_merge( $attributeDefaults, $schemaDefaults );
	}

	/**
	 * Get default style values extracted from the style schema.
	 *
	 * Merges defaults from block.json attributes (source=style)
	 * with defaults from the style schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaultStyles(): array
	{
		$schemaDefaults    = $this->extractDefaults( $this->getStyleSchema() );
		$attributeDefaults = $this->extractAttributeDefaultsBySource( 'style' );

		return array_merge( $attributeDefaults, $schemaDefaults );
	}

	/**
	 * Get allowed parent block types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>|null
	 */
	public function getAllowedParents(): ?array
	{
		if ( null !== $this->metadata && array_key_exists( 'parent', $this->metadata ) ) {
			return $this->metadata['parent'];
		}

		return null;
	}

	/**
	 * Get allowed child block types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>|null
	 */
	public function getAllowedChildren(): ?array
	{
		if ( null !== $this->metadata && array_key_exists( 'allowedChildren', $this->metadata ) ) {
			return $this->metadata['allowedChildren'];
		}

		return null;
	}

	/**
	 * Get available block variations.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getVariations(): array
	{
		return [];
	}

	/**
	 * Get available block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [];
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * Returns an array of control groups. Each group has a 'group' key
	 * and a 'controls' array. By default, adds an alignment control
	 * if the block supports alignment.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		$controls = [];

		$alignments = $this->getSupportedAlignments();
		if ( ! empty( $alignments ) ) {
			$controls[] = [
				'group'    => 'block',
				'controls' => [
					[
						'type'    => 'block-alignment',
						'field'   => 'align',
						'options' => $alignments,
					],
				],
			];
		}

		return $controls;
	}

	/**
	 * Render the block for frontend display.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $content     The block content values.
	 * @param array<string, mixed> $styles      The block style values.
	 * @param array<string, mixed> $context     Additional rendering context.
	 * @param array<int, string>   $innerBlocks Pre-rendered inner block HTML strings.
	 *
	 * @return string
	 */
	public function render( array $content, array $styles, array $context = [], array $innerBlocks = [] ): string
	{
		$viewName = $this->resolveView( 'save' );

		return view( $viewName, [
			'content'     => $content,
			'styles'      => $styles,
			'context'     => $context,
			'block'       => $this,
			'innerBlocks' => $innerBlocks,
		] )->render();
	}

	/**
	 * Render the block for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $content     The block content values.
	 * @param array<string, mixed> $styles      The block style values.
	 * @param array<string, mixed> $context     Additional rendering context.
	 * @param array<int, string>   $innerBlocks Pre-rendered inner block HTML strings.
	 *
	 * @return string
	 */
	public function renderEditor( array $content, array $styles, array $context = [], array $innerBlocks = [] ): string
	{
		$viewName = $this->resolveView( 'edit' );

		return view( $viewName, [
			'content'     => $content,
			'styles'      => $styles,
			'context'     => $context,
			'block'       => $this,
			'innerBlocks' => $innerBlocks,
		] )->render();
	}

	/**
	 * Get the block schema version.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->metadata['version'] ?? $this->version;
	}

	/**
	 * Migrate block content from an older version.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $content     The block content to migrate.
	 * @param int                  $fromVersion The version to migrate from.
	 *
	 * @return array<string, mixed>
	 */
	public function migrate( array $content, int $fromVersion ): array
	{
		return $content;
	}

	/**
	 * Whether this block should appear in the block inserter.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isPublic(): bool
	{
		return $this->metadata['public'] ?? true;
	}

	/**
	 * Check whether this block has a custom inspector view.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function hasCustomInspector(): bool
	{
		if ( null === $this->blockDir ) {
			return false;
		}

		return file_exists( $this->blockDir . '/views/inspector.blade.php' );
	}

	/**
	 * Render the custom inspector view for this block.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $data Data to pass to the inspector view.
	 *
	 * @return string
	 */
	public function renderInspector( array $data = [] ): string
	{
		if ( ! $this->hasCustomInspector() ) {
			return '';
		}

		$type     = $this->getType();
		$viewName = "visual-editor-block-{$type}::inspector";

		return view( $viewName, array_merge( $data, [
			'block' => $this,
		] ) )->render();
	}

	/**
	 * Check whether this block has a custom toolbar view.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function hasCustomToolbar(): bool
	{
		if ( null === $this->blockDir ) {
			return false;
		}

		return file_exists( $this->blockDir . '/views/toolbar.blade.php' );
	}

	/**
	 * Render the custom toolbar view for this block.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $data Data to pass to the toolbar view.
	 *
	 * @return string
	 */
	public function renderToolbar( array $data = [] ): string
	{
		if ( ! $this->hasCustomToolbar() ) {
			return '';
		}

		$type     = $this->getType();
		$viewName = "visual-editor-block-{$type}::toolbar";

		return view( $viewName, array_merge( $data, [
			'block' => $this,
		] ) )->render();
	}

	/**
	 * Whether this block supports inner blocks.
	 *
	 * Reads from block.json metadata when available, defaults to false.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function supportsInnerBlocks(): bool
	{
		return $this->metadata['supportsInnerBlocks'] ?? false;
	}

	/**
	 * Get the inner blocks orientation for container blocks.
	 *
	 * @since 2.0.0
	 *
	 * @return string 'vertical' or 'horizontal'
	 */
	public function getInnerBlocksOrientation(): string
	{
		return $this->metadata['innerBlocksOrientation'] ?? 'vertical';
	}

	/**
	 * Get the default inner blocks for new instances.
	 *
	 * Override in subclasses to pre-populate container blocks
	 * with child blocks when they are first inserted.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getDefaultInnerBlocks(): array
	{
		return [];
	}

	/**
	 * Whether this block is a dynamic (server-rendered) block.
	 *
	 * Dynamic blocks are rendered server-side via Livewire components
	 * instead of storing static content. Returns false by default;
	 * DynamicBlock subclass overrides this to return true.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function isDynamic(): bool
	{
		return $this->metadata['dynamic'] ?? false;
	}

	/**
	 * Whether this block has a custom JavaScript renderer.
	 *
	 * Blocks with inner blocks or custom editor rendering should
	 * return true so the editor delegates rendering to JavaScript.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function hasJsRenderer(): bool
	{
		return $this->metadata['hasJsRenderer'] ?? false;
	}

	/**
	 * Get block metadata as an array for serialization.
	 *
	 * Used by BlockRegistry::toArray() to pass block metadata
	 * to the JavaScript editor.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'type'                   => $this->getType(),
			'name'                   => $this->getName(),
			'description'            => $this->getDescription(),
			'icon'                   => $this->getIcon(),
			'category'               => $this->getCategory(),
			'keywords'               => $this->getKeywords(),
			'public'                 => $this->isPublic(),
			'dynamic'                => $this->isDynamic(),
			'supportsInnerBlocks'    => $this->supportsInnerBlocks(),
			'innerBlocksOrientation' => $this->getInnerBlocksOrientation(),
			'allowedChildren'        => $this->getAllowedChildren(),
			'allowedParents'         => $this->getAllowedParents(),
			'hasJsRenderer'          => $this->hasJsRenderer(),
			'hasCustomInspector'     => $this->hasCustomInspector(),
			'hasCustomToolbar'       => $this->hasCustomToolbar(),
			'alignments'             => $this->getSupportedAlignments(),
			'textAlignment'          => $this->supportsFeature( 'textAlignment' ),
			'textFormatting'         => $this->supportsFeature( 'textFormatting' ),
			'defaultInnerBlocks'     => $this->getDefaultInnerBlocks(),
		];
	}

	/**
	 * Get the block directory path.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function getBlockDir(): ?string
	{
		return $this->blockDir;
	}

	/**
	 * Get the raw metadata array.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function getMetadata(): ?array
	{
		return $this->metadata;
	}

	/**
	 * Reset the cached manifest (used in testing).
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function resetCachedManifest(): void
	{
		self::$cachedManifest = false;
	}

	/**
	 * Reset the resolved directory cache (used in testing).
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public static function resetResolvedDirs(): void
	{
		self::$resolvedDirs = [];
	}

	/**
	 * Get the style field schema.
	 *
	 * Auto-generates schema entries from block.json supports so that
	 * subclasses only need to declare custom (non-supports) fields.
	 * Override in subclasses and merge with parent to add custom fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return $this->generateSupportsStyleSchema();
	}

	/**
	 * Get the content field schema.
	 *
	 * Override in subclasses to declare content fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [];
	}

	/**
	 * Resolve the directory containing this block class.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function resolveBlockDirectory(): string
	{
		$class = static::class;

		if ( isset( self::$resolvedDirs[ $class ] ) ) {
			return self::$resolvedDirs[ $class ];
		}

		$reflection                   = new ReflectionClass( $class );
		$dir                          = dirname( (string) $reflection->getFileName() );
		self::$resolvedDirs[ $class ] = $dir;

		return $dir;
	}

	/**
	 * Load block.json metadata from cache or disk.
	 *
	 * Checks the production manifest cache first (matched by FQCN),
	 * then falls back to reading block.json from disk.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	protected function loadMetadata(): void
	{
		$manifest = self::getCachedManifest();

		if ( null !== $manifest ) {
			$class = static::class;

			foreach ( $manifest as $entry ) {
				if ( $entry['class'] === $class ) {
					$this->metadata = $entry['metadata'];

					return;
				}
			}
		}

		if ( null === $this->blockDir ) {
			return;
		}

		$jsonPath = $this->blockDir . '/block.json';

		if ( ! file_exists( $jsonPath ) ) {
			return;
		}

		$contents = file_get_contents( $jsonPath );

		if ( false === $contents ) {
			return;
		}

		$decoded = json_decode( $contents, true );

		if ( ! is_array( $decoded ) ) {
			if ( 'production' !== app()->environment() ) {
				throw new RuntimeException(
					"Malformed block.json at {$jsonPath}",
				);
			}

			return;
		}

		$this->metadata = $decoded;
	}

	/**
	 * Resolve a view name with namespace chain fallback.
	 *
	 * Resolution order:
	 * 1. Co-located view: visual-editor-block-{type}::{name}
	 * 2. Reorganized: visual-editor::blocks.{type}.{name}
	 * 3. Legacy flat: visual-editor::blocks.{type} (for save)
	 *    or visual-editor::blocks.{type}-editor (for edit)
	 *
	 * @since 2.0.0
	 *
	 * @param string $name The view name (e.g. 'save', 'edit', 'inspector', 'toolbar').
	 *
	 * @return string The resolved view name.
	 */
	protected function resolveView( string $name ): string
	{
		$type = $this->getType();

		$colocated = "visual-editor-block-{$type}::{$name}";
		if ( view()->exists( $colocated ) ) {
			return $colocated;
		}

		$reorganized = "visual-editor::blocks.{$type}.{$name}";
		if ( view()->exists( $reorganized ) ) {
			return $reorganized;
		}

		$legacySuffix = 'edit' === $name ? '-editor' : '';
		$legacy       = "visual-editor::blocks.{$type}{$legacySuffix}";
		if ( view()->exists( $legacy ) ) {
			return $legacy;
		}

		return $colocated;
	}

	/**
	 * Generate style schema entries from block.json supports.
	 *
	 * Maps active supports to schema field definitions, using
	 * block.json attribute defaults where available. This provides
	 * a single source of truth for supports-driven style fields.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function generateSupportsStyleSchema(): array
	{
		$schema         = [];
		$activeSupports = $this->getActiveStyleSupports();
		$attributes     = $this->getAttributes();

		if ( in_array( 'color.text', $activeSupports, true ) ) {
			$schema['textColor'] = [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => $attributes['textColor']['default'] ?? null,
			];
		}

		if ( in_array( 'color.background', $activeSupports, true ) ) {
			$schema['backgroundColor'] = [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.background_color' ),
				'default' => $attributes['backgroundColor']['default'] ?? null,
			];
		}

		if ( in_array( 'typography.fontSize', $activeSupports, true ) ) {
			$schema['fontSize'] = [
				'type'    => 'font_size',
				'label'   => __( 'visual-editor::ve.font_size' ),
				'default' => $attributes['fontSize']['default'] ?? null,
			];
		}

		if ( in_array( 'typography.dropCap', $activeSupports, true ) ) {
			$schema['dropCap'] = [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.drop_cap' ),
				'default' => $attributes['dropCap']['default'] ?? false,
			];
		}

		if ( in_array( 'spacing.padding', $activeSupports, true ) ) {
			$schema['padding'] = [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.padding' ),
				'sides'   => [ 'top', 'right', 'bottom', 'left' ],
				'default' => $attributes['padding']['default'] ?? null,
			];
		}

		if ( in_array( 'spacing.margin', $activeSupports, true ) ) {
			$schema['margin'] = [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.margin' ),
				'sides'   => [ 'top', 'bottom' ],
				'default' => $attributes['margin']['default'] ?? null,
			];
		}

		if ( in_array( 'spacing.blockSpacing', $activeSupports, true ) ) {
			$schema['blockSpacing'] = [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.block_spacing' ),
				'default' => $attributes['blockSpacing']['default'] ?? null,
			];
		}

		if ( in_array( 'border', $activeSupports, true ) ) {
			$schema['border'] = [
				'type'    => 'border',
				'label'   => __( 'visual-editor::ve.border' ),
				'default' => $attributes['border']['default'] ?? [
					'width'      => '0',
					'widthUnit'  => 'px',
					'style'      => 'none',
					'color'      => '#000000',
					'radius'     => '0',
					'radiusUnit' => 'px',
					'perSide'    => false,
					'perCorner'  => false,
				],
			];
		}

		if ( in_array( 'shadow', $activeSupports, true ) ) {
			$schema['shadow'] = [
				'type'    => 'shadow',
				'label'   => __( 'visual-editor::ve.shadow' ),
				'default' => $attributes['shadow']['default'] ?? null,
			];
		}

		if ( in_array( 'dimensions.aspectRatio', $activeSupports, true ) ) {
			$schema['aspectRatio'] = [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.aspect_ratio' ),
				'default' => $attributes['aspectRatio']['default'] ?? null,
			];
		}

		if ( in_array( 'dimensions.minHeight', $activeSupports, true ) ) {
			$schema['minHeight'] = [
				'type'    => 'unit',
				'label'   => __( 'visual-editor::ve.min_height' ),
				'default' => $attributes['minHeight']['default'] ?? '',
			];
		}

		if ( in_array( 'background.backgroundImage', $activeSupports, true ) ) {
			$schema['backgroundImage'] = [
				'type'    => 'media_picker',
				'label'   => __( 'visual-editor::ve.background_image' ),
				'default' => $attributes['backgroundImage']['default'] ?? null,
			];
		}

		if ( in_array( 'background.backgroundSize', $activeSupports, true ) ) {
			$schema['backgroundSize'] = [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.background_size' ),
				'default' => $attributes['backgroundSize']['default'] ?? null,
			];
		}

		if ( in_array( 'background.backgroundPosition', $activeSupports, true ) ) {
			$schema['backgroundPosition'] = [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.background_position' ),
				'default' => $attributes['backgroundPosition']['default'] ?? null,
			];
		}

		if ( in_array( 'background.backgroundGradient', $activeSupports, true ) ) {
			$schema['backgroundGradient'] = [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.background_gradient' ),
				'default' => $attributes['backgroundGradient']['default'] ?? null,
			];
		}

		return $schema;
	}

	/**
	 * Validate that a subclass style schema does not overlap with
	 * auto-generated supports fields.
	 *
	 * Only runs in non-production environments.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, array<string, mixed>> $customSchema The custom schema to validate.
	 *
	 * @return void
	 */
	protected function validateStyleSchemaOverlap( array $customSchema ): void
	{
		if ( 'production' === app()->environment() ) {
			return;
		}

		$supportsSchema = $this->generateSupportsStyleSchema();
		$overlap        = array_intersect_key( $customSchema, $supportsSchema );

		if ( ! empty( $overlap ) ) {
			$type   = $this->getType();
			$fields = implode( ', ', array_keys( $overlap ) );

			trigger_error(
				"Block '{$type}' getStyleSchema() declares fields already covered by supports: {$fields}. "
				. 'Remove these from getStyleSchema() — they are auto-generated from block.json supports.',
				E_USER_NOTICE,
			);
		}
	}

	/**
	 * Extract default values from a schema array.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $schema The field schema.
	 *
	 * @return array<string, mixed>
	 */
	protected function extractDefaults( array $schema ): array
	{
		$defaults = [];

		foreach ( $schema as $field => $config ) {
			if ( array_key_exists( 'default', $config ) ) {
				$defaults[ $field ] = $config['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Extract default values from block.json attributes filtered by source.
	 *
	 * @since 2.0.0
	 *
	 * @param string $source The source to filter by ('content' or 'style').
	 *
	 * @return array<string, mixed>
	 */
	protected function extractAttributeDefaultsBySource( string $source ): array
	{
		$attributes = $this->getAttributes();
		$defaults   = [];

		foreach ( $attributes as $name => $config ) {
			if ( ( $config['source'] ?? '' ) === $source && array_key_exists( 'default', $config ) ) {
				$defaults[ $name ] = $config['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Get the cached manifest, loading it on first access.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array{type: string, class: class-string, dir: string, metadata: array<string, mixed>}>|null
	 */
	private static function getCachedManifest(): ?array
	{
		if ( false !== self::$cachedManifest ) {
			return self::$cachedManifest;
		}

		$path = app()->bootstrapPath( 'cache/visual-editor-blocks.php' );

		if ( file_exists( $path ) ) {
			$data                 = require $path;
			self::$cachedManifest = is_array( $data ) ? $data : null;
		} else {
			self::$cachedManifest = null;
		}

		return self::$cachedManifest;
	}
}
