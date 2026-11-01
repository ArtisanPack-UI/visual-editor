<?php

/**
 * Collection of the {@see ContentSource} instances the Content
 * visibility rule can inspect.
 *
 * Seeded with the built-in sources by
 * {@see \ArtisanPackUI\VisualEditor\VisualEditorServiceProvider} and
 * extended through the `ap.visualEditor.visibility.registerContentSources`
 * filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility;

class ContentSourceRegistry
{
	/**
	 * @var array<string, ContentSource>
	 */
	protected array $sources = [];

	/**
	 * @since 1.20.0
	 *
	 * @param  array<int, ContentSource>  $sources  The initial sources.
	 */
	public function __construct( array $sources = [] )
	{
		foreach ( $sources as $source ) {
			$this->register( $source );
		}
	}

	/**
	 * Add a source, replacing any source with the same key.
	 *
	 * @since 1.20.0
	 *
	 * @param  ContentSource  $source  The source to add.
	 */
	public function register( ContentSource $source ): void
	{
		$this->sources[ $source->key() ] = $source;
	}

	/**
	 * Find a source by key.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $key  The source key.
	 *
	 * @return ContentSource|null The source, or null when none is registered.
	 */
	public function get( string $key ): ?ContentSource
	{
		return $this->sources[ $key ] ?? null;
	}

	/**
	 * Every registered source, in registration order.
	 *
	 * @since 1.20.0
	 *
	 * @return array<int, ContentSource> The sources.
	 */
	public function all(): array
	{
		return array_values( $this->sources );
	}

	/**
	 * The sources in the shape the Inspector's Content subsection reads
	 * from `data-visibility-content-sources`.
	 *
	 * @since 1.20.0
	 *
	 * @return array<int, array{key: string, label: string, blocks: array<int, string>, conditions: array<int, array{value: string, label: string}>}> The payload.
	 */
	public function toEditorPayload(): array
	{
		$payload = [];

		foreach ( $this->sources as $source ) {
			$conditions = [];

			foreach ( $source->conditions() as $value => $label ) {
				$conditions[] = [
					'value' => (string) $value,
					'label' => $label,
				];
			}

			$payload[] = [
				'key'        => $source->key(),
				'label'      => $source->label(),
				'blocks'     => array_values( $source->blockNames() ),
				'conditions' => $conditions,
			];
		}

		return $payload;
	}
}
