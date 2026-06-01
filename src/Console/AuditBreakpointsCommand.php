<?php

/**
 * `visual-editor:audit-breakpoints` console command (#487).
 *
 * Walks every block tree stored in the host application's
 * editor-backed models and reports any per-breakpoint overrides whose
 * key is no longer present in the resolved {@see BreakpointRegistry}.
 *
 * Orphans are preserved on save — the resolver simply skips them at
 * render time. This command surfaces them so theme/config authors can
 * decide whether to reintroduce the breakpoint, migrate the value to
 * a different breakpoint, or strip it.
 *
 * Resource models are discovered through the same map the resource
 * resolver uses: `config('artisanpack.visual-editor.resources')`. The
 * legacy `VisualEditorPost` table is also scanned when present.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Console;

use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Throwable;

class AuditBreakpointsCommand extends Command
{
	/**
	 * @var string
	 */
	protected $signature = 'visual-editor:audit-breakpoints
		{--resource= : Limit the audit to a single resource slug from config.}
		{--json : Output the report as machine-readable JSON instead of a table.}';

	/**
	 * @var string
	 */
	protected $description = 'Find block attributes that store overrides for breakpoints that are no longer registered.';

	public function handle(
		ConfigRepository $config,
		BreakpointRegistry $registry,
		ResponsiveValueResolver $resolver,
	): int {
		$scoped   = (string) $this->option( 'resource' );
		$outputAs = $this->option( 'json' ) ? 'json' : 'table';

		$known = array_flip( $registry->keysWithBase() );

		$report  = [];
		$scanned = 0;

		$models = $this->collectModels( $config, '' === $scoped ? null : $scoped );

		if ( [] === $models ) {
			$this->warn( 'No resource models discovered. Configure `artisanpack.visual-editor.resources` or install the legacy `ve_contents` table.' );

			return self::SUCCESS;
		}

		foreach ( $models as $slug => $modelClass ) {
			// Wraps BOTH `query()` and the cursor iteration in one
			// try/catch — a DB/hydration error mid-cursor would
			// otherwise abort the entire audit instead of skipping
			// the offending resource with a warning.
			try {
				/** @var \Illuminate\Database\Eloquent\Builder $query */
				$query = $modelClass::query();

				foreach ( $query->cursor() as $record ) {
					$scanned++;

					$column = method_exists( $record, 'getBlockContentColumn' )
						? (string) $record->getBlockContentColumn()
						: 'blocks';

					$blocks = $record->{$column} ?? null;

					if ( ! is_array( $blocks ) ) {
						continue;
					}

					$orphans = [];
					$this->walkBlocks( $blocks, $resolver, $known, $orphans );

					if ( [] === $orphans ) {
						continue;
					}

					$report[] = [
						'resource' => $slug,
						'id'       => $record->getKey(),
						'orphans'  => $orphans,
					];
				}
			} catch ( Throwable $e ) {
				$this->warn( sprintf( 'Skipping resource "%s": %s', $slug, $e->getMessage() ) );

				continue;
			}
		}

		if ( 'json' === $outputAs ) {
			$encoded = json_encode(
				[
					'scanned' => $scanned,
					'records' => $report,
				],
				JSON_PRETTY_PRINT
			);

			if ( false === $encoded ) {
				$this->error( 'Failed to encode breakpoint audit as JSON: ' . json_last_error_msg() );

				return self::FAILURE;
			}

			$this->line( $encoded );

			return self::SUCCESS;
		}

		if ( [] === $report ) {
			$this->info( sprintf( 'Audited %d record(s). No orphaned breakpoint overrides found.', $scanned ) );

			return self::SUCCESS;
		}

		$rows = [];

		foreach ( $report as $entry ) {
			$rows[] = [
				$entry['resource'],
				(string) $entry['id'],
				implode( ', ', $this->flattenOrphans( $entry['orphans'] ) ),
			];
		}

		$this->table( [ 'Resource', 'Record ID', 'Orphaned overrides' ], $rows );

		$this->warn( sprintf( 'Audited %d record(s). %d record(s) carry orphaned overrides.', $scanned, count( $report ) ) );

		return self::SUCCESS;
	}

	/**
	 * @return array<string, class-string>
	 */
	protected function collectModels( ConfigRepository $config, ?string $scoped ): array
	{
		$resources = (array) $config->get( 'artisanpack.visual-editor.resources', [] );

		$models = [];

		foreach ( $resources as $slug => $class ) {
			if ( ! is_string( $slug ) || ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}

			if ( null !== $scoped && $slug !== $scoped ) {
				continue;
			}

			$models[ $slug ] = $class;
		}

		if ( null === $scoped || 've_contents' === $scoped ) {
			if ( class_exists( VisualEditorPost::class ) ) {
				$models['ve_contents'] = VisualEditorPost::class;
			}
		}

		return $models;
	}

	/**
	 * Recursively walks a block tree, accumulating orphaned override
	 * keys per `block.attribute_path`.
	 *
	 * @param  array<int, mixed>             $blocks
	 * @param  array<string, int>            $known   Map of valid breakpoint keys
	 *                                                (`base`, `sm`, …) → 0.
	 * @param  array<string, array<int, string>>  $orphans  Output accumulator,
	 *                                                      keyed by
	 *                                                      `block_name@path`.
	 */
	protected function walkBlocks(
		array $blocks,
		ResponsiveValueResolver $resolver,
		array $known,
		array &$orphans,
	): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name  = (string) ( $block['blockName'] ?? $block['name'] ?? 'unknown' );
			$attrs = $block['attrs'] ?? $block['attributes'] ?? [];

			if ( is_array( $attrs ) ) {
				$this->walkAttributes( $name, '', $attrs, $resolver, $known, $orphans );
			}

			$inner = $block['innerBlocks'] ?? [];

			if ( is_array( $inner ) ) {
				$this->walkBlocks( $inner, $resolver, $known, $orphans );
			}
		}
	}

	/**
	 * @param  array<string, mixed>                 $attrs
	 * @param  array<string, int>                   $known
	 * @param  array<string, array<int, string>>    $orphans
	 */
	protected function walkAttributes(
		string $blockName,
		string $path,
		array $attrs,
		ResponsiveValueResolver $resolver,
		array $known,
		array &$orphans,
	): void {
		foreach ( $attrs as $key => $value ) {
			$childPath = '' === $path ? (string) $key : $path . '.' . (string) $key;

			if ( is_array( $value ) && ! array_is_list( $value ) ) {
				$discovered = $resolver->orphanedKeys( $value );

				if ( [] !== $discovered ) {
					// Filter out keys that are actually nested attribute
					// names (e.g. nested style objects); orphanedKeys()
					// gives us every non-registry key, but only string
					// keys that aren't themselves arrays are overrides.
					$filtered = array_values( array_filter(
						$discovered,
						static fn ( string $k ): bool => ! is_array( $value[ $k ] ?? null )
							&& ! isset( $known[ $k ] )
					) );

					if ( [] !== $filtered ) {
						$bucket             = $blockName . '@' . $childPath;
						$orphans[ $bucket ] = array_values( array_unique( array_merge(
							$orphans[ $bucket ] ?? [],
							$filtered
						) ) );
					}
				}

				$this->walkAttributes( $blockName, $childPath, $value, $resolver, $known, $orphans );
			}
		}
	}

	/**
	 * @param  array<string, array<int, string>>  $orphans
	 *
	 * @return array<int, string>
	 */
	protected function flattenOrphans( array $orphans ): array
	{
		$rows = [];

		foreach ( $orphans as $location => $keys ) {
			$rows[] = $location . ' → [' . implode( ', ', $keys ) . ']';
		}

		return $rows;
	}
}
