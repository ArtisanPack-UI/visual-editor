<?php

/**
 * Theme JSON Artisan Command.
 *
 * Provides --init and --validate options for managing theme.json files.
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

use ArtisanPackUI\VisualEditor\Services\ThemeJsonLoader;
use Illuminate\Console\Command;

/**
 * Artisan command for scaffolding and validating theme.json files.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Console\Commands
 *
 * @since      1.0.0
 */
class ThemeJsonCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $signature = 've:theme-json
		{--init : Scaffold a starter theme.json file}
		{--validate : Validate the current theme.json file}';

	/**
	 * The console command description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'Manage the visual editor theme.json file';

	/**
	 * Execute the console command.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$init     = $this->option( 'init' );
		$validate = $this->option( 'validate' );

		if ( $init && $validate ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_both_options' ),
			);

			return self::FAILURE;
		}

		if ( ! $init && ! $validate ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_no_option' ),
			);

			return self::FAILURE;
		}

		if ( $init ) {
			return $this->handleInit();
		}

		return $this->handleValidate();
	}

	/**
	 * Scaffold a starter theme.json file.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function handleInit(): int
	{
		$destination = resource_path( 'theme.json' );

		if ( file_exists( $destination ) ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_already_exists', [ 'path' => $destination ] ),
			);

			return self::FAILURE;
		}

		$stub = __DIR__ . '/../../../stubs/theme.json';

		if ( ! file_exists( $stub ) ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_stub_not_found' ),
			);

			return self::FAILURE;
		}

		$dir = dirname( $destination );

		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_dir_failed', [ 'dir' => $dir ] ),
			);

			return self::FAILURE;
		}

		if ( false === copy( $stub, $destination ) ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_copy_failed' ),
			);

			return self::FAILURE;
		}

		$this->components->info(
			__( 'visual-editor::ve.theme_json_created', [ 'path' => $destination ] ),
		);

		return self::SUCCESS;
	}

	/**
	 * Validate theme.json files using the same cascade as boot.
	 *
	 * Resolves paths from config and registered paths, falling back
	 * to resource_path('theme.json') when no paths are configured.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function handleValidate(): int
	{
		$loader      = app( ThemeJsonLoader::class );
		$configPaths = config( 'artisanpack.visual-editor.theme_json.paths', [] );

		// Fallback: only use default location when both config and
		// registered paths are empty.
		if ( [] === $configPaths && [] === $loader->getRegisteredPaths() ) {
			$defaultPath = resource_path( 'theme.json' );

			if ( ! file_exists( $defaultPath ) ) {
				$this->components->error(
					__( 'visual-editor::ve.theme_json_not_found', [ 'path' => $defaultPath ] ),
				);

				return self::FAILURE;
			}

			$configPaths = [ $defaultPath ];
		}

		$valid  = $loader->loadPaths( $configPaths );
		$errors = $loader->getErrors();

		if ( $valid && [] === $errors ) {
			$loaded = $loader->getLoadedPaths();

			$this->components->info(
				__( 'visual-editor::ve.theme_json_valid' ),
			);

			foreach ( $loaded as $path ) {
				$this->components->bulletList( [ $path ] );
			}

			return self::SUCCESS;
		}

		// No files loaded and no validation errors means none of the
		// resolved paths existed on disk.
		if ( ! $valid && [] === $errors ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_no_files_found' ),
			);

			$allPaths = array_merge( $configPaths, $loader->getRegisteredPaths() );
			$this->components->bulletList( $allPaths );

			return self::FAILURE;
		}

		$this->components->error(
			__( 'visual-editor::ve.theme_json_validation_failed' ),
		);

		foreach ( $errors as $error ) {
			$this->components->bulletList( [ $error ] );
		}

		return self::FAILURE;
	}
}
