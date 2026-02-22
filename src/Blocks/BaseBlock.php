<?php

/**
 * Base Block Abstract Class.
 *
 * Provides default implementations for the BlockInterface methods.
 * All core blocks should extend this class.
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
	 * Get the block type identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * Get the human-readable block name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Get the block description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
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
		return $this->icon;
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
		return $this->category;
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
		return $this->keywords;
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

		if ( $this->supportsFeature( 'anchor' ) ) {
			$schema['anchor'] = [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.html_anchor' ),
				'placeholder' => __( 'visual-editor::ve.html_anchor_placeholder' ),
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
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaultContent(): array
	{
		return $this->extractDefaults( $this->getContentSchema() );
	}

	/**
	 * Get default style values extracted from the style schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaultStyles(): array
	{
		return $this->extractDefaults( $this->getStyleSchema() );
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
		return null;
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
		return view( 'visual-editor::blocks.' . $this->type, [
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
		$editorView = 'visual-editor::blocks.' . $this->type . '-editor';

		if ( view()->exists( $editorView ) ) {
			return view( $editorView, [
				'content' => $content,
				'styles'  => $styles,
				'context' => $context,
				'block'   => $this,
			] )->render();
		}

		return $this->render( $content, $styles, $context );
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
		return $this->version;
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
		return true;
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
}
