<?php

declare( strict_types=1 );

/**
 * Block Registry
 *
 * Manages the registration and retrieval of block types for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Registries
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\VisualEditor\Registries;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Block Registry class.
 *
 * Provides a centralized registry for managing block types in the visual editor.
 * Blocks can be registered, unregistered, and queried by category or type.
 *
 * @since 1.0.0
 */
class BlockRegistry
{
	/**
	 * The registered blocks.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array>
	 */
	protected array $blocks = [];

	/**
	 * The block categories.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array>
	 */
	protected array $categories = [];

	/**
	 * Create a new BlockRegistry instance.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->categories = [
			'text'        => [
				'name' => __( 'Text' ),
				'icon' => 'fas.file-lines',
			],
			'media'       => [
				'name' => __( 'Media' ),
				'icon' => 'fas.image',
			],
			'interactive' => [
				'name' => __( 'Interactive' ),
				'icon' => 'fas.hand-pointer',
			],
			'layout'      => [
				'name' => __( 'Layout' ),
				'icon' => 'fas.table-cells',
			],
			'embed'       => [
				'name' => __( 'Embed' ),
				'icon' => 'fas.code',
			],
			'dynamic'     => [
				'name' => __( 'Dynamic' ),
				'icon' => 'fas.arrows-rotate',
			],
		];
	}

	/**
	 * Register a block type.
	 *
	 * Validates the block type and configuration before registering.
	 * The type must be a non-empty string containing only alphanumeric
	 * characters, hyphens, and underscores.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   The block type identifier.
	 * @param array  $config The block configuration.
	 *
	 * @throws InvalidArgumentException If the type or configuration is invalid.
	 *
	 * @return self
	 */
	public function register( string $type, array $config ): self
	{
		$this->validateRegistration( $type, $config );

		$this->blocks[ $type ] = array_merge( [
			'name'             => $type,
			'description'      => '',
			'icon'             => 'fas.cube',
			'category'         => 'text',
			'keywords'         => [],
			'content_schema'   => [],
			'settings_schema'  => [],
			'component'        => null,
			'editor_component' => null,
			'supports'         => [ 'sizing' ],
			'toolbar'          => [],
			'example'          => [],
		], $config );

		return $this;
	}

	/**
	 * Unregister a block type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return self
	 */
	public function unregister( string $type ): self
	{
		unset( $this->blocks[ $type ] );

		return $this;
	}

	/**
	 * Check if a block type is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return bool
	 */
	public function has( string $type ): bool
	{
		return isset( $this->blocks[ $type ] );
	}

	/**
	 * Get a block type configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return array|null
	 */
	public function get( string $type ): ?array
	{
		return $this->blocks[ $type ] ?? null;
	}

	/**
	 * Get all registered blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	public function all(): Collection
	{
		return collect( $this->blocks );
	}

	/**
	 * Get blocks filtered by allowed/disallowed configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	public function getAvailable(): Collection
	{
		$allowed    = config( 'artisanpack.visual-editor.blocks.allowed_blocks', [] );
		$disallowed = config( 'artisanpack.visual-editor.blocks.disallowed_blocks', [] );

		return $this->all()->filter( function ( $block, $type ) use ( $allowed, $disallowed ) {
			// If allowed list is not empty, block must be in it
			if ( !empty( $allowed ) && !in_array( $type, $allowed, true ) ) {
				return false;
			}

			// Block must not be in disallowed list
			return !in_array( $type, $disallowed, true );
		} );
	}

	/**
	 * Get blocks by category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category name.
	 *
	 * @return Collection
	 */
	public function getByCategory( string $category ): Collection
	{
		return $this->getAvailable()->filter( fn ( $block ) => ( $block['category'] ?? '' ) === $category );
	}

	/**
	 * Get all categories with their blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	public function getGroupedByCategory(): Collection
	{
		$available = $this->getAvailable();

		return collect( $this->categories )->map( function ( $category, $key ) use ( $available ) {
			return array_merge( $category, [
				'blocks' => $available->filter( fn ( $block ) => ( $block['category'] ?? '' ) === $key ),
			] );
		} )->filter( fn ( $category ) => $category['blocks']->isNotEmpty() );
	}

	/**
	 * Register a block category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    The category key.
	 * @param array  $config The category configuration.
	 *
	 * @return self
	 */
	public function registerCategory( string $key, array $config ): self
	{
		$this->categories[ $key ] = array_merge( [
			'name' => $key,
			'icon' => 'fas.folder',
		], $config );

		return $this;
	}

	/**
	 * Get all categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function getCategories(): array
	{
		return $this->categories;
	}

	/**
	 * Register the default blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function registerDefaults(): void
	{
		// Text blocks
		$this->register( 'heading', [
			'name'           => __( 'Heading' ),
			'icon'           => 'fas.heading',
			'category'       => 'text',
			'content_schema' => [
				'text'  => [ 'type' => 'richtext', 'label' => __( 'Heading Text' ), 'required' => true ],
				'level' => [ 'type' => 'select', 'label' => __( 'Heading Level' ), 'options' => [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], 'default' => 'h2' ],
			],
			'settings_schema' => [
				'anchor' => [ 'type' => 'text', 'label' => __( 'HTML Anchor' ) ],
			],
			'supports' => [ 'sizing', 'typography', 'colors' ],
			'toolbar'  => [ 'align', 'richtext', 'heading_level' ],
		] );

		$this->register( 'text', [
			'name'           => __( 'Text' ),
			'icon'           => 'fas.file-lines',
			'category'       => 'text',
			'content_schema' => [
				'text' => [ 'type' => 'richtext', 'label' => __( 'Content' ) ],
			],
			'settings_schema' => [
				'drop_cap' => [ 'type' => 'toggle', 'label' => __( 'Drop Cap' ), 'default' => false ],
			],
			'supports' => [ 'sizing', 'typography', 'colors' ],
			'toolbar'  => [ 'align', 'richtext' ],
		] );

		$this->register( 'list', [
			'name'           => __( 'List' ),
			'icon'           => 'fas.list',
			'category'       => 'text',
			'content_schema' => [
				'text'  => [ 'type' => 'richtext', 'label' => __( 'List Content' ) ],
				'style' => [ 'type' => 'select', 'label' => __( 'List Style' ), 'options' => [ 'bullet', 'number' ], 'default' => 'bullet' ],
			],
			'supports' => [ 'sizing', 'typography', 'colors' ],
			'toolbar'  => [ 'align', 'richtext', 'list_style' ],
		] );

		$this->register( 'quote', [
			'name'           => __( 'Quote' ),
			'icon'           => 'fas.quote-left',
			'category'       => 'text',
			'content_schema' => [
				'text'     => [ 'type' => 'textarea', 'label' => __( 'Quote Text' ) ],
				'citation' => [ 'type' => 'text', 'label' => __( 'Citation' ) ],
			],
			'supports' => [ 'sizing', 'typography', 'colors', 'borders' ],
		] );

		// Media blocks
		$this->register( 'image', [
			'name'           => __( 'Image' ),
			'icon'           => 'fas.image',
			'category'       => 'media',
			'content_schema' => [
				'media_id' => [ 'type' => 'media', 'label' => __( 'Image' ) ],
				'alt'      => [ 'type' => 'text', 'label' => __( 'Alt Text' ) ],
				'caption'  => [ 'type' => 'text', 'label' => __( 'Caption' ) ],
			],
			'settings_schema' => [
				'shadow' => [ 'type' => 'toggle', 'label' => __( 'Drop Shadow' ) ],
			],
			'supports' => [ 'sizing', 'borders' ],
			'toolbar'  => [ 'align' ],
		] );

		$this->register( 'video', [
			'name'           => __( 'Video' ),
			'icon'           => 'fas.video',
			'category'       => 'media',
			'content_schema' => [
				'url'      => [ 'type' => 'url', 'label' => __( 'Video URL' ) ],
				'autoplay' => [ 'type' => 'toggle', 'label' => __( 'Autoplay' ), 'default' => false ],
				'loop'     => [ 'type' => 'toggle', 'label' => __( 'Loop' ), 'default' => false ],
			],
			'supports' => [ 'sizing', 'borders' ],
		] );

		// Interactive blocks
		$this->register( 'button', [
			'name'           => __( 'Button' ),
			'icon'           => 'fas.hand-pointer',
			'category'       => 'interactive',
			'content_schema' => [
				'text'   => [ 'type' => 'text', 'label' => __( 'Button Text' ), 'required' => true ],
				'url'    => [ 'type' => 'url', 'label' => __( 'Link URL' ), 'required' => true ],
				'target' => [ 'type' => 'select', 'options' => [ '_self', '_blank' ], 'default' => '_self' ],
			],
			'settings_schema' => [
				'style' => [ 'type' => 'select', 'options' => [ 'primary', 'secondary', 'outline', 'ghost' ] ],
			],
			'supports' => [ 'sizing', 'typography', 'colors', 'borders' ],
			'toolbar'  => [ 'align' ],
		] );

		$this->register( 'button_group', [
			'name'           => __( 'Button Group' ),
			'icon'           => 'fas.object-group',
			'category'       => 'interactive',
			'content_schema' => [
				'buttons' => [ 'type' => 'repeater', 'label' => __( 'Buttons' ) ],
			],
			'supports' => [ 'sizing' ],
		] );

		$this->register( 'form', [
			'name'           => __( 'Form' ),
			'icon'           => 'fas.clipboard-list',
			'category'       => 'interactive',
			'content_schema' => [
				'form_id' => [ 'type' => 'form_select', 'label' => __( 'Select Form' ) ],
			],
			'supports' => [ 'sizing', 'borders' ],
		] );

		// Layout blocks
		$this->register( 'divider', [
			'name'     => __( 'Divider' ),
			'icon'     => 'fas.minus',
			'category' => 'layout',
			'supports' => [ 'sizing', 'colors', 'borders' ],
		] );

		$this->register( 'spacer', [
			'name'     => __( 'Spacer' ),
			'icon'     => 'fas.arrows-up-down',
			'category' => 'layout',
			'supports' => [ 'sizing' ],
		] );

		// Dynamic blocks
		$this->register( 'global_content', [
			'name'           => __( 'Business Info' ),
			'icon'           => 'fas.building',
			'category'       => 'dynamic',
			'content_schema' => [
				'key'    => [ 'type' => 'global_content_select', 'label' => __( 'Content to Display' ) ],
				'format' => [ 'type' => 'select', 'options' => [ 'text', 'link', 'formatted' ] ],
			],
			'supports' => [ 'sizing', 'typography', 'colors' ],
		] );
	}

	/**
	 * Validate block registration parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   The block type identifier.
	 * @param array  $config The block configuration.
	 *
	 * @throws InvalidArgumentException If the type or configuration is invalid.
	 *
	 * @return void
	 */
	protected function validateRegistration( string $type, array $config ): void
	{
		if ( '' === trim( $type ) ) {
			throw new InvalidArgumentException( __( 'Block type cannot be empty.' ) );
		}

		if ( !preg_match( '/^[a-zA-Z0-9_-]+$/', $type ) ) {
			throw new InvalidArgumentException(
				__( 'Block type ":type" contains invalid characters. Only alphanumeric characters, hyphens, and underscores are allowed.', [
					'type' => $type,
				] ),
			);
		}

		if ( isset( $config['category'] ) && !isset( $this->categories[ $config['category'] ] ) ) {
			throw new InvalidArgumentException(
				__( 'Block category ":category" is not registered. Register it first with registerCategory().', [
					'category' => $config['category'],
				] ),
			);
		}
	}
}
