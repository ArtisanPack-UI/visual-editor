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
	 * Validate the current theme.json file.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function handleValidate(): int
	{
		$path = resource_path( 'theme.json' );

		if ( ! file_exists( $path ) ) {
			$this->components->error(
				__( 'visual-editor::ve.theme_json_not_found', [ 'path' => $path ] ),
			);

			return self::FAILURE;
		}

		$loader = app( ThemeJsonLoader::class );
		$valid  = $loader->load( $path );

		if ( $valid ) {
			$this->components->info(
				__( 'visual-editor::ve.theme_json_valid' ),
			);

			return self::SUCCESS;
		}

		$this->components->error(
			__( 'visual-editor::ve.theme_json_validation_failed' ),
		);

		foreach ( $loader->getErrors() as $error ) {
			$this->components->bulletList( [ $error ] );
		}

		return self::FAILURE;
	}
}
