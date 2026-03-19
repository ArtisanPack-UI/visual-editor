<?php

/**
 * Template Part Manager Service.
 *
 * Manages template part registration, resolution, and CRUD operations
 * for reusable layout sections (headers, footers, sidebars).
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

use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use RuntimeException;

/**
 * Service for registering, resolving, and managing template parts.
 *
 * Provides an in-memory registry for programmatically registered template
 * parts and database-backed CRUD for user-created parts. The resolve()
 * method checks the database first, then falls back to the in-memory registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class TemplatePartManager
{
	/**
	 * In-memory registry of programmatically registered template parts.
	 *
	 * Keyed by template part slug.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $registered = [];

	/**
	 * Register a template part definition programmatically.
	 *
	 * Registered template parts serve as defaults that can be overridden
	 * by database-stored template parts with the same slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug   The unique template part slug.
	 * @param array<string, mixed> $config The template part configuration.
	 *
	 * @return void
	 */
	public function register( string $slug, array $config ): void
	{
		$this->registered[ $slug ] = array_merge( [
			'name'        => $slug,
			'slug'        => $slug,
			'description' => null,
			'area'        => 'custom',
			'content'     => [],
			'status'      => 'active',
			'is_custom'   => false,
		], $config );
	}

	/**
	 * Unregister a programmatically registered template part.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template part slug to unregister.
	 *
	 * @return void
	 */
	public function unregister( string $slug ): void
	{
		unset( $this->registered[ $slug ] );
	}

	/**
	 * Get all template parts (database + registered).
	 *
	 * Database template parts take precedence over in-memory registered
	 * template parts with the same slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array
	{
		$parts = $this->registered;

		$dbParts = TemplatePart::query()->get();

		foreach ( $dbParts as $part ) {
			$parts[ $part->slug ] = $part->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templateParts', $parts );
	}

	/**
	 * Get all active template parts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function allActive(): array
	{
		$parts = array_filter(
			$this->registered,
			fn ( array $p ): bool => 'active' === ( $p['status'] ?? 'active' ),
		);

		$dbParts = TemplatePart::active()->get();

		foreach ( $dbParts as $part ) {
			$parts[ $part->slug ] = $part->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templateParts', $parts );
	}

	/**
	 * Get template parts for a specific area.
	 *
	 * Returns template parts assigned to the given area (header, footer,
	 * sidebar, or custom).
	 *
	 * @since 1.0.0
	 *
	 * @param string $area The area to filter by.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function forArea( string $area ): array
	{
		$parts = array_filter(
			$this->registered,
			fn ( array $p ): bool => $area === ( $p['area'] ?? 'custom' ),
		);

		$dbParts = TemplatePart::active()->forArea( $area )->get();

		foreach ( $dbParts as $part ) {
			$parts[ $part->slug ] = $part->toArray();
		}

		return veApplyFilters( 'ap.visualEditor.templateParts', $parts );
	}

	/**
	 * Resolve a template part by slug.
	 *
	 * Checks the database first, then falls back to the in-memory registry.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template part slug to resolve.
	 *
	 * @return array<string, mixed>|TemplatePart|null The template part model, registered array, or null.
	 */
	public function resolve( string $slug ): TemplatePart|array|null
	{
		$dbPart = TemplatePart::where( 'slug', $slug )->first();

		if ( null !== $dbPart ) {
			return $dbPart;
		}

		return $this->registered[ $slug ] ?? null;
	}

	/**
	 * Check if a template part exists by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The template part slug to check.
	 *
	 * @return bool
	 */
	public function exists( string $slug ): bool
	{
		if ( isset( $this->registered[ $slug ] ) ) {
			return true;
		}

		return TemplatePart::where( 'slug', $slug )->exists();
	}

	/**
	 * Create a new template part in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data The template part data.
	 *
	 * @return TemplatePart
	 */
	public function create( array $data ): TemplatePart
	{
		if ( isset( $data['slug'] ) && TemplatePart::where( 'slug', $data['slug'] )->exists() ) {
			throw new RuntimeException(
				__( 'A template part with the slug ":slug" already exists.', [ 'slug' => $data['slug'] ] ),
			);
		}

		$part = TemplatePart::create( $data );

		veDoAction( 'ap.visualEditor.templatePartCreated', $part );

		return $part;
	}

	/**
	 * Update an existing template part.
	 *
	 * Creates a revision before applying changes if the template part
	 * has content modifications.
	 *
	 * @since 1.0.0
	 *
	 * @param TemplatePart         $part   The template part to update.
	 * @param array<string, mixed> $data   The data to update.
	 * @param int|null             $userId The user performing the update.
	 *
	 * @throws RuntimeException If the template part is locked.
	 *
	 * @return TemplatePart
	 */
	public function update( TemplatePart $part, array $data, ?int $userId = null ): TemplatePart
	{
		if ( $part->is_locked ) {
			throw new RuntimeException(
				__( 'Template part ":name" is locked and cannot be edited.', [ 'name' => $part->name ] ),
			);
		}

		if ( isset( $data['content'] ) && $data['content'] !== $part->content ) {
			$part->createRevision( $userId );
		}

		$part->update( $data );

		veDoAction( 'ap.visualEditor.templatePartUpdated', $part );

		return $part;
	}

	/**
	 * Delete a template part.
	 *
	 * @since 1.0.0
	 *
	 * @param TemplatePart $part The template part to delete.
	 *
	 * @throws RuntimeException If the template part is locked.
	 *
	 * @return bool
	 */
	public function delete( TemplatePart $part ): bool
	{
		if ( $part->is_locked ) {
			throw new RuntimeException(
				__( 'Template part ":name" is locked and cannot be deleted.', [ 'name' => $part->name ] ),
			);
		}

		veDoAction( 'ap.visualEditor.templatePartDeleting', $part );

		return (bool) $part->delete();
	}

	/**
	 * Duplicate an existing template part.
	 *
	 * @since 1.0.0
	 *
	 * @param TemplatePart $part    The template part to duplicate.
	 * @param string       $newSlug The slug for the duplicated template part.
	 * @param string|null  $newName Optional new name for the duplicate.
	 *
	 * @return TemplatePart
	 */
	public function duplicate( TemplatePart $part, string $newSlug, ?string $newName = null ): TemplatePart
	{
		$duplicate = $part->duplicate( $newSlug, $newName );

		veDoAction( 'ap.visualEditor.templatePartDuplicated', $duplicate, $part );

		return $duplicate;
	}

	/**
	 * Lock a template part to prevent editing and deletion.
	 *
	 * @since 1.0.0
	 *
	 * @param TemplatePart $part The template part to lock.
	 *
	 * @return TemplatePart
	 */
	public function lock( TemplatePart $part ): TemplatePart
	{
		$part->update( [ 'is_locked' => true ] );

		veDoAction( 'ap.visualEditor.templatePartLocked', $part );

		return $part;
	}

	/**
	 * Unlock a template part to allow editing and deletion.
	 *
	 * @since 1.0.0
	 *
	 * @param TemplatePart $part The template part to unlock.
	 *
	 * @return TemplatePart
	 */
	public function unlock( TemplatePart $part ): TemplatePart
	{
		$part->update( [ 'is_locked' => false ] );

		veDoAction( 'ap.visualEditor.templatePartUnlocked', $part );

		return $part;
	}

	/**
	 * Persist all programmatically registered template parts to the database.
	 *
	 * Useful for seeding default template parts. Skips parts that
	 * already exist in the database.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, TemplatePart> The created template parts.
	 */
	public function seedRegistered(): array
	{
		$created = [];

		foreach ( $this->registered as $slug => $config ) {
			if ( TemplatePart::where( 'slug', $slug )->exists() ) {
				continue;
			}

			$created[] = $this->create( [
				'name'        => $config['name'],
				'slug'        => $slug,
				'description' => $config['description'] ?? null,
				'area'        => $config['area'] ?? 'custom',
				'content'     => $config['content'] ?? [],
				'status'      => $config['status'] ?? 'active',
				'is_custom'   => false,
				'is_locked'   => false,
			] );
		}

		return $created;
	}

	/**
	 * Get only the in-memory registered template parts.
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
	 * Clear all in-memory registered template parts.
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
