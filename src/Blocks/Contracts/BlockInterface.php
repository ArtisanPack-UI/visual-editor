<?php

/**
 * Block Interface Contract.
 *
 * Defines the contract that all block types must implement, including
 * identification, schema definition, rendering, and versioning.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Contracts
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Contracts;

/**
 * Interface for visual editor block types.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Contracts
 *
 * @since      1.0.0
 */
interface BlockInterface
{
	/**
	 * Get the block type identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getType(): string;

	/**
	 * Get the human-readable block name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get the block description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Get the block icon identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getIcon(): string;

	/**
	 * Get the block category.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getCategory(): string;

	/**
	 * Get searchable keywords for the block.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function getKeywords(): array;

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
	public function getAttributes(): array;

	/**
	 * Get the content field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array;

	/**
	 * Get the style field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array;

	/**
	 * Get the advanced settings schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getAdvancedSchema(): array;

	/**
	 * Get default content values.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaultContent(): array;

	/**
	 * Get default style values.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaultStyles(): array;

	/**
	 * Get allowed parent block types, or null for no restriction.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>|null
	 */
	public function getAllowedParents(): ?array;

	/**
	 * Get allowed child block types, or null for no restriction.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>|null
	 */
	public function getAllowedChildren(): ?array;

	/**
	 * Get available block variations.
	 *
	 * Each variation is an associative array with keys:
	 * - name: string — unique variation identifier
	 * - label: string — human-readable label
	 * - description: string — short description
	 * - icon: string — icon identifier
	 * - attributes: array — default attribute overrides
	 * - isDefault: bool — whether this is the default variation
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getVariations(): array;

	/**
	 * Get available block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array;

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
	public function render( array $content, array $styles, array $context = [], array $innerBlocks = [] ): string;

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
	public function renderEditor( array $content, array $styles, array $context = [], array $innerBlocks = [] ): string;

	/**
	 * Get the block schema version.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getVersion(): int;

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
	public function migrate( array $content, int $fromVersion ): array;

	/**
	 * Whether this block should appear in the block inserter.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isPublic(): bool;

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * Returns an array of control groups with their controls
	 * for rendering in the block toolbar.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array;

	/**
	 * Get a flat list of active style-related supports.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, string>
	 */
	public function getActiveStyleSupports(): array;

	/**
	 * Check whether this block has a custom inspector view.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function hasCustomInspector(): bool;

	/**
	 * Render the custom inspector view for this block.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $data Data to pass to the inspector view.
	 *
	 * @return string
	 */
	public function renderInspector( array $data = [] ): string;

	/**
	 * Check whether this block has a custom toolbar view.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function hasCustomToolbar(): bool;

	/**
	 * Render the custom toolbar view for this block.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $data Data to pass to the toolbar view.
	 *
	 * @return string
	 */
	public function renderToolbar( array $data = [] ): string;
}
