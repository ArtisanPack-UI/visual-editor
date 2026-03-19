<?php

/**
 * Editor Content Interface.
 *
 * Contract for Eloquent models that store visual editor block content.
 * Implement this interface alongside the HasVisualEditorContent trait
 * to integrate any model with the visual editor's save and render pipeline.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Contracts
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Contracts;

/**
 * Interface for models that store visual editor content.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Contracts
 *
 * @since      1.0.0
 */
interface EditorContent
{
	/**
	 * Get the block content array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlocks(): array;

	/**
	 * Set the block content array.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block content array.
	 *
	 * @return void
	 */
	public function setBlocks( array $blocks ): void;

	/**
	 * Save the model from editor metadata.
	 *
	 * Receives the full meta array from the editor (blocks, status, etc.)
	 * and persists the data. Override this method to handle custom fields
	 * like title, excerpt, taxonomies, etc.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta The editor metadata including blocks, status, etc.
	 *
	 * @return void
	 */
	public function saveFromEditor( array $meta ): void;

	/**
	 * Render the stored blocks as front-end HTML.
	 *
	 * @since 1.0.0
	 *
	 * @return string The rendered HTML output.
	 */
	public function renderBlocks(): string;
}
