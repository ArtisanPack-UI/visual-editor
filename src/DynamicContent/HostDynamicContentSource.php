<?php

/**
 * Host-registered Dynamic Content source.
 *
 * A value object describing one token source registered by a host app,
 * a package, or a cms-framework plugin/theme via
 * {@see \ArtisanPackUI\VisualEditor\VisualEditor::registerDynamicContentSource()}.
 * Where cms-framework's `DynamicContentTypeRegistry` reads from the DB
 * (types authored in the admin UI), this is the code-path registration
 * that lets any bootable component contribute tokens without needing
 * cms-framework installed — the editor's inserter + `{{` autocomplete
 * and the render-time binding resolver both consult host sources first,
 * then fall back to cms-framework.
 *
 * A source resolves at render time through the supplied `$resolver`
 * callable: for a singleton source it returns an associative array of
 * field → value; for a collection source it returns a list of such
 * arrays. The resolver is invoked once per token walk — callers that
 * need caching should memoize inside their closure.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\DynamicContent;

use Closure;
use InvalidArgumentException;

class HostDynamicContentSource
{
	/**
	 * Canonical source-slug pattern: lowercase snake_case. Mirrors
	 * {@see \ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry::NAME_PATTERN}
	 * so a host source slug slots into a `{{ slug.field }}` token or a
	 * binding `args.token` without any further escaping.
	 *
	 * @since 1.9.0
	 */
	public const SLUG_PATTERN = '/^[a-z][a-z0-9_]*$/';

	/**
	 * @param  list<array{slug: string, label: string, type: string, description?: string}>  $fields
	 */
	final public function __construct(
		public readonly string $slug,
		public readonly string $label,
		public readonly string $cardinality,
		public readonly array $fields,
		public readonly Closure $resolver,
		public readonly string $description = '',
		public readonly string $icon = '',
	) {
	}

	/**
	 * Build a source from a definition array.
	 *
	 * Accepts the same shape the public
	 * {@see \ArtisanPackUI\VisualEditor\VisualEditor::registerDynamicContentSource()}
	 * takes so the value object owns the validation and the facade stays
	 * a thin pass-through.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, mixed>  $definition
	 */
	public static function fromArray( array $definition ): self
	{
		$slug = self::requireString( $definition, 'slug' );

		if ( 1 !== preg_match( self::SLUG_PATTERN, $slug ) ) {
			throw new InvalidArgumentException( sprintf(
				'Dynamic content source slug "%s" is invalid. Expected lowercase snake_case (letters, digits, underscores).',
				$slug
			) );
		}

		$cardinality = strtolower( self::requireString( $definition, 'cardinality' ) );

		if ( 'singleton' !== $cardinality && 'collection' !== $cardinality ) {
			throw new InvalidArgumentException( sprintf(
				'Dynamic content source "%s" has invalid cardinality "%s". Expected "singleton" or "collection".',
				$slug,
				$cardinality
			) );
		}

		$resolver = $definition['resolver'] ?? null;

		if ( ! is_callable( $resolver ) ) {
			throw new InvalidArgumentException( sprintf(
				'Dynamic content source "%s" must supply a callable "resolver".',
				$slug
			) );
		}

		return new self(
			slug: $slug,
			label: self::optionalString( $definition, 'label', $slug ),
			cardinality: $cardinality,
			fields: self::normalizeFields( $slug, $definition['fields'] ?? [] ),
			resolver: Closure::fromCallable( $resolver ),
			description: self::optionalString( $definition, 'description', '' ),
			icon: self::optionalString( $definition, 'icon', '' ),
		);
	}

	/**
	 * Invoke the resolver for a singleton source and coerce the result
	 * to an associative array (or null when the source has no value).
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolveSingleton(): ?array
	{
		$value = ( $this->resolver )();

		if ( ! is_array( $value ) || [] === $value ) {
			return is_array( $value ) ? $value : null;
		}

		// Guard against a caller returning a list — a singleton bag is
		// always keyed by field slug, never numerically indexed.
		return array_is_list( $value ) ? null : $value;
	}

	/**
	 * Invoke the resolver for a collection source and coerce the result
	 * to a list of associative arrays. Non-array rows are dropped.
	 *
	 * @since 1.9.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function resolveCollection(): array
	{
		$value = ( $this->resolver )();

		if ( ! is_array( $value ) ) {
			return [];
		}

		$rows = [];

		foreach ( array_values( $value ) as $row ) {
			if ( is_array( $row ) && ! array_is_list( $row ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Read one row of a collection source by numeric index.
	 *
	 * Mirrors {@see \ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services\DynamicContentAccessor::collectionItem()}
	 * — an explicit index against a singleton is a shape mismatch and
	 * returns null rather than the singleton bag.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function readItem( int $index ): ?array
	{
		if ( 'singleton' === $this->cardinality ) {
			return null;
		}

		$rows = $this->resolveCollection();

		return $rows[ $index ] ?? null;
	}

	/**
	 * Public shape suited to the sources-listing endpoint. Field entries
	 * carry the same `{slug, label, type}` triple as cms-framework so the
	 * merged response the controller returns is uniform.
	 *
	 * @since 1.9.0
	 *
	 * @return array{
	 *     slug: string,
	 *     label: string,
	 *     cardinality: string,
	 *     origin: string,
	 *     description: string,
	 *     icon: string,
	 *     fields: list<array{slug: string, label: string, type: string}>,
	 * }
	 */
	public function toArray(): array
	{
		return [
			'slug'        => $this->slug,
			'label'       => $this->label,
			'cardinality' => $this->cardinality,
			'origin'      => 'host',
			'description' => $this->description,
			'icon'        => $this->icon,
			'fields'      => array_map(
				static fn ( array $field ): array => [
					'slug'  => $field['slug'],
					'label' => $field['label'],
					'type'  => $field['type'],
				],
				$this->fields
			),
		];
	}

	/**
	 * @param  array<string, mixed>  $definition
	 *
	 * @since 1.9.0
	 */
	protected static function requireString( array $definition, string $key ): string
	{
		$value = $definition[ $key ] ?? null;

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			throw new InvalidArgumentException( sprintf(
				'Dynamic content source definition is missing required string "%s".',
				$key
			) );
		}

		return trim( $value );
	}

	/**
	 * @param  array<string, mixed>  $definition
	 *
	 * @since 1.9.0
	 */
	protected static function optionalString( array $definition, string $key, string $default ): string
	{
		$value = $definition[ $key ] ?? null;

		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : $default;
	}

	/**
	 * Normalize the field list. Each entry needs a non-empty `slug`;
	 * label defaults to the slug and type defaults to `text` (mirroring
	 * cms-framework's own default) so hosts can omit noise.
	 *
	 * @since 1.9.0
	 *
	 * @return list<array{slug: string, label: string, type: string, description?: string}>
	 */
	protected static function normalizeFields( string $sourceSlug, mixed $fields ): array
	{
		if ( ! is_array( $fields ) ) {
			throw new InvalidArgumentException( sprintf(
				'Dynamic content source "%s" fields must be an array.',
				$sourceSlug
			) );
		}

		$normalized = [];

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$slug = $field['slug'] ?? null;

			if ( ! is_string( $slug ) || '' === trim( $slug ) ) {
				continue;
			}

			$slug  = trim( $slug );
			$label = isset( $field['label'] ) && is_string( $field['label'] ) && '' !== trim( $field['label'] )
				? trim( $field['label'] )
				: $slug;
			$type  = isset( $field['type'] ) && is_string( $field['type'] ) && '' !== trim( $field['type'] )
				? trim( $field['type'] )
				: 'text';

			$entry = [
				'slug'  => $slug,
				'label' => $label,
				'type'  => $type,
			];

			if ( isset( $field['description'] ) && is_string( $field['description'] ) && '' !== trim( $field['description'] ) ) {
				$entry['description'] = trim( $field['description'] );
			}

			$normalized[] = $entry;
		}

		return $normalized;
	}
}
