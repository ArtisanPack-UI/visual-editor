<?php

/**
 * `visual-editor:audit-scheduled-blocks` — list every block in the
 * host's registered content that carries a date/time or recurring
 * visibility schedule (#493).
 *
 * Iterates every model registered under `artisanpack.visual-editor.resources`
 * whose class uses the `HasBlockContent` trait. For each row, decodes
 * its block-content JSON, walks the tree with
 * {@see \ArtisanPackUI\VisualEditor\Visibility\ScheduledBlockCollector},
 * and prints a table of `[Resource, ID, Title, Block, Rule, Window]`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Console;

use ArtisanPackUI\VisualEditor\Visibility\ScheduledBlockCollector;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Throwable;

class AuditScheduledBlocksCommand extends Command
{
	protected $signature = 'visual-editor:audit-scheduled-blocks
		{--resource= : Restrict to a single registered resource slug}';

	protected $description = 'List blocks that carry date/time or recurring visibility schedules.';

	public function __construct( protected ScheduledBlockCollector $collector, protected ConfigRepository $config )
	{
		parent::__construct();
	}

	public function handle(): int
	{
		$resources = (array) $this->config->get( 'artisanpack.visual-editor.resources', [] );

		$filter = $this->option( 'resource' );

		if ( is_string( $filter ) && '' !== $filter ) {
			$resources = isset( $resources[ $filter ] ) ? [ $filter => $resources[ $filter ] ] : [];
		}

		if ( [] === $resources ) {
			$this->info( 'No visual-editor resources are registered.' );
			return self::SUCCESS;
		}

		$rows = [];

		foreach ( $resources as $slug => $modelClass ) {
			if ( ! is_string( $slug ) || ! is_string( $modelClass ) || ! class_exists( $modelClass ) ) {
				continue;
			}

			try {
				$instance = new $modelClass();

				if ( ! method_exists( $instance, 'newQuery' ) ) {
					continue;
				}

				$query = $instance->newQuery();

				$results = $query->get();

				foreach ( $results as $row ) {
					$content = $this->readContent( $row );

					if ( [] === $content ) {
						continue;
					}

					foreach ( $this->collector->collect( $content ) as $hit ) {
						$rows[] = [
							'Resource' => $slug,
							'ID'       => is_scalar( $row->getKey() ) ? (string) $row->getKey() : '',
							'Title'    => is_string( $row->title ?? null ) ? $row->title : '',
							'Block'    => $hit['name'],
							'Rule'     => $hit['rule'],
							'Window'   => $this->summarizeWindow( $hit['rule'], $hit['attributes'] ),
						];
					}
				}
			} catch ( Throwable $e ) {
				$this->warn( sprintf( 'Skipped %s: %s', $slug, $e->getMessage() ) );
			}
		}

		if ( [] === $rows ) {
			$this->info( 'No scheduled visibility rules found.' );
			return self::SUCCESS;
		}

		$this->table( [ 'Resource', 'ID', 'Title', 'Block', 'Rule', 'Window' ], $rows );

		return self::SUCCESS;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function readContent( object $row ): array
	{
		foreach ( [ 'block_content', 'blockContent', 'content' ] as $candidate ) {
			$value = $row->{$candidate} ?? null;

			if ( is_array( $value ) ) {
				return $value;
			}

			if ( is_string( $value ) && '' !== $value ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		return [];
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function summarizeWindow( string $rule, array $attrs ): string
	{
		if ( 'dateTimeWindow' === $rule ) {
			$start = isset( $attrs['start'] ) && is_string( $attrs['start'] ) ? $attrs['start'] : '—';
			$end   = isset( $attrs['end'] )   && is_string( $attrs['end'] )   ? $attrs['end']   : '—';
			$tz    = isset( $attrs['timezone'] ) && is_string( $attrs['timezone'] ) ? $attrs['timezone'] : 'app';
			return sprintf( '%s → %s (%s)', $start, $end, $tz );
		}

		$windows = isset( $attrs['windows'] ) && is_array( $attrs['windows'] ) ? $attrs['windows'] : [];
		return sprintf( '%d windows', count( $windows ) );
	}
}
