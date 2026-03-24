<?php

/**
 * Styles Import Artisan Command.
 *
 * Imports a global styles configuration from a JSON file, with
 * support for selective import, force mode, and preset application.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Console\Commands;

use ArtisanPackUI\VisualEditor\Services\StyleImportExportService;
use Illuminate\Console\Command;

/**
 * Artisan command for importing global styles.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @since      1.0.0
 */
class StylesImportCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $signature = 've:styles-import
		{file? : Path to the JSON file to import}
		{--force : Skip conflict confirmation and overwrite}
		{--only= : Comma-separated list of sections to import (colors,typography,spacing)}
		{--preset= : Apply a saved preset by slug instead of importing a file}
		{--save-preset= : Save the imported file as a named preset without applying}
		{--list-presets : List all saved presets}
		{--delete-preset= : Delete a saved preset by slug}';

	/**
	 * The console command description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'Import global styles from a JSON file or apply a saved preset';

	/**
	 * Execute the console command.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$service = app( StyleImportExportService::class );

		if ( $this->option( 'list-presets' ) ) {
			return $this->handleListPresets( $service );
		}

		$deletePreset = $this->option( 'delete-preset' );

		if ( null !== $deletePreset && '' !== $deletePreset ) {
			return $this->handleDeletePreset( $service, (string) $deletePreset );
		}

		$presetSlug = $this->option( 'preset' );

		if ( null !== $presetSlug && '' !== $presetSlug ) {
			return $this->handlePresetApply( $service, (string) $presetSlug );
		}

		return $this->handleFileImport( $service );
	}

	/**
	 * Handle listing saved presets.
	 *
	 * @since 1.0.0
	 *
	 * @param StyleImportExportService $service The import/export service.
	 *
	 * @return int
	 */
	protected function handleListPresets( StyleImportExportService $service ): int
	{
		$presets = $service->listPresets();

		if ( $presets->isEmpty() ) {
			$this->components->info(
				__( 'visual-editor::ve.styles_preset_none_found' ),
			);

			return self::SUCCESS;
		}

		$rows = $presets->map( fn ( $preset ) => [
			$preset->slug,
			$preset->name,
			$preset->description ?? '-',
			$preset->created_at->format( 'Y-m-d H:i' ),
		] )->toArray();

		$this->table(
			[
				__( 'visual-editor::ve.styles_preset_col_slug' ),
				__( 'visual-editor::ve.styles_preset_col_name' ),
				__( 'visual-editor::ve.styles_preset_col_description' ),
				__( 'visual-editor::ve.styles_preset_col_created' ),
			],
			$rows,
		);

		return self::SUCCESS;
	}

	/**
	 * Handle deleting a preset.
	 *
	 * @since 1.0.0
	 *
	 * @param StyleImportExportService $service The import/export service.
	 * @param string                   $slug    The preset slug.
	 *
	 * @return int
	 */
	protected function handleDeletePreset( StyleImportExportService $service, string $slug ): int
	{
		$deleted = $service->deletePreset( $slug );

		if ( ! $deleted ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_preset_not_found', [ 'slug' => $slug ] ),
			);

			return self::FAILURE;
		}

		$this->components->info(
			__( 'visual-editor::ve.styles_preset_deleted', [ 'slug' => $slug ] ),
		);

		return self::SUCCESS;
	}

	/**
	 * Handle applying a saved preset.
	 *
	 * @since 1.0.0
	 *
	 * @param StyleImportExportService $service The import/export service.
	 * @param string                   $slug    The preset slug.
	 *
	 * @return int
	 */
	protected function handlePresetApply( StyleImportExportService $service, string $slug ): int
	{
		$only     = $this->option( 'only' );
		$sections = null !== $only ? $this->parseSections( (string) $only ) : null;

		if ( null !== $sections && [] === $sections ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_import_invalid_sections', [
					'allowed' => implode( ', ', StyleImportExportService::VALID_SECTIONS ),
				] ),
			);

			return self::FAILURE;
		}

		$result = $service->applyPreset( $slug, $sections );

		if ( null === $result ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_preset_not_found', [ 'slug' => $slug ] ),
			);

			return self::FAILURE;
		}

		$this->components->info(
			__( 'visual-editor::ve.styles_preset_applied', [ 'slug' => $slug ] ),
		);

		return self::SUCCESS;
	}

	/**
	 * Handle importing from a file.
	 *
	 * @since 1.0.0
	 *
	 * @param StyleImportExportService $service The import/export service.
	 *
	 * @return int
	 */
	protected function handleFileImport( StyleImportExportService $service ): int
	{
		$file = $this->argument( 'file' );

		if ( null === $file || '' === $file ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_import_no_file' ),
			);

			return self::FAILURE;
		}

		$only     = $this->option( 'only' );
		$sections = null !== $only ? $this->parseSections( (string) $only ) : null;

		if ( null !== $sections && [] === $sections ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_import_invalid_sections', [
					'allowed' => implode( ', ', StyleImportExportService::VALID_SECTIONS ),
				] ),
			);

			return self::FAILURE;
		}

		$validation = $service->validateFile( (string) $file );

		if ( ! $validation['valid'] ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_import_validation_failed' ),
			);

			foreach ( $validation['errors'] as $error ) {
				$this->components->bulletList( [ $error ] );
			}

			return self::FAILURE;
		}

		$savePreset = $this->option( 'save-preset' );

		if ( null !== $savePreset && '' !== $savePreset ) {
			$preset = $service->saveImportAsPreset( $validation['data'], (string) $savePreset );

			$this->components->info(
				__( 'visual-editor::ve.styles_export_preset_saved', [
					'name' => $preset->name,
					'slug' => $preset->slug,
				] ),
			);

			return self::SUCCESS;
		}

		if ( ! $this->option( 'force' ) ) {
			$conflicts = $service->detectConflicts( $validation['data'] );

			if ( [] !== $conflicts ) {
				$this->components->warn(
					__( 'visual-editor::ve.styles_import_conflicts_detected', [
						'sections' => implode( ', ', array_keys( $conflicts ) ),
					] ),
				);

				$this->components->info(
					__( 'visual-editor::ve.styles_import_use_force' ),
				);

				return self::FAILURE;
			}
		}

		$result = $service->importFromFile( (string) $file, $sections );

		if ( ! $result['success'] ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_import_failed' ),
			);

			foreach ( $result['errors'] as $error ) {
				$this->components->bulletList( [ $error ] );
			}

			return self::FAILURE;
		}

		$this->components->info(
			__( 'visual-editor::ve.styles_import_success' ),
		);

		return self::SUCCESS;
	}

	/**
	 * Parse the --only option into a validated array of section names.
	 *
	 * @since 1.0.0
	 *
	 * @param string $only The comma-separated sections string.
	 *
	 * @return array<int, string> The validated section names (empty if none valid).
	 */
	protected function parseSections( string $only ): array
	{
		$requested = array_map( 'trim', explode( ',', $only ) );
		$valid     = array_filter(
			$requested,
			fn ( string $section ): bool => in_array( $section, StyleImportExportService::VALID_SECTIONS, true ),
		);

		return array_values( $valid );
	}
}
