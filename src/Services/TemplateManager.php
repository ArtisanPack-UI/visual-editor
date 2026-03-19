<?php

/**
 * Template Manager Service.
 *
 * Manages template registration, resolution, and CRUD operations
 * for the visual editor template system.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Models\Template;
use RuntimeException;

/**
 * Service for registering, resolving, and managing templates.
 *
 * Provides an in-memory registry for programmatically registered templates
 * and database-backed CRUD for user-created templates. The resolve()
 * method checks the database first, then falls back to the in-memory registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class TemplateManager
{
	/**
	 * In-memory registry of programmatically registered templates.
	 *
	 * Keyed by template slug.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $registered = [];

	/**
	 * Register a template definition programmatically.
	 *
	 * Registered templates serve as defaults that can be overridden
	 * by database-stored templates with the same slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug   The unique template slug.
	 * @param array<string, mixed> $config The template configuration.
	 *
	 * @return void
	 */
	public function register( string $slug, array $config ): void
	{
		$this->registered[ $slug ] = array_merge( [
			'name'                  => $slug,
			'slug'                  => $slug,
			'description'           => null,
			'type'                  => 'page',
			'for_content_type'      => null,
			'content'               => [],
			'status'                => 'active',
			'is_custom'             => false,
			'content_area_settings' => [],
			'styles'                => [],
		], $config );
	}

	/**
	 * Unregister a programmatically registered template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template slug to unregister.
	 *
	 * @return void
	 */
	public function unregister( string $slug ): void
	{
		unset( $this->registered[ $slug ] );
	}

	/**
	 * Get all templates (database + registered).
	 *
	 * Database templates take precedence over in-memory registered
	 * templates with the same slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array
	{
		$templates = $this->registered;

		$dbTemplates = Template::query()->get();

		foreach ( $dbTemplates as $template ) {
			$templates[ $template->slug ] = $template->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templates', $templates );
	}

	/**
	 * Get all active templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function allActive(): array
	{
		$templates = array_filter(
			$this->registered,
			fn ( array $t ): bool => 'active' === ( $t['status'] ?? 'active' ),
		);

		$dbTemplates = Template::active()->get();

		foreach ( $dbTemplates as $template ) {
			$templates[ $template->slug ] = $template->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templates', $templates );
	}

	/**
	 * Get templates available for a specific content type.
	 *
	 * Returns templates that either have no content type restriction
	 * or are explicitly assigned to the given content type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contentType The content type to filter by.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function forContentType( string $contentType ): array
	{
		$templates = array_filter(
			$this->registered,
			fn ( array $t ): bool => null === $t['for_content_type'] || $contentType === $t['for_content_type'],
		);

		$dbTemplates = Template::active()->forContentType( $contentType )->get();

		foreach ( $dbTemplates as $template ) {
			$templates[ $template->slug ] = $template->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templates', $templates );
	}

	/**
	 * Resolve a template by slug.
	 *
	 * Checks the database first, then falls back to the in-memory registry.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template slug to resolve.
	 *
	 * @return array<string, mixed>|Template|null The template model, registered array, or null.
	 */
	public function resolve( string $slug ): Template|array|null
	{
		$dbTemplate = Template::where( 'slug', $slug )->first();

		if ( null !== $dbTemplate ) {
			return $dbTemplate;
		}

		return $this->registered[ $slug ] ?? null;
	}

	/**
	 * Check if a template exists by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template slug to check.
	 *
	 * @return bool
	 */
	public function exists( string $slug ): bool
	{
		if ( isset( $this->registered[ $slug ] ) ) {
			return true;
		}

		return Template::where( 'slug', $slug )->exists();
	}

	/**
	 * Create a new template in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data The template data.
	 *
	 * @return Template
	 */
	public function create( array $data ): Template
	{
		$template = Template::create( $data );

		veDoAction( 'ap.visualEditor.templateCreated', $template );

		return $template;
	}

	/**
	 * Update an existing template.
	 *
	 * Creates a revision before applying changes if the template
	 * has content modifications.
	 *
	 * @since 1.0.0
	 *
	 * @param Template             $template The template to update.
	 * @param array<string, mixed> $data     The data to update.
	 * @param int|null             $userId   The user performing the update.
	 *
	 * @throws RuntimeException If the template is locked.
	 *
	 * @return Template
	 */
	public function update( Template $template, array $data, ?int $userId = null ): Template
	{
		if ( $template->is_locked ) {
			throw new RuntimeException(
				__( 'Template ":name" is locked and cannot be edited.', [ 'name' => $template->name ] ),
			);
		}

		if ( isset( $data['content'] ) && $data['content'] !== $template->content ) {
			$template->createRevision( $userId );
		}

		$template->update( $data );

		veDoAction( 'ap.visualEditor.templateUpdated', $template );

		return $template;
	}

	/**
	 * Delete a template.
	 *
	 * @since 1.0.0
	 *
	 * @param Template $template The template to delete.
	 *
	 * @throws RuntimeException If the template is locked.
	 *
	 * @return bool
	 */
	public function delete( Template $template ): bool
	{
		if ( $template->is_locked ) {
			throw new RuntimeException(
				__( 'Template ":name" is locked and cannot be deleted.', [ 'name' => $template->name ] ),
			);
		}

		veDoAction( 'ap.visualEditor.templateDeleting', $template );

		return (bool) $template->delete();
	}

	/**
	 * Duplicate an existing template.
	 *
	 * @since 1.0.0
	 *
	 * @param Template    $template The template to duplicate.
	 * @param string      $newSlug  The slug for the duplicated template.
	 * @param string|null $newName  Optional new name for the duplicate.
	 *
	 * @return Template
	 */
	public function duplicate( Template $template, string $newSlug, ?string $newName = null ): Template
	{
		$duplicate = $template->duplicate( $newSlug, $newName );

		veDoAction( 'ap.visualEditor.templateDuplicated', $duplicate, $template );

		return $duplicate;
	}

	/**
	 * Lock a template to prevent editing and deletion.
	 *
	 * @since 1.0.0
	 *
	 * @param Template $template The template to lock.
	 *
	 * @return Template
	 */
	public function lock( Template $template ): Template
	{
		$template->update( [ 'is_locked' => true ] );

		veDoAction( 'ap.visualEditor.templateLocked', $template );

		return $template;
	}

	/**
	 * Unlock a template to allow editing and deletion.
	 *
	 * @since 1.0.0
	 *
	 * @param Template $template The template to unlock.
	 *
	 * @return Template
	 */
	public function unlock( Template $template ): Template
	{
		$template->update( [ 'is_locked' => false ] );

		veDoAction( 'ap.visualEditor.templateUnlocked', $template );

		return $template;
	}

	/**
	 * Persist all programmatically registered templates to the database.
	 *
	 * Useful for seeding default templates. Skips templates that
	 * already exist in the database.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, Template> The created templates.
	 */
	public function seedRegistered(): array
	{
		$created = [];

		foreach ( $this->registered as $slug => $config ) {
			if ( Template::where( 'slug', $slug )->exists() ) {
				continue;
			}

			$created[] = $this->create( [
				'name'                  => $config['name'],
				'slug'                  => $slug,
				'description'           => $config['description'] ?? null,
				'type'                  => $config['type'] ?? 'page',
				'for_content_type'      => $config['for_content_type'] ?? null,
				'content'               => $config['content'] ?? [],
				'status'                => $config['status'] ?? 'active',
				'content_area_settings' => $config['content_area_settings'] ?? [],
				'styles'                => $config['styles'] ?? [],
				'is_custom'             => false,
				'is_locked'             => false,
			] );
		}

		return $created;
	}

	/**
	 * Get only the in-memory registered templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getRegistered(): array
	{
		return $this->registered;
	}

	/**
	 * Clear all in-memory registered templates.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clearRegistered(): void
	{
		$this->registered = [];
	}
}
