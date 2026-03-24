<?php

/**
 * Styles Export Artisan Command.
 *
 * Exports the current global styles configuration to a JSON file
 * or saves it as a named preset in the local library.
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
 * Artisan command for exporting global styles.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @since      1.0.0
 */
class StylesExportCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $signature = 've:styles-export
		{--output= : File path to write the export JSON to}
		{--name= : A human-readable name for the export}
		{--only= : Comma-separated list of sections to export (colors,typography,spacing)}
		{--preset= : Save as a named preset instead of exporting to file}';

	/**
	 * The console command description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'Export global styles to a JSON file or save as a preset';

	/**
	 * Execute the console command.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$service    = app( StyleImportExportService::class );
		$output     = $this->option( 'output' );
		$name       = $this->option( 'name' );
		$only       = $this->option( 'only' );
		$presetName = $this->option( 'preset' );

		$sections = null;

		if ( null !== $only ) {
			[ $sections, $invalid ] = $this->parseSections( (string) $only );

			if ( [] !== $invalid ) {
				$this->components->error(
					__( 'visual-editor::ve.styles_export_invalid_sections', [
						'invalid' => implode( ', ', $invalid ),
						'allowed' => implode( ', ', StyleImportExportService::VALID_SECTIONS ),
					] ),
				);

				return self::FAILURE;
			}

			if ( [] === $sections ) {
				$this->components->error(
					__( 'visual-editor::ve.styles_export_invalid_sections', [
						'invalid' => (string) $only,
						'allowed' => implode( ', ', StyleImportExportService::VALID_SECTIONS ),
					] ),
				);

				return self::FAILURE;
			}
		}

		if ( null !== $presetName && '' !== $presetName ) {
			return $this->handlePresetSave( $service, (string) $presetName );
		}

		return $this->handleFileExport( $service, $output, $name, $sections );
	}

	/**
	 * Handle saving styles as a preset.
	 *
	 * @since 1.0.0
	 *
	 * @param StyleImportExportService $service    The import/export service.
	 * @param string                   $presetName The preset name.
	 *
	 * @return int
	 */
	protected function handlePresetSave( StyleImportExportService $service, string $presetName ): int
	{
		$preset = $service->savePreset( $presetName );

		$this->components->info(
			__( 'visual-editor::ve.styles_export_preset_saved', [
				'name' => $preset->name,
				'slug' => $preset->slug,
			] ),
		);

		return self::SUCCESS;
	}

	/**
	 * Handle exporting styles to a file.
	 *
	 * @since 1.0.0
	 *
	 * @param StyleImportExportService $service  The import/export service.
	 * @param string|null              $output   The output file path.
	 * @param string|null              $name     The export name.
	 * @param array|null               $sections Sections to export.
	 *
	 * @return int
	 */
	protected function handleFileExport( StyleImportExportService $service, ?string $output, ?string $name, ?array $sections ): int
	{
		if ( null === $output || '' === $output ) {
			$output = 'styles-export.json';
		}

		$exportName = ( null !== $name && '' !== $name ) ? $name : 'Exported Styles';

		$success = $service->exportToFile( (string) $output, $exportName, $sections );

		if ( ! $success ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_export_write_failed', [ 'path' => $output ] ),
			);

			return self::FAILURE;
		}

		$this->components->info(
			__( 'visual-editor::ve.styles_export_success', [ 'path' => $output ] ),
		);

		return self::SUCCESS;
	}

	/**
	 * Parse the --only option into a validated array of section names.
	 *
	 * Returns a tuple of [valid sections, invalid sections]. The caller
	 * should check for invalid entries and fail fast before proceeding.
	 *
	 * @since 1.0.0
	 *
	 * @param string $only The comma-separated sections string.
	 *
	 * @return array{0: array<int, string>, 1: array<int, string>} [valid, invalid]
	 */
	protected function parseSections( string $only ): array
	{
		$requested = array_map( 'trim', explode( ',', $only ) );
		$requested = array_filter( $requested, fn ( string $s ): bool => '' !== $s );
		$invalid   = array_values( array_diff( $requested, StyleImportExportService::VALID_SECTIONS ) );
		$valid     = array_values( array_intersect( $requested, StyleImportExportService::VALID_SECTIONS ) );

		return [ $valid, $invalid ];
	}
}
