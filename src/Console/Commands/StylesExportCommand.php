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

		$sections = null !== $only ? $this->parseSections( (string) $only ) : null;

		if ( null !== $sections && [] === $sections ) {
			$this->components->error(
				__( 'visual-editor::ve.styles_export_invalid_sections', [
					'allowed' => implode( ', ', StyleImportExportService::VALID_SECTIONS ),
				] ),
			);

			return self::FAILURE;
		}

		if ( null !== $presetName && '' !== $presetName ) {
			return $this->handlePresetSave( $service, (string) $presetName, $sections );
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
	 * @param array|null               $sections   Sections to include.
	 *
	 * @return int
	 */
	protected function handlePresetSave( StyleImportExportService $service, string $presetName, ?array $sections ): int
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
