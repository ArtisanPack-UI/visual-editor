<?php

/**
 * Binding source that reads cms-framework Dynamic Content values.
 *
 * Bindings on any block attribute can point at a Dynamic Content token
 * (e.g. `business_info.phone`, `team[0].name`) via
 * `{ source: 'dynamic_content', args: { token: 'business_info.phone' } }`.
 * Resolution goes through cms-framework's `DynamicContentAccessor` so we
 * return the raw structured value (string for scalar fields, media id
 * for images, array for compound address fields) — the empty-value
 * policy runs on the result.
 *
 * The source is a soft dependency on cms-framework: with the package
 * absent the source registers, `resolve()` returns null, and the field
 * catalog is empty. That keeps VE installable stand-alone.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings\Sources;

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use Throwable;

class DynamicContentSource implements BlockBindingSource
{
	/**
	 * Canonical source name. Snake-cased to satisfy the registry's
	 * `/^[a-z][a-z0-9_]*$/` pattern — the docs surface the friendlier
	 * `artisanpack/dynamic-content` alias in prose but the wire value
	 * bindings persist is `dynamic_content`.
	 *
	 * @since 1.4.0
	 */
	public const NAME = 'dynamic_content';

	/**
	 * Optional extras key used by the dynamic-loop block SSR to inject a
	 * "current record index" scope. When the extras map contains
	 * `dc_index => [ 'team' => 3 ]`, a token like `team.name` resolves as
	 * `team[3].name`. The loop block sets this before delegating to the
	 * shared resolver for each iteration.
	 *
	 * @since 1.4.0
	 */
	public const EXTRAS_INDEX_KEY = 'dc_index';

	public function name(): string
	{
		return self::NAME;
	}

	public function resolve( BindingContext $context, array $args ): mixed
	{
		$token = is_string( $args['token'] ?? null ) ? trim( $args['token'] ) : '';

		if ( '' === $token ) {
			return null;
		}

		$token = $this->applyLoopIndex( $token, $context );

		if ( ! class_exists( 'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor' ) ) {
			return null;
		}

		try {
			$value = $this->readToken( $token );
		} catch ( Throwable $e ) {
			report( $e );

			return null;
		}

		return $this->applyScheme( $value, $args['scheme'] ?? null );
	}

	/**
	 * Apply a URL scheme prefix when the binding declared one and the
	 * value isn't already prefixed.
	 *
	 * Supported schemes: `mailto`, `tel`, `url` (no-op — the value is
	 * already a URL). Used by the Button binding panel when binding a
	 * link to a phone/email DC field so the raw value renders as a
	 * clickable href.
	 *
	 * @since 1.4.0
	 */
	protected function applyScheme( mixed $value, mixed $scheme ): mixed
	{
		if ( ! is_string( $scheme ) || ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		$lower = strtolower( $value );

		if ( str_starts_with( $lower, 'http://' ) || str_starts_with( $lower, 'https://' )
			|| str_starts_with( $lower, 'mailto:' ) || str_starts_with( $lower, 'tel:' ) ) {
			return $value;
		}

		return match ( $scheme ) {
			'mailto' => 'mailto:' . $value,
			'tel'    => 'tel:' . preg_replace( '/[^\d+]/', '', $value ),
			default  => $value,
		};
	}

	public function eagerLoadRelations( array $bindingArgs ): array
	{
		return [];
	}

	public function availableFields( string $resource, ?string $modelClass = null ): array
	{
		$registryClass = 'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Managers\\DynamicContentTypeRegistry';

		if ( ! class_exists( $registryClass ) ) {
			return [];
		}

		try {
			/** @var object $registry */
			$registry = app( $registryClass );
			$types    = $registry->all();
		} catch ( Throwable $e ) {
			report( $e );

			return [];
		}

		if ( ! is_array( $types ) ) {
			return [];
		}

		$fields = [];

		foreach ( $types as $typeSlug => $definition ) {
			if ( ! is_string( $typeSlug ) || ! is_array( $definition ) ) {
				continue;
			}

			$typeLabel      = (string) ( $definition['name'] ?? $typeSlug );
			$cardinality    = $definition['cardinality'] ?? null;
			$cardinalityStr = $cardinality instanceof \BackedEnum ? $cardinality->value : (string) $cardinality;

			foreach ( (array) ( $definition['fields'] ?? [] ) as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$fieldSlug = (string) ( $field['slug'] ?? '' );

				if ( '' === $fieldSlug ) {
					continue;
				}

				$fields[] = [
					'key'   => $typeSlug . '.' . $fieldSlug,
					'label' => $typeLabel . ' → ' . ( (string) ( $field['label'] ?? $fieldSlug ) ),
					'type'  => $this->mapFieldType( (string) ( $field['type'] ?? 'text' ) ),
					'meta'  => [
						'source_slug'  => $typeSlug,
						'source_label' => $typeLabel,
						'field_slug'   => $fieldSlug,
						'field_type'   => (string) ( $field['type'] ?? 'text' ),
						'cardinality'  => 'collection' === $cardinalityStr ? 'collection' : 'singleton',
					],
				];
			}
		}

		return $fields;
	}

	/**
	 * Rewrite `source.field` to `source[N].field` when the loop block has
	 * pushed a current-record index into `$context->extras()`.
	 *
	 * @since 1.4.0
	 */
	protected function applyLoopIndex( string $token, BindingContext $context ): string
	{
		$indexMap = $context->extras()[ self::EXTRAS_INDEX_KEY ] ?? null;

		if ( ! is_array( $indexMap ) || [] === $indexMap ) {
			return $token;
		}

		// Only rewrite bare `source.field...` tokens — an explicit
		// `source[n].field` from the author wins.
		if ( ! preg_match( '/^([a-z][a-z0-9_]*)(\.[^\[].*)?$/i', $token, $matches ) ) {
			return $token;
		}

		$sourceSlug = $matches[1];

		if ( ! isset( $indexMap[ $sourceSlug ] ) || ! is_int( $indexMap[ $sourceSlug ] ) ) {
			return $token;
		}

		$tail = $matches[2] ?? '';

		return $sourceSlug . '[' . $indexMap[ $sourceSlug ] . ']' . $tail;
	}

	/**
	 * Read a token through the cms-framework accessor.
	 *
	 * @since 1.4.0
	 */
	protected function readToken( string $token ): mixed
	{
		$accessorClass = 'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor';

		/** @var object $accessor */
		$accessor = app( $accessorClass );

		$segments = preg_split( '/[.\[\]]+/', $token, -1, PREG_SPLIT_NO_EMPTY );

		if ( false === $segments || [] === $segments ) {
			return null;
		}

		$sourceSlug = array_shift( $segments );

		if ( ! is_string( $sourceSlug ) || '' === $sourceSlug ) {
			return null;
		}

		$data = $this->readSourceData( $accessor, $sourceSlug, $segments );

		if ( null === $data ) {
			return null;
		}

		return $this->walk( $data, $segments );
	}

	/**
	 * Fetch either a singleton bag or a collection row for the given
	 * source, shrinking `$segments` to the remaining path.
	 *
	 * @param  list<int|string>  $segments
	 *
	 * @since 1.4.0
	 */
	protected function readSourceData( object $accessor, string $sourceSlug, array &$segments ): ?array
	{
		// Explicit index: `team[0].name` → segments start with an int.
		if ( isset( $segments[0] ) && is_numeric( $segments[0] ) ) {
			$index = (int) array_shift( $segments );

			return $accessor->collectionItem( $sourceSlug, $index );
		}

		// Try singleton first — accessor returns null for a collection
		// source, in which case fall through to first-record.
		$singleton = $accessor->singleton( $sourceSlug );

		if ( is_array( $singleton ) ) {
			return $singleton;
		}

		// Implicit "first record" for a collection — mirrors the string
		// resolver's convention (`team.name` == `team[0].name`).
		return $accessor->collectionItem( $sourceSlug, 0 );
	}

	/**
	 * Walk a nested associative array along dotted / bracketed segments.
	 *
	 * @param  list<int|string>  $segments
	 *
	 * @since 1.4.0
	 */
	protected function walk( mixed $value, array $segments ): mixed
	{
		foreach ( $segments as $segment ) {
			if ( ! is_array( $value ) ) {
				return null;
			}

			$key = is_numeric( $segment ) ? (int) $segment : (string) $segment;

			if ( ! array_key_exists( $key, $value ) ) {
				return null;
			}

			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Map a cms-framework field-type slug to the binding-layer type label.
	 *
	 * @since 1.4.0
	 */
	protected function mapFieldType( string $fieldType ): string
	{
		return match ( $fieldType ) {
			'number'         => 'number',
			'boolean'        => 'boolean',
			'date'           => 'date',
			'datetime'       => 'datetime',
			'url'            => 'url',
			'image'          => 'image',
			'rich_text'      => 'html',
			'address'        => 'string',
			'email', 'phone' => 'string',
			default          => 'string',
		};
	}
}
