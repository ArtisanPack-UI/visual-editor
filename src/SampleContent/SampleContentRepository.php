<?php

/**
 * Repository that reads dev-app sample-content fixtures and seeds
 * them directly into the C1/C2 database tables.
 *
 * The fixtures model the five site-editor entities — `wp_template`,
 * `wp_template_part`, `wp_navigation`, `wp_block` (patterns), and
 * `globalStyles`. Every file is a single-record JSON document laid out
 * under `{fixturesDir}/{fragment}/{name}.json`, where `fragment` is
 * the REST path fragment (e.g. `templates`, `template-parts`).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SampleContent;

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPatternCategory;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class SampleContentRepository
{
	/**
	 * Map of REST URL fragment → (kind, name) that the site editor
	 * registers by default. The REST fragment doubles as the sub-directory
	 * under the fixtures root.
	 *
	 * @var array<string, array{kind: string, name: string}>
	 */
	public const ENTITY_MAP = [
		'templates'      => [ 'kind' => 'postType', 'name' => 'wp_template' ],
		'template-parts' => [ 'kind' => 'postType', 'name' => 'wp_template_part' ],
		'navigation'     => [ 'kind' => 'postType', 'name' => 'wp_navigation' ],
		'patterns'       => [ 'kind' => 'postType', 'name' => 'wp_block' ],
		'global-styles'  => [ 'kind' => 'root', 'name' => 'globalStyles' ],
	];

	/**
	 * Loads every fixture under `$fixturesDir` grouped by its REST
	 * URL fragment.
	 *
	 * The directory layout mirrors the package's REST surface:
	 *
	 *     tests/Fixtures/sample-content/
	 *     ├── templates/
	 *     │   ├── index.json
	 *     │   └── single.json
	 *     ├── template-parts/
	 *     │   └── header.json
	 *     ├── navigation/…
	 *     ├── patterns/…
	 *     └── global-styles/default.json
	 *
	 * Subdirectories that do not match {@see ENTITY_MAP} are ignored
	 * so host apps can park in-progress or draft fixtures alongside
	 * the canonical set without breaking the seeder.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $fixturesDir  Absolute path to the fixtures root.
	 *
	 * @return array<string, array<string, array<string, mixed>>> Map
	 *     of `urlFragment => [ basename => record ]`.
	 *
	 * @throws InvalidArgumentException If the directory is missing.
	 * @throws RuntimeException         If a fixture file cannot be read or
	 *                                  does not contain a JSON object.
	 */
	public function loadFixtures( string $fixturesDir ): array
	{
		if ( ! is_dir( $fixturesDir ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Sample-content fixtures directory not found: %s', $fixturesDir )
			);
		}

		$fixtures = [];

		foreach ( array_keys( self::ENTITY_MAP ) as $fragment ) {
			$entityDir = rtrim( $fixturesDir, DIRECTORY_SEPARATOR )
				. DIRECTORY_SEPARATOR . $fragment;

			if ( ! is_dir( $entityDir ) ) {
				$fixtures[ $fragment ] = [];

				continue;
			}

			$fixtures[ $fragment ] = $this->loadEntityDirectory( $entityDir );
		}

		return $fixtures;
	}

	/**
	 * Inserts or updates every loaded fixture into the C1/C2 database tables.
	 *
	 * Each entity kind is resolved by its natural unique key so re-running
	 * the command is idempotent — an existing record is updated in-place
	 * rather than duplicated.
	 *
	 * Unique keys per kind:
	 * - templates      → (slug, theme)
	 * - template-parts → (slug, theme)
	 * - navigation     → slug
	 * - patterns       → slug
	 * - global-styles  → theme (derived from fixture or config fallback)
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, array<string, array<string, mixed>>>  $fixtures
	 *     Fixtures grouped by URL fragment, as returned by {@see loadFixtures()}.
	 *
	 * @return array<string, int> Map of `urlFragment => upsertedRowCount`.
	 */
	public function seedToDatabase( array $fixtures ): array
	{
		return DB::transaction( function () use ( $fixtures ): array {
			$counts = [];

			// Templates — unique key: (slug, theme)
			$counts['templates'] = 0;
			$seenTemplates       = [];
			foreach ( $fixtures['templates'] ?? [] as $name => $record ) {
				$slug  = $this->requireNonEmptyString( $record, 'slug' );
				$theme = $this->requireNonEmptyString( $record, 'theme' );
				$this->assertUniqueKey( $seenTemplates, [ $slug, $theme ], (string) $name, 'template' );
				$model = VisualEditorTemplate::firstOrNew( [
					'slug'  => $slug,
					'theme' => $theme,
				] );
				$model->fill( [
					'title'       => $this->resolveTitle( $record ),
					'description' => $record['description'] ?? null,
					'status'      => $record['status'] ?? VisualEditorTemplate::STATUS_PUBLISH,
					'source'      => $record['source'] ?? VisualEditorTemplate::SOURCE_CUSTOM,
					'origin'      => $record['origin'] ?? null,
				] );
				$model->setContentEnvelope( $record['content'] ?? [] );
				$model->save();
				$counts['templates']++;
			}

			// Template parts — unique key: (slug, theme)
			$counts['template-parts'] = 0;
			$seenTemplateParts        = [];
			foreach ( $fixtures['template-parts'] ?? [] as $name => $record ) {
				$slug  = $this->requireNonEmptyString( $record, 'slug' );
				$theme = $this->requireNonEmptyString( $record, 'theme' );
				$this->assertUniqueKey( $seenTemplateParts, [ $slug, $theme ], (string) $name, 'template part' );
				$model = VisualEditorTemplatePart::firstOrNew( [
					'slug'  => $slug,
					'theme' => $theme,
				] );
				$model->fill( [
					'title' => $this->resolveTitle( $record ),
					'area'  => $record['area'] ?? VisualEditorTemplatePart::AREA_UNCATEGORIZED,
				] );
				$model->setContentEnvelope( $record['content'] ?? [] );
				$model->save();
				$counts['template-parts']++;
			}

			// Navigation — unique key: slug
			$counts['navigation'] = 0;
			$seenNavigation       = [];
			foreach ( $fixtures['navigation'] ?? [] as $name => $record ) {
				$slug = $this->requireNonEmptyString( $record, 'slug' );
				$this->assertUniqueKey( $seenNavigation, [ $slug ], (string) $name, 'navigation' );
				$model = VisualEditorNavigation::firstOrNew( [ 'slug' => $slug ] );
				$model->fill( [
					'title'      => $this->resolveTitle( $record ),
					'status'     => $record['status'] ?? VisualEditorNavigation::STATUS_PUBLISH,
					'menu_order' => (int) ( $record['menu_order'] ?? 0 ),
				] );
				$model->setContentEnvelope( $record['content'] ?? [] );
				$model->save();
				$counts['navigation']++;
			}

			// Patterns — unique key: slug; categories synced via pivot
			$counts['patterns'] = 0;
			$seenPatterns       = [];
			foreach ( $fixtures['patterns'] ?? [] as $name => $record ) {
				$slug = $this->requireNonEmptyString( $record, 'slug' );
				$this->assertUniqueKey( $seenPatterns, [ $slug ], (string) $name, 'pattern' );
				$model = VisualEditorPattern::firstOrNew( [ 'slug' => $slug ] );
				$model->fill( [
					'title'  => $this->resolveTitle( $record ),
					'synced' => (bool) ( $record['synced'] ?? false ),
					'status' => $record['status'] ?? VisualEditorPattern::STATUS_PUBLISH,
				] );
				$model->setContentEnvelope( $record['content'] ?? [] );
				$model->save();

				$this->syncPatternCategories( $model, $record['categories'] ?? [] );

				$counts['patterns']++;
			}

			// GlobalStyles — unique key: theme (no setContentEnvelope; settings/styles stored directly)
			$counts['global-styles'] = 0;
			$seenGlobalStyles        = [];
			foreach ( $fixtures['global-styles'] ?? [] as $name => $record ) {
				$theme = $this->resolveGlobalStylesTheme( $record );
				$this->assertUniqueKey( $seenGlobalStyles, [ $theme ], (string) $name, 'global-styles' );
				$model = VisualEditorGlobalStyles::firstOrNew( [ 'theme' => $theme ] );
				$model->fill( [
					'version'  => (int) ( $record['version'] ?? 3 ),
					'settings' => $record['settings'] ?? [],
					'styles'   => $record['styles'] ?? [],
				] );
				$model->save();
				$counts['global-styles']++;
			}

			return $counts;
		} );
	}

	/**
	 * Records a fixture's resolved natural key for its kind and fails fast
	 * when a second fixture in the same set maps to the same key. Without
	 * this guard, two files sorted by filename would silently overwrite one
	 * another and inflate the per-kind seed counts.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, string>  $seen      Map of encoded key => fixture name, passed by reference.
	 * @param  list<string>           $keyParts  The natural-key field values, treated atomically.
	 * @param  string                 $name      The current fixture's basename.
	 * @param  string                 $kind      Human-readable entity kind for the message.
	 */
	protected function assertUniqueKey( array &$seen, array $keyParts, string $name, string $kind ): void
	{
		// Encode the parts so distinct pairs like ['a|b', 'c'] and ['a', 'b|c']
		// can never collide on a delimiter-joined string.
		$key = json_encode( $keyParts, JSON_THROW_ON_ERROR );

		if ( isset( $seen[ $key ] ) ) {
			throw new RuntimeException(
				sprintf(
					'Duplicate %s natural key `%s` in sample-content fixtures `%s` and `%s`.',
					$kind,
					implode( ', ', $keyParts ),
					$seen[ $key ],
					$name
				)
			);
		}

		$seen[ $key ] = $name;
	}

	/**
	 * Returns a required natural-key field from a fixture, failing fast when
	 * it is missing, non-string, or empty so a malformed fixture cannot
	 * silently upsert an empty-key row.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $record  The decoded fixture record.
	 * @param  string                $key     The required field name.
	 *
	 * @return string The non-empty field value.
	 */
	protected function requireNonEmptyString( array $record, string $key ): string
	{
		$value = $record[ $key ] ?? null;

		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException(
				sprintf( 'Sample-content fixture is missing required `%s`.', $key )
			);
		}

		return $value;
	}

	/**
	 * @param  string  $directory  Absolute path to an entity-type fixture dir.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function loadEntityDirectory( string $directory ): array
	{
		$records = [];

		$files = glob( rtrim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*.json' ) ?: [];

		sort( $files );

		foreach ( $files as $file ) {
			$basename = pathinfo( $file, PATHINFO_FILENAME );
			$contents = file_get_contents( $file );

			if ( false === $contents ) {
				throw new RuntimeException( sprintf( 'Unable to read fixture: %s', $file ) );
			}

			$records[ $basename ] = $this->decode( $contents, $file );
		}

		return $records;
	}

	/**
	 * Syncs the category pivot for a pattern, auto-creating category rows
	 * by slug on first use (mirrors the store/update controller behaviour).
	 *
	 * @param  array<int, mixed>  $categorySlugs
	 */
	protected function syncPatternCategories( VisualEditorPattern $model, array $categorySlugs ): void
	{
		$ids = [];

		foreach ( $categorySlugs as $slug ) {
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}

			$category = VisualEditorPatternCategory::firstOrCreate(
				[ 'slug' => $slug ],
				[ 'name' => ucwords( str_replace( [ '-', '_' ], ' ', $slug ) ) ]
			);

			$ids[] = $category->id;
		}

		$model->categories()->sync( $ids );
	}

	/**
	 * Resolves the theme for a global-styles fixture record.
	 *
	 * Checks the record itself first (future fixtures may carry a `theme`
	 * key), then falls back to the configured sample-content theme, and
	 * finally to `artisanpack-base` which matches the templates shipped
	 * in the package fixtures.
	 */
	protected function resolveGlobalStylesTheme( array $record ): string
	{
		if ( isset( $record['theme'] ) && is_string( $record['theme'] ) && '' !== $record['theme'] ) {
			return $record['theme'];
		}

		$configured = config( 'artisanpack.visual-editor.sample_content.theme' );

		if ( is_string( $configured ) && '' !== $configured ) {
			return $configured;
		}

		return 'artisanpack-base';
	}

	/**
	 * Extracts the human-readable title from a fixture record.
	 *
	 * Fixtures use the REST `{ rendered: "…" }` title shape; plain
	 * string titles are also accepted.
	 *
	 * @param  array<string, mixed>  $record
	 */
	protected function resolveTitle( array $record ): string
	{
		$title = $record['title'] ?? '';

		if ( is_array( $title ) ) {
			return isset( $title['rendered'] ) && is_string( $title['rendered'] )
				? $title['rendered']
				: '';
		}

		return is_string( $title ) ? $title : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function decode( string $payload, string $source ): array
	{
		try {
			$decoded = json_decode( $payload, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			throw new RuntimeException(
				sprintf( 'Unable to decode sample-content fixture %s: %s', $source, $e->getMessage() ),
				0,
				$e
			);
		}

		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new RuntimeException(
				sprintf( 'Sample-content fixture %s must decode to a JSON object.', $source )
			);
		}

		return $decoded;
	}
}
