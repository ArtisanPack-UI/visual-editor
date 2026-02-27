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
						'type'    => 'alignment',
						'field'   => 'alignment',
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
	 * @param array<string, mixed> $content The block content values.
	 * @param array<string, mixed> $styles  The block style values.
	 * @param array<string, mixed> $context Additional rendering context.
	 *
	 * @return string
	 */
	public function render( array $content, array $styles, array $context = [] ): string
	{
		$viewName = $this->resolveView( 'save' );

		return view( $viewName, [
			'content' => $content,
			'styles'  => $styles,
			'context' => $context,
			'block'   => $this,
		] )->render();
	}

	/**
	 * Render the block for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $content The block content values.
	 * @param array<string, mixed> $styles  The block style values.
	 * @param array<string, mixed> $context Additional rendering context.
	 *
	 * @return string
	 */
	public function renderEditor( array $content, array $styles, array $context = [] ): string
	{
		$viewName = $this->resolveView( 'edit' );

		return view( $viewName, [
			'content' => $content,
			'styles'  => $styles,
			'context' => $context,
			'block'   => $this,
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
