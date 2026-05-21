<?php

/**
 * Artisan command that seeds sample site-editor content for the
 * dev app (or any other host app that wants the C1/C2 DB-backed
 * entities to render with non-empty data).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Console\Commands;

use ArtisanPackUI\VisualEditor\SampleContent\SampleContentRepository;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SeedSampleContentCommand extends Command
{
	protected $signature = 'visual-editor:seed-sample-content
        {--path= : Absolute path to a sample-content fixtures directory.}';

	protected $description = 'Seed templates, template parts, navigation, patterns, and global styles from the sample-content fixtures into the database.';

	public function handle( SampleContentRepository $repository ): int
	{
		$fixturesDir = $this->resolveFixturesDirectory();

		if ( null === $fixturesDir ) {
			$this->error(
				'Unable to locate a sample-content fixtures directory. Pass --path or set '
				. 'artisanpack.visual-editor.sample_content.fixtures_path in config.'
			);

			return self::FAILURE;
		}

		try {
			$fixtures = $repository->loadFixtures( $fixturesDir );
			$counts   = $repository->seedToDatabase( $fixtures );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			$this->error( $e->getMessage() );

			return self::FAILURE;
		} catch ( Throwable $e ) {
			$this->error(
				sprintf( 'Sample-content seed failed: %s', $e->getMessage() )
			);

			return self::FAILURE;
		}

		$this->info( sprintf( 'Seeded sample content from %s', $fixturesDir ) );

		foreach ( $counts as $fragment => $count ) {
			$this->line( sprintf( '  - %-15s %d record(s)', $fragment, $count ) );
		}

		return self::SUCCESS;
	}

	protected function resolveFixturesDirectory(): ?string
	{
		$override = $this->option( 'path' );

		if ( is_string( $override ) && '' !== $override ) {
			return $override;
		}

		$configured = config( 'artisanpack.visual-editor.sample_content.fixtures_path' );

		if ( is_string( $configured ) && '' !== $configured ) {
			return $configured;
		}

		$packaged = dirname( __DIR__, 3 ) . '/tests/Fixtures/sample-content';

		if ( is_dir( $packaged ) ) {
			return $packaged;
		}

		return null;
	}
}
