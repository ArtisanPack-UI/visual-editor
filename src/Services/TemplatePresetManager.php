<?php

/**
 * Template Preset Manager Service.
 *
 * Manages template preset registration, resolution, and CRUD operations.
 * Presets serve as reusable starting points for creating new templates.
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
use ArtisanPackUI\VisualEditor\Models\TemplatePreset;
use RuntimeException;

/**
 * Service for registering, resolving, and managing template presets.
 *
 * Provides an in-memory registry for programmatically registered presets
 * and database-backed CRUD for user-created presets. The resolve()
 * method checks the database first, then falls back to the in-memory registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class TemplatePresetManager
{
	/**
	 * In-memory registry of programmatically registered presets.
	 *
	 * Keyed by preset slug.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $registered = [];

	/**
	 * Register a preset definition programmatically.
	 *
	 * Registered presets serve as built-in defaults that can be overridden
	 * by database-stored presets with the same slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug   The unique preset slug.
	 * @param array<string, mixed> $config The preset configuration.
	 *
	 * @return void
	 */
	public function register( string $slug, array $config ): void
	{
		$this->registered[ $slug ] = array_merge( [
			'name'                  => $slug,
			'slug'                  => $slug,
			'description'           => null,
			'category'              => null,
			'thumbnail'             => null,
			'content'               => [],
			'content_area_settings' => [],
			'styles'                => [],
			'template_parts'        => [],
			'type'                  => 'page',
			'for_content_type'      => null,
			'is_custom'             => false,
		], $config );
	}

	/**
	 * Unregister a programmatically registered preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The preset slug to unregister.
	 *
	 * @return void
	 */
	public function unregister( string $slug ): void
	{
		unset( $this->registered[ $slug ] );
	}

	/**
	 * Get all presets (database + registered).
	 *
	 * Database presets take precedence over in-memory registered
	 * presets with the same slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array
	{
		$presets = $this->registered;

		foreach ( TemplatePreset::query()->cursor() as $preset ) {
			$presets[ $preset->slug ] = $preset->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templatePresets', $presets );
	}

	/**
	 * Get presets filtered by category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to filter by.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function forCategory( string $category ): array
	{
		$presets = array_filter(
			$this->registered,
			fn ( array $p ): bool => $category === ( $p['category'] ?? null ),
		);

		$dbPresets = TemplatePreset::byCategory( $category )->get();

		foreach ( $dbPresets as $preset ) {
			$presets[ $preset->slug ] = $preset->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templatePresets', $presets );
	}

	/**
	 * Get presets available for a specific content type.
	 *
	 * Returns presets that either have no content type restriction
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
		$presets = array_filter(
			$this->registered,
			fn ( array $p ): bool => null === $p['for_content_type'] || $contentType === $p['for_content_type'],
		);

		$dbPresets = TemplatePreset::forContentType( $contentType )->get();

		foreach ( $dbPresets as $preset ) {
			$presets[ $preset->slug ] = $preset->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templatePresets', $presets );
	}

	/**
	 * Get all available preset categories.
	 *
	 * Returns categories from both registered and database presets.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function categories(): array
	{
		$categories = [];

		foreach ( $this->registered as $preset ) {
			if ( null !== ( $preset['category'] ?? null ) ) {
				$categories[] = $preset['category'];
			}
		}

		$dbCategories = TemplatePreset::query()
			->whereNotNull( 'category' )
			->distinct()
			->pluck( 'category' )
			->toArray();

		$categories = array_unique( array_merge( $categories, $dbCategories ) );

		sort( $categories );

		return array_values( $categories );
	}

	/**
	 * Resolve a preset by slug.
	 *
	 * Checks the database first, then falls back to the in-memory registry.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The preset slug to resolve.
	 *
	 * @return array<string, mixed>|TemplatePreset|null The preset model, registered array, or null.
	 */
	public function resolve( string $slug ): TemplatePreset|array|null
	{
		$dbPreset = TemplatePreset::where( 'slug', $slug )->first();

		if ( null !== $dbPreset ) {
			return $dbPreset;
		}

		return $this->registered[ $slug ] ?? null;
	}

	/**
	 * Check if a preset exists by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The preset slug to check.
	 *
	 * @return bool
	 */
	public function exists( string $slug ): bool
	{
		if ( isset( $this->registered[ $slug ] ) ) {
			return true;
		}

		return TemplatePreset::where( 'slug', $slug )->exists();
	}

	/**
	 * Create a new preset in the database.
	 *
	 * The is_custom and user_id fields are not mass-assignable and must
	 * be provided explicitly via the dedicated parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data     The preset data (mass-assignable fields only).
	 * @param bool                 $isCustom Whether this is a user-created preset.
	 * @param int|null             $userId   The user creating the preset.
	 *
	 * @throws RuntimeException If custom presets are disabled and isCustom is true.
	 *
	 * @return TemplatePreset
	 */
	public function create( array $data, bool $isCustom = false, ?int $userId = null ): TemplatePreset
	{
		if ( $isCustom && ! config( 'artisanpack.visual-editor.template_presets.allow_custom_presets', true ) ) {
			throw new RuntimeException(
				__( 'Custom template presets are disabled.' ),
			);
		}

		$preset            = new TemplatePreset( $data );
		$preset->is_custom = $isCustom;
		$preset->user_id   = $userId;
		$preset->save();

		veDoAction( 'ap.visualEditor.templatePresetCreated', $preset );

		return $preset;
	}

	/**
	 * Update an existing preset.
	 *
	 * @since 1.0.0
	 *
	 * @param TemplatePreset       $preset The preset to update.
	 * @param array<string, mixed> $data   The data to update.
	 *
	 * @return TemplatePreset
	 */
	public function update( TemplatePreset $preset, array $data ): TemplatePreset
	{
		$preset->update( $data );

		veDoAction( 'ap.visualEditor.templatePresetUpdated', $preset );

		return $preset;
	}

	/**
	 * Delete a preset.
	 *
	 * @since 1.0.0
	 *
	 * @param TemplatePreset $preset The preset to delete.
	 *
	 * @return bool
	 */
	public function delete( TemplatePreset $preset ): bool
	{
		veDoAction( 'ap.visualEditor.templatePresetDeleting', $preset );

		return (bool) $preset->delete();
	}

	/**
	 * Create a template from a preset.
	 *
	 * Uses the preset's content, settings, and configuration to create
	 * a new template in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $presetSlug The slug of the preset to use.
	 * @param string               $slug       The slug for the new template.
	 * @param string               $name       The name for the new template.
	 * @param array<string, mixed> $overrides  Optional overrides for the template data.
	 *
	 * @return Template|null The created template, or null if the preset was not found.
	 */
	public function createTemplateFromPreset( string $presetSlug, string $slug, string $name, array $overrides = [] ): ?Template
	{
		$preset = $this->resolve( $presetSlug );

		if ( null === $preset ) {
			return null;
		}

		$presetData = $preset instanceof TemplatePreset ? $preset->toArray() : $preset;

		$templateData = array_merge( [
			'name'                  => $name,
			'slug'                  => $slug,
			'description'           => $presetData['description'] ?? null,
			'type'                  => $presetData['type'] ?? 'page',
			'for_content_type'      => $presetData['for_content_type'] ?? null,
			'content'               => $presetData['content'] ?? [],
			'status'                => 'draft',
			'content_area_settings' => $presetData['content_area_settings'] ?? [],
			'styles'                => $presetData['styles'] ?? [],
			'is_custom'             => true,
			'is_locked'             => false,
		], $overrides );

		$template = Template::create( $templateData );

		veDoAction( 'ap.visualEditor.templateCreatedFromPreset', $template, $presetSlug );

		return $template;
	}

	/**
	 * Save an existing template as a new preset.
	 *
	 * This delegates to create(), which fires the generic
	 * `ap.visualEditor.templatePresetCreated` hook. After creation, this
	 * method additionally fires `ap.visualEditor.templateSavedAsPreset`
	 * so listeners can differentiate between generic preset creation and
	 * the "saved from template" flow.
	 *
	 * @since 1.0.0
	 *
	 * @param Template    $template The template to save as a preset.
	 * @param string      $slug     The slug for the new preset.
	 * @param string      $name     The name for the new preset.
	 * @param string|null $category The category for the preset.
	 *
	 * @throws RuntimeException If custom presets are disabled.
	 *
	 * @return TemplatePreset
	 */
	public function saveTemplateAsPreset( Template $template, string $slug, string $name, ?string $category = null ): TemplatePreset
	{
		$preset = $this->create(
			[
				'name'                  => $name,
				'slug'                  => $slug,
				'description'           => $template->description,
				'category'              => $category,
				'content'               => $template->resolveContent(),
				'content_area_settings' => $template->resolveContentAreaSettings(),
				'styles'                => $template->resolveStyles(),
				'type'                  => $template->type,
				'for_content_type'      => $template->for_content_type,
			],
			true,
			$template->user_id,
		);

		veDoAction( 'ap.visualEditor.templateSavedAsPreset', $preset, $template );

		return $preset;
	}

	/**
	 * Persist all programmatically registered presets to the database.
	 *
	 * Useful for seeding default presets. Skips presets that
	 * already exist in the database.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, TemplatePreset> The created presets.
	 */
	public function seedRegistered(): array
	{
		$created = [];

		foreach ( $this->registered as $slug => $config ) {
			if ( TemplatePreset::where( 'slug', $slug )->exists() ) {
				continue;
			}

			$created[] = $this->create(
				[
					'name'                  => $config['name'],
					'slug'                  => $slug,
					'description'           => $config['description'] ?? null,
					'category'              => $config['category'] ?? null,
					'thumbnail'             => $config['thumbnail'] ?? null,
					'content'               => $config['content'] ?? [],
					'content_area_settings' => $config['content_area_settings'] ?? [],
					'styles'                => $config['styles'] ?? [],
					'template_parts'        => $config['template_parts'] ?? [],
					'type'                  => $config['type'] ?? 'page',
					'for_content_type'      => $config['for_content_type'] ?? null,
				],
				false,
			);
		}

		return $created;
	}

	/**
	 * Get only the in-memory registered presets.
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
	 * Clear all in-memory registered presets.
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
